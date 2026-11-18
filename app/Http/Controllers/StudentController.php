<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicService;
use App\Services\ExamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function dashboard(Request $r, AcademicService $academic)
    {
        if ($r->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        $course = Course::with('subjects')->firstOrFail();
        $rows = $academic->transcript($r->user(), $course);

        return view('student.dashboard', compact('course', 'rows'));
    }

    public function subject(Request $r, Subject $subject)
    {
        abort_unless($subject->active || $r->user()->isAdmin(), 404);
        $attempts = $r->user()->attempts()->where('subject_id', $subject->id)->latest()->get();

        return view('student.subject', compact('subject', 'attempts'));
    }

    public function material(Request $r, Subject $subject, string $kind)
    {
        abort_unless($subject->active || $r->user()->isAdmin(), 404);
        abort_unless(in_array($kind, ['livro', 'prova']), 404);
        $file = resource_path('course/materials/'.($kind === 'livro' ? 'books/' : 'exams/').sprintf('%02d', $subject->number).'.pdf');
        abort_unless(is_file($file), 404, 'Material não encontrado.');

        return response()->file($file, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="IBADEG-'.sprintf('%02d', $subject->number).'-'.$kind.'.pdf"']);
    }

    public function start(Request $r, Subject $subject, ExamService $service)
    {
        $attempt = $service->start($r->user(), $subject);

        return redirect()->route('attempt.show', $attempt);
    }

    private function owner(Request $r, Attempt $attempt): void
    {
        abort_unless($attempt->user_id === $r->user()->id || $r->user()->isAdmin(), 403);
    }

    public function attempt(Request $r, Attempt $attempt)
    {
        $this->owner($r, $attempt);
        $attempt->load('subject.course', 'answers', 'grades.reviewer', 'student');
        if ($attempt->status === 'in_progress') {
            abort_unless($r->user()->canStudy(), 403);
            if ($r->user()->isAdmin()) {
                return redirect()->route('admin.attempt', $attempt);
            }

return view('student.exam', compact('attempt'));
        }

        return view('student.result', compact('attempt'));
    }

    public function draft(Request $r, Attempt $attempt, ExamService $service)
    {
        $this->owner($r, $attempt);
        $data = $r->validate(['answers' => 'present|array']);
        DB::transaction(function () use ($r, $attempt, $service, $data) {
            $user = User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->canStudy(), 403);
            $attempt = Attempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($attempt->status === 'in_progress' && $attempt->user_id === $user->id, 409);
            $attempt->update(['draft_answers' => $service->validateAnswers($attempt, $data['answers'], true)]);
        });

        return response()->json(['saved_at' => now()->format('H:i')]);
    }

    public function submit(Request $r, Attempt $attempt, ExamService $service)
    {
        $this->owner($r, $attempt);
        $data = $r->validate(['answers' => 'required|array']);
        $service->submit($r->user(), $attempt, $data['answers']);

        return redirect()->route('attempt.show', $attempt)->with('success', 'Prova enviada. Suas respostas e seu resultado foram registrados.');
    }

    public function history(Request $r, AcademicService $academic)
    {
        $course = Course::with('subjects')->firstOrFail();
        $student = $r->user();
        $rows = $academic->transcript($student, $course);

        return view('student.history', compact('course', 'student', 'rows'));
    }

    public function certificate(Request $r, Certificate $certificate)
    {
        abort_unless($certificate->user_id === $r->user()->id || $r->user()->isAdmin(),403);

        return view('certificate.document',compact('certificate'));
    }
}
