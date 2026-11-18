<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\AuditEvent;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamService
{
    public function start(User $student, Subject $subject): Attempt
    {
        return DB::transaction(function () use ($student, $subject) {
            $student = User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $subject = Subject::whereKey($subject->id)->with('course')->lockForUpdate()->firstOrFail();
            abort_unless($student->canStudy() && ! $student->isAdmin() && $subject->active, 403, 'O acesso à prova precisa ser liberado pela administração.');
            Enrollment::firstOrCreate(['user_id' => $student->id, 'course_id' => $subject->course_id]);
            $attempt = $student->attempts()->where('subject_id', $subject->id)->where('status', 'in_progress')->first();
            if ($attempt) {
                return $attempt;
            }
            $count = $student->attempts()->where('subject_id', $subject->id)->count();
            if ($count >= $subject->limitFor($student)) {
                throw ValidationException::withMessages(['attempts' => 'Você atingiu o limite de tentativas desta disciplina. Solicite uma nova liberação à administração.']);
            }
            $snapshot = $subject->questions->map(fn ($q) => $q->only(['code', 'group_code', 'instruction', 'kind', 'label', 'prompt', 'options', 'correct_answer', 'excerpt', 'review_required', 'scorable']))->all();
            abort_if(! $snapshot, 409, 'Esta disciplina ainda não possui questões.');

            return $student->attempts()->create(['subject_id' => $subject->id, 'number' => $count + 1, 'question_snapshot' => $snapshot, 'student_snapshot' => $student->only(['name', 'document', 'email']), 'max_points' => collect($snapshot)->where('scorable', true)->count(), 'started_at' => now()]);
        });
    }

    public function validateAnswers(Attempt $attempt, array $answers, bool $partial = false): array
    {
        $questions = collect($attempt->question_snapshot)->where('scorable', true)->keyBy('code');
        if (array_diff(array_keys($answers), $questions->keys()->all())) {
            throw ValidationException::withMessages(['answers' => 'Há respostas que não pertencem a esta prova.']);
        }
        $errors = [];
        $clean = [];
        foreach ($questions as $key => $q) {
            $value = $answers[$key] ?? null;
            if ($partial && $value === null) {
                continue;
            }
            if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 4000) {
                $errors['answers.'.$key] = 'Responda este item (até 4.000 caracteres).';

                continue;
            }
            if ($q['kind'] !== 'fill' && ! in_array($value, array_column($q['options'], 'key'), true)) {
                $errors['answers.'.$key] = 'Selecione uma alternativa válida.';

                continue;
            }
            $clean[$key] = trim($value);
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    public function submit(User $student, Attempt $attempt, array $answers): Attempt
    {
        return DB::transaction(function () use ($student, $attempt, $answers) {
            $student = User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            abort_unless($student->canStudy(), 403, 'Seu acesso está suspenso. Entre em contato com a administração.');
            $attempt = Attempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($attempt->user_id === $student->id, 403);
            if ($attempt->status !== 'in_progress') {
                return $attempt;
            }
            $answers = $this->validateAnswers($attempt, $answers);
            foreach ($attempt->question_snapshot as $q) {
                if (! $q['scorable']) {
                    continue;
                }
                $manual = $q['kind'] === 'fill' || $q['review_required'];
                $attempt->answers()->create(['question_code' => $q['code'], 'answer' => $answers[$q['code']], 'points' => $manual ? null : (int) ($answers[$q['code']] === $q['correct_answer']), 'needs_review' => $manual]);
            }
            $pending = $attempt->answers()->whereNull('points')->exists();
            $attempt->update(['status' => $pending ? 'pending_review' : 'graded', 'submitted_at' => now(), 'draft_answers' => null]);
            $this->recordGrade($attempt, null, 'Correção automática no envio da prova.', $pending);
            AuditEvent::record('exam.submitted', 'attempt:'.$attempt->id, ['number' => $attempt->number]);

            return $attempt->fresh();
        });
    }

    public function review(Attempt $attempt, User $reviewer, array $scores, array $feedback, string $reason): void
    {
        abort_unless($reviewer->isAdmin(), 403);
        DB::transaction(function () use ($attempt, $reviewer, $scores, $feedback, $reason) {
            $attempt = Attempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_if($attempt->status === 'in_progress', 409, 'A prova ainda não foi enviada.');
            $answers = $attempt->answers()->get();
            if (array_diff(array_keys($scores), $answers->pluck('id')->all()) || count($scores) !== $answers->count()) {
                throw ValidationException::withMessages(['scores' => 'Informe a pontuação de todos os itens desta prova.']);
            }
            foreach ($answers as $answer) {
                $score = $scores[$answer->id] ?? null;
                if (! is_numeric($score) || $score < 0 || $score > 1) {
                    throw ValidationException::withMessages(['scores.'.$answer->id => 'A pontuação deve ficar entre 0 e 1.']);
                }
                $answer->update(['points' => $score, 'feedback' => $feedback[$answer->id] ?? null, 'reviewer_id' => $reviewer->id, 'reviewed_at' => now()]);
            }
            $attempt->update(['status' => 'graded']);
            $this->recordGrade($attempt, $reviewer, $reason, false);
            AuditEvent::record('grade.reviewed', 'attempt:'.$attempt->id, ['reason' => $reason]);
        });
    }

    private function recordGrade(Attempt $attempt, ?User $reviewer, string $reason, bool $pending): void
    {
        $answers = $attempt->answers()->get();
        $points = (float) $answers->sum('points');
        $grade = round(10 * $points / max(1, $attempt->max_points), 2);
        $attempt->grades()->create(['revision' => ($attempt->grades()->max('revision') ?? 0) + 1, 'grade' => $grade, 'points' => $points, 'max_points' => $attempt->max_points, 'kind' => $pending ? 'provisional' : 'final', 'reason' => $reason, 'answer_snapshot' => $answers->map->only(['question_code', 'answer', 'points', 'feedback'])->all(), 'reviewer_id' => $reviewer?->id, 'created_at' => now()]);
        $attempt->update(['score' => $pending ? null : $grade]);
    }
}
