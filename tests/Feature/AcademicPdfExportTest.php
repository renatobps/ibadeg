<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcademicPdfExportTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $status = 'approved'): User
    {
        $student = User::factory()->create();
        $student->forceFill(['name' => 'João da Conceição de São Sebastião', 'status' => $status])->save();

        return $student;
    }

    private function administrator(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['name' => 'Coordenação de Teste', 'role' => 'admin', 'status' => 'approved'])->save();

        return $admin;
    }

    private function course(): Course
    {
        $course = Course::create(['slug' => 'pdf-test', 'name' => 'Curso Médio em Teologia', 'description' => 'Curso para teste de exportação', 'max_attempts' => 3, 'passing_grade' => 6, 'workload_hours' => 1680]);
        $source = json_decode(File::get(resource_path('course/questions.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($source as $subject) {
            $course->subjects()->create(['number' => $subject['number'], 'title' => $subject['title'], 'book_file' => '01.pdf', 'exam_file' => '01.pdf']);
        }

        return $course->load('subjects');
    }

    /** @param list<array{grade: float, kind: string, reason: string}> $revisions */
    private function attempt(User $student, Subject $subject, int $number, string $status, array $revisions): Attempt
    {
        $attempt = $student->attempts()->create(['subject_id' => $subject->id, 'number' => $number, 'status' => $status, 'question_snapshot' => [], 'student_snapshot' => ['name' => $student->name], 'max_points' => 40, 'score' => $status === 'graded' ? $revisions[array_key_last($revisions)]['grade'] : null, 'started_at' => '2026-11-10 10:00:00', 'submitted_at' => $status === 'in_progress' ? null : '2026-11-10 11:00:00']);
        foreach ($revisions as $index => $revision) {
            $attempt->grades()->create($revision + ['revision' => $index + 1, 'points' => $revision['grade'] * 4, 'max_points' => 40, 'answer_snapshot' => [], 'created_at' => '2026-11-10 12:00:00']);
        }

        return $attempt;
    }

    private function certificate(User $student, Course $course, User $admin): Certificate
    {
        return Certificate::create(['uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id, 'issued_by' => $admin->id, 'issued_at' => '2026-11-12 10:00:00', 'snapshot' => ['student_name' => $student->name, 'student_document' => '000.000.000-00', 'student_birth_date' => '1980-08-07', 'student_nationality' => 'Brasileiro (a)', 'course_name' => $course->name, 'workload_hours' => 1680, 'passing_grade' => 6, 'average' => 9.25, 'subjects' => $course->subjects->map(fn (Subject $subject): array => ['number' => $subject->number, 'title' => $subject->title, 'grade' => 9.25, 'workload_hours' => 80])->all()]]);
    }

    /** @return array{logo: string, generatedAt: Carbon} */
    private function viewData(): array
    {
        return ['logo' => 'data:image/png;base64,'.base64_encode(File::get(public_path('assets/logo-ibadeg.png'))), 'generatedAt' => now()];
    }

    private function assertPdfDownload(TestResponse $response, string $filename): void
    {
        $response->assertOk()->assertDownload($filename)->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringEndsWith('%%EOF', trim($response->getContent()));
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));
    }

    private function saveVisualSample(TestResponse $response, string $name): void
    {
        if ($directory = getenv('IBADEG_PDF_QA_DIR')) {
            File::ensureDirectoryExists($directory);
            File::put($directory.'/'.$name.'.pdf', $response->getContent());
        }
    }

    public function test_student_downloads_pdf_with_every_subject_attempt_and_grade_revision(): void
    {
        $course = $this->course();
        $student = $this->student();
        $this->attempt($student, $course->subjects[0], 1, 'graded', [['grade' => 7.25, 'kind' => 'provisional', 'reason' => 'Correção automática inicial.'], ['grade' => 8.75, 'kind' => 'final', 'reason' => 'Correção das questões abertas.'], ['grade' => 9.25, 'kind' => 'final', 'reason' => 'Revisão após conferência da apostila.']]);
        $this->attempt($student, $course->subjects[0], 2, 'graded', [['grade' => 8.50, 'kind' => 'final', 'reason' => 'Segunda tentativa corrigida.']]);
        $this->attempt($student, $course->subjects[1], 1, 'pending_review', [['grade' => 6.50, 'kind' => 'provisional', 'reason' => 'Aguardando correção do professor.']]);
        $this->attempt($student, $course->subjects[2], 1, 'in_progress', []);

        $this->actingAs($student)->get(route('history'))->assertOk()->assertSee(route('history.pdf'), false);
        $response = $this->get(route('history.pdf'));
        $this->assertPdfDownload($response, 'historico-ibadeg-matricula-'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT).'.pdf');
        $rows = app(AcademicService::class)->transcript($student, $course);
        $html = view('pdf.history', compact('student', 'course', 'rows') + $this->viewData())->render();
        $this->assertStringContainsString('Escola Bíblica Dominical', $html);
        foreach (['7,25', '8,75', '9,25', '8,50', '6,50', 'Provisória', 'Prova ainda não enviada. Sem nota registrada.'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertDatabaseCount('grade_records', 5);
        $this->saveVisualSample($response, 'historico-individual');
    }

    public function test_administrator_downloads_individual_history_and_all_revisions_report(): void
    {
        $course = $this->course();
        $student = $this->student();
        $admin = $this->administrator();
        $this->attempt($student, $course->subjects[0], 1, 'graded', [['grade' => 8.75, 'kind' => 'final', 'reason' => 'Nota original.'], ['grade' => 9.25, 'kind' => 'final', 'reason' => 'Conferência e revisão da correção.']]);
        $other = $this->student();
        $other->update(['name' => 'Outro Aluno Exemplo']);
        $this->attempt($other, $course->subjects[1], 1, 'pending_review', [['grade' => 6.50, 'kind' => 'provisional', 'reason' => 'Correção aberta pendente.']]);
        $this->attempt($student, $course->subjects[2], 1, 'in_progress', []);

        $this->actingAs($admin)->get(route('admin.student', $student))->assertOk()->assertSee(route('admin.student.history.pdf', $student), false);
        $this->assertPdfDownload($this->get(route('admin.student.history.pdf', $student)), 'historico-ibadeg-matricula-'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT).'.pdf');
        $response = $this->get(route('admin.export.pdf'));
        $this->assertPdfDownload($response, 'historico-completo-ibadeg-'.now()->format('Y-m-d').'.pdf');
        $attempts = Attempt::with('student', 'subject', 'grades.reviewer')->whereNotNull('submitted_at')->get();
        $html = view('pdf.complete-history', compact('attempts') + $this->viewData())->render();
        foreach (['Outro Aluno Exemplo', 'Nota original.', 'Conferência e revisão da correção.', '8,75', '9,25', '6,50', 'Provisória'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Livros Históricos', $html);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.export.pdf'), false);
        $this->get(route('admin.attempts'))->assertOk()->assertSee(route('admin.export.pdf'), false);
        $this->saveVisualSample($response, 'historico-completo');
    }

    public function test_certificate_pdf_uses_issued_snapshot_and_includes_all_twenty_one_final_grades(): void
    {
        $course = $this->course();
        $student = $this->student();
        $admin = $this->administrator();
        $certificate = $this->certificate($student, $course, $admin);
        $student->update(['name' => 'Nome Atual Alterado', 'document' => 'DOCUMENTOALTERADO', 'birth_date' => '1999-01-01']);
        $course->update(['name' => 'Nome Atual do Curso', 'workload_hours' => 2400]);

        $this->actingAs($student)->get(route('certificate.show', $certificate))->assertOk()->assertSee(route('certificate.pdf', $certificate), false);
        $response = $this->get(route('certificate.pdf', $certificate));
        $this->assertPdfDownload($response, 'certificado-ibadeg-'.$certificate->uuid.'.pdf');
        $html = view('pdf.certificate', compact('certificate') + $this->viewData())->render();
        foreach (['João da Conceição de São Sebastião', 'Curso Médio em Teologia', '1.680 horas', '80 Horas', 'Escola Bíblica Dominical', '9,25', '000.000.000-00', '07 de agosto de 1980', '12/11/2026'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Nome Atual Alterado', $html);
        $this->assertStringNotContainsString('DOCUMENTOALTERADO', $html);
        $this->assertStringNotContainsString('01 de janeiro de 1999', $html);
        $this->assertStringNotContainsString('2400 horas', $html);
        $this->assertDatabaseCount('certificates', 1);
        $this->assertSame(2, preg_match_all('~/Type\s*/Page\b~', $response->getContent()));
        $this->saveVisualSample($response, 'certificado');
        $this->assertTrue(str_contains($response->getContent(), 'EdwardianScriptITC'), 'O PDF precisa incorporar a fonte Edwardian Script do modelo.');
        $this->actingAs($admin)->get(route('certificate.pdf', $certificate))->assertOk();
        $this->get(route('certificate.pdf', [$certificate, 'inline' => 1]))
            ->assertOk()->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename=certificado-ibadeg-'.$certificate->uuid.'.pdf');
    }

    public function test_empty_administrative_history_still_downloads_a_valid_pdf(): void
    {
        $admin = $this->administrator();
        $response = $this->actingAs($admin)->get(route('admin.export.pdf'));
        $this->assertPdfDownload($response, 'historico-completo-ibadeg-'.now()->format('Y-m-d').'.pdf');
        $html = view('pdf.complete-history', ['attempts' => collect()] + $this->viewData())->render();
        $this->assertStringContainsString('Nenhuma prova enviada até o momento.', $html);
        $this->saveVisualSample($response, 'historico-vazio');
    }

    public function test_student_cannot_download_another_students_certificate_or_admin_reports(): void
    {
        $course = $this->course();
        $student = $this->student();
        $other = $this->student();
        $certificate = $this->certificate($other, $course, $this->administrator());
        $this->actingAs($student)->get(route('certificate.pdf', $certificate))->assertForbidden();
        $this->get(route('certificate.pdf', [$certificate, 'inline' => 1]))->assertForbidden();
        $this->get(route('admin.student.history.pdf', $other))->assertForbidden();
        $this->get(route('admin.export.pdf'))->assertForbidden();
        $this->assertDatabaseCount('certificates', 1);
    }

    public static function guestPaths(): array
    {
        return ['own history' => ['/historico/pdf'], 'certificate' => ['/certificados/00000000-0000-4000-8000-000000000000/pdf'], 'student history' => ['/admin/alunos/1/historico/pdf'], 'complete history' => ['/admin/exportar-historico/pdf']];
    }

    #[DataProvider('guestPaths')]
    public function test_pdf_download_requires_login(string $path): void
    {
        $this->get($path)->assertRedirect(route('login'));
    }

    public function test_missing_certificate_returns_not_found_without_creating_one(): void
    {
        $this->actingAs($this->student())->get('/certificados/00000000-0000-4000-8000-000000000000/pdf')->assertNotFound();
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_pdf_templates_escape_student_names_and_review_notes(): void
    {
        $course = $this->course();
        $student = $this->student();
        $student->update(['name' => '<img src="https://example.invalid/tracker">']);
        $this->attempt($student, $course->subjects[0], 1, 'graded', [['grade' => 9.25, 'kind' => 'final', 'reason' => '<script>alert(1)</script>']]);
        $rows = app(AcademicService::class)->transcript($student, $course);
        $html = view('pdf.history', compact('student', 'course', 'rows') + $this->viewData())->render();
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src="https://', $html);
    }
}
