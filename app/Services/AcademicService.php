<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\AuditEvent;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcademicService
{
    public function transcript(User $student, Course $course): array
    {
        $attempts = $student->attempts()->whereIn('subject_id', $course->subjects->pluck('id'))->with('grades')->get()->groupBy('subject_id');

        return $course->subjects->map(function ($subject) use ($attempts, $course) {
            $all = $attempts->get($subject->id, collect());
            $best = $all->where('status', 'graded')->whereNotNull('score')->sortByDesc('score')->first();

            return ['subject' => $subject, 'attempts' => $all, 'best' => $best, 'passed' => $best && $best->score >= $course->passing_grade];
        })->all();
    }

    public function issue(User $student, Course $course, User $admin): Certificate
    {
        abort_unless($admin->isAdmin(), 403);

        return DB::transaction(function () use ($student, $course, $admin) {
            $student = User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $course = Course::whereKey($course->id)->with('subjects')->lockForUpdate()->firstOrFail();
            if ($existing = Certificate::where('user_id', $student->id)->where('course_id', $course->id)->first()) {
                return $existing;
            }
            $rows = $this->transcript($student, $course);
            if (! $student->canStudy() || ! $rows || collect($rows)->contains(fn ($r) => ! $r['passed'])) {
                throw ValidationException::withMessages(['certificate' => 'O aluno precisa estar liberado e aprovado em todas as disciplinas com notas finais.']);
            }
            if (! $course->workload_hours) {
                throw ValidationException::withMessages(['certificate' => 'Configure a carga horária oficial do curso antes de emitir o certificado.']);
            }
            // O lock das tentativas coordena emissão com revisões de notas.
            Attempt::whereIn('id', collect($rows)->map(fn ($r) => $r['best']->id))->lockForUpdate()->get();
            $rows = $this->transcript($student, $course);
            if (collect($rows)->contains(fn ($r) => ! $r['passed'])) {
                throw ValidationException::withMessages(['certificate' => 'As notas foram revisadas. Confira o histórico antes de emitir.']);
            }
            $certificate = Certificate::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $student->id,
                'course_id' => $course->id,
                'issued_by' => $admin->id,
                'issued_at' => now(),
                'snapshot' => [
                    'student_name' => $student->name,
                    'student_document' => $student->document,
                    'student_birth_date' => $student->birth_date?->toDateString(),
                    'student_nationality' => $student->nationality,
                    'course_name' => $course->name,
                    'workload_hours' => $course->workload_hours,
                    'passing_grade' => (float) $course->passing_grade,
                    'average' => round(collect($rows)->avg(fn ($row): float => (float) $row['best']->score), 2),
                    'subjects' => collect($rows)->map(fn ($row): array => [
                        'number' => $row['subject']->number,
                        'title' => $row['subject']->title,
                        'grade' => (float) $row['best']->score,
                        'workload_hours' => (int) $course->workload_hours === 1680 && count($rows) === 21 ? 80 : null,
                        'attempt_id' => $row['best']->id,
                        'grade_record_id' => $row['best']->grades->first()->id,
                    ])->all(),
                ],
            ]);
            AuditEvent::record('certificate.issued', 'certificate:'.$certificate->uuid);

            return $certificate;
        });
    }
}
