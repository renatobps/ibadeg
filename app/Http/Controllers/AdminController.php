<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicService;
use App\Services\ExamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = ['students' => User::where('role', 'student')->count(), 'pending' => User::where('role', 'student')->where('status', 'pending')->count(), 'attempts' => Attempt::whereNotNull('submitted_at')->count(), 'review' => Attempt::where('status', 'pending_review')->count()];
        $students = User::where('role', 'student')->where('status', 'pending')->latest()->limit(6)->get();
        $attempts = Attempt::with('student', 'subject')->whereNotNull('submitted_at')->latest('submitted_at')->limit(7)->get();

        return view('admin.dashboard', compact('stats', 'students', 'attempts'));
    }

    public function students(Request $r)
    {
        $query = User::where('role', 'student')->withCount('attempts');
        if ($r->filled('q')) {
            $term = mb_substr($r->string('q')->toString(), 0, 100);
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
        }
        if (in_array($r->input('status'), ['pending', 'approved', 'suspended'])) {
            $query->where('status', $r->input('status'));
        }
        $students = $query->latest()->paginate(15)->withQueryString();

        return view('admin.students', compact('students'));
    }

    public function student(User $student, AcademicService $academic)
    {
        abort_unless($student->role === 'student', 404);
        $course = Course::with('subjects')->firstOrFail();
        $rows = $academic->transcript($student, $course);
        $enrollment = Enrollment::firstOrCreate(['user_id' => $student->id, 'course_id' => $course->id]);

        return view('admin.student', compact('student', 'course', 'rows', 'enrollment'));
    }

    public function updateStudent(Request $r, User $student)
    {
        abort_unless($student->role === 'student', 404);
        $data = $r->validate(['status' => 'required|in:pending,approved,suspended', 'attempt_limit' => 'nullable|integer|min:0|max:100', 'password' => ['nullable', 'confirmed', Password::min(10)->letters()->numbers()]]);
        DB::transaction(function () use ($r, $student, $data) {
            $student = User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $before = $student->status;
            $student->forceFill(['status' => $data['status'], 'approved_by' => $data['status'] === 'approved' ? $r->user()->id : $student->approved_by, 'approved_at' => $data['status'] === 'approved' ? ($student->approved_at ?? now()) : $student->approved_at]);
            if (! empty($data['password'])) {
                $student->password = $data['password'];
            }$student->save();
            Enrollment::updateOrCreate(['user_id' => $student->id, 'course_id' => Course::firstOrFail()->id], ['attempt_limit' => $data['attempt_limit'] ?? null]);
            AuditEvent::record('student.updated', 'user:'.$student->id, ['before' => $before, 'status' => $data['status'], 'attempt_limit' => $data['attempt_limit'] ?? null, 'password_reset' => ! empty($data['password'])]);
        });

        return back()->with('success', 'Acesso e limites do aluno atualizados.');
    }

    public function attempts(Request $r)
    {
        $query = Attempt::with('student', 'subject')->whereNotNull('submitted_at');
        if (in_array($r->input('status'), ['graded', 'pending_review'])) {
            $query->where('status', $r->input('status'));
        }if ($r->filled('subject')) {
            $query->where('subject_id', $r->input('subject'));
        }
        $attempts = $query->latest('submitted_at')->paginate(15)->withQueryString();
        $subjects = Subject::orderBy('number')->get();

        return view('admin.attempts', compact('attempts', 'subjects'));
    }

    public function attempt(Attempt $attempt)
    {
        $attempt->load('student', 'subject.course', 'answers', 'grades.reviewer');

        return view('admin.attempt', compact('attempt'));
    }

    public function review(Request $r, Attempt $attempt, ExamService $service)
    {
        $data = $r->validate(['scores' => 'required|array', 'scores.*' => 'required|numeric|between:0,1', 'feedback' => 'nullable|array', 'feedback.*' => 'nullable|string|max:1500', 'reason' => 'required|string|min:5|max:1000']);
        $service->review($attempt, $r->user(), $data['scores'], $data['feedback'] ?? [], $data['reason']);

        return back()->with('success', 'Correção registrada. A nota anterior foi preservada no histórico.');
    }

    public function settings()
    {
        $course = Course::with('subjects')->firstOrFail();

        return view('admin.settings', compact('course'));
    }

    public function saveSettings(Request $r)
    {
        $data = $r->validate(['max_attempts' => 'required|integer|min:0|max:100', 'passing_grade' => 'required|numeric|between:0,10', 'workload_hours' => 'nullable|integer|min:1|max:10000', 'subjects' => 'required|array', 'subjects.*.max_attempts' => 'nullable|integer|min:0|max:100', 'subjects.*.active' => 'required|boolean']);
        DB::transaction(function () use ($data) {
            $course = Course::lockForUpdate()->firstOrFail();
            $course->update(collect($data)->except('subjects')->all());
            foreach ($data['subjects'] as $id => $settings) {
                $course->subjects()->whereKey($id)->update($settings);
            }AuditEvent::record('course.settings', 'course:'.$course->id, collect($data)->except('subjects')->all());
        });

        return back()->with('success', 'Regras do curso atualizadas. As tentativas e notas anteriores foram preservadas.');
    }

    public function certificate(Request $r, User $student, AcademicService $academic)
    {
        abort_unless($student->role === 'student', 404);
        $certificate = $academic->issue($student, Course::firstOrFail(), $r->user());

        return redirect()->route('certificate.show', $certificate);
    }

    public function export()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Aluno', 'E-mail', 'Disciplina', 'Tentativa', 'Revisão', 'Tipo', 'Nota', 'Data', 'Motivo'], ';', escape: '');
            foreach (Attempt::with('student', 'subject', 'grades')->whereNotNull('submitted_at')->orderBy('id')->lazy(100) as $a) {
                foreach ($a->grades as $g) {
                    $row = [$a->student->name, $a->student->email, $a->subject->title, $a->number, $g->revision, $g->kind === 'final' ? 'Final' : 'Provisória', $g->grade, $g->created_at->format('d/m/Y H:i'), $g->reason];
                    fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/',(string) $v) ? "'".$v : $v,$row), ';', escape: '');
                }
            }fclose($out);
        }, 'ibadeg-historico-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
