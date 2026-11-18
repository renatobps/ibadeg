<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicService;
use App\Services\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcademicPlatformTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $status = 'approved'): User
    {
        $u = User::factory()->create();
        $u->forceFill(['status' => $status])->save();

        return $u;
    }

    private function admin(): User
    {
        $u = $this->student();
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    private function subject(bool $manual = false): Subject
    {
        $c = Course::firstOrCreate(['slug' => 'test'], ['name' => 'Teologia', 'description' => 'Curso', 'max_attempts' => 2, 'passing_grade' => 7, 'workload_hours' => 100]);
        $s = Subject::create(['course_id' => $c->id, 'number' => $c->subjects()->count() + 1, 'title' => 'Introdução Bíblica', 'book_file' => '01.pdf', 'exam_file' => '01.pdf']);
        $s->questions()->create(['code' => '1_a', 'position' => 0, 'group_code' => '1', 'instruction' => '1) Marque C ou E', 'kind' => 'ce', 'label' => 'a', 'prompt' => 'Conduzir para dentro', 'options' => [['key' => 'C', 'text' => 'C'], ['key' => 'E', 'text' => 'E']], 'correct_answer' => 'C', 'scorable' => true]);
        if ($manual) {
            $s->questions()->create(['code' => '2', 'position' => 1, 'group_code' => '2', 'instruction' => '2) Explique o conceito.', 'kind' => 'fill', 'correct_answer' => 'Referência reservada ao professor', 'scorable' => true]);
        }

        return $s;
    }

    public function test_home_login_and_registration_are_available(): void
    {
        $this->get('/')->assertOk()->assertSee('Prepare-se para servir');
        $this->get('/entrar')->assertOk();
        $this->get('/matricula')->assertOk()->assertSee('Nome da mãe')->assertSee('Escolaridade');
    }

    public function test_registration_cannot_grant_its_own_access_or_admin_role(): void
    {
        $s = $this->subject();
        $this->post('/matricula', ['name' => 'Aluno de Teste', 'email' => 'aluno@teste.local', 'document' => '12345678900', 'birth_date' => '1995-01-10', 'address' => 'Rua de teste 10', 'postal_code' => '70000000', 'city' => 'Brasília', 'state' => 'DF', 'phone' => '61999999999', 'sex' => 'Masculino', 'nationality' => 'Brasileira', 'birthplace' => 'Brasília', 'mother_name' => 'Nome da mãe', 'education' => 'Médio completo', 'church' => 'Igreja de teste', 'pastor' => 'Pastor de teste', 'password' => 'SenhaSegura123', 'password_confirmation' => 'SenhaSegura123', 'status' => 'approved', 'role' => 'admin'])->assertRedirect(route('dashboard'));
        $u = User::where('email', 'aluno@teste.local')->firstOrFail();
        $this->assertSame('pending', $u->status);
        $this->assertSame('student', $u->role);
        $this->assertDatabaseHas('enrollments', ['user_id' => $u->id]);
        $this->assertTrue(password_verify('SenhaSegura123', $u->password));
    }

    public function test_pending_student_cannot_start_exam_or_read_books(): void
    {
        $s = $this->subject();
        $u = $this->student('pending');
        $this->actingAs($u)->get('/painel')->assertOk()->assertSee('Sua matrícula está em análise');
        $this->post(route('subject.start', $s))->assertForbidden();
        $this->get(route('subject.material', [$s, 'livro']))->assertForbidden();
        $this->assertDatabaseCount('attempts', 0);
    }

    public function test_admin_can_approve_and_set_individual_attempt_limit(): void
    {
        $s = $this->subject();
        $u = $this->student('pending');
        $this->actingAs($this->admin())->put(route('admin.student.update', $u), ['status' => 'approved', 'attempt_limit' => 4])->assertRedirect();
        $this->assertSame('approved', $u->fresh()->status);
        $this->assertSame(4, $s->limitFor($u));
    }

    public function test_student_cannot_access_admin_or_someone_elses_results(): void
    {
        $s = $this->subject();
        $a = app(ExamService::class)->start($this->student(), $s);
        $this->actingAs($this->student())->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('attempt.show', $a))->assertForbidden();
        $this->put(route('attempt.draft', $a), ['answers' => ['1_a' => 'E']])->assertForbidden();
    }

    public function test_start_resumes_existing_attempt_and_enforces_limit(): void
    {
        $s = $this->subject();
        $s->update(['max_attempts' => 1]);
        $u = $this->student();
        $service = app(ExamService::class);
        $a = $service->start($u, $s);
        $this->assertSame($a->id, $service->start($u, $s)->id);
        $service->submit($u, $a, ['1_a' => 'C']);
        $this->actingAs($u)->post(route('subject.start', $s))->assertSessionHasErrors('attempts');
        $this->assertDatabaseCount('attempts', 1);
    }

    public function test_limits_inherit_course_subject_and_student_in_that_order(): void
    {
        $s = $this->subject();
        $u = $this->student();
        $this->assertSame(2, $s->limitFor($u));
        $s->update(['max_attempts' => 3]);
        $this->assertSame(3, $s->limitFor($u));
        Enrollment::create(['user_id' => $u->id, 'course_id' => $s->course_id, 'attempt_limit' => 0]);
        $this->assertSame(0, $s->limitFor($u));
    }

    public function test_answer_keys_are_hidden_before_submission_and_drafts_are_saved(): void
    {
        $s = $this->subject(true);
        $u = $this->student();
        $a = app(ExamService::class)->start($u, $s);
        $this->actingAs($u)->get(route('attempt.show', $a))->assertOk()->assertDontSee('Referência reservada ao professor')->assertSee('Conduzir para dentro');
        $this->putJson(route('attempt.draft', $a), ['answers' => ['1_a' => 'E', '2' => 'Resposta em andamento']])->assertOk();
        $this->assertSame('E', $a->fresh()->draft_answers['1_a']);
    }

    public function test_server_rejects_missing_invalid_and_foreign_answers(): void
    {
        $s = $this->subject();
        $u = $this->student();
        $a = app(ExamService::class)->start($u, $s);
        $this->actingAs($u)->post(route('attempt.submit', $a), ['answers' => ['1_a' => 'FORJADA'], 'score' => 10])->assertSessionHasErrors('answers.1_a');
        $this->post(route('attempt.submit', $a), ['answers' => ['foreign' => 'C']])->assertSessionHasErrors('answers');
        $this->assertDatabaseCount('grade_records', 0);
    }

    public function test_grading_is_server_side_and_submission_is_idempotent(): void
    {
        $s = $this->subject();
        $u = $this->student();
        $a = app(ExamService::class)->start($u, $s);
        $this->actingAs($u)->post(route('attempt.submit', $a), ['answers' => ['1_a' => 'E'], 'score' => 10])->assertRedirect();
        $this->assertEquals(0, $a->fresh()->score);
        $this->post(route('attempt.submit', $a), ['answers' => ['1_a' => 'C']])->assertRedirect();
        $this->assertEquals(0, $a->fresh()->score);
        $this->assertDatabaseCount('grade_records', 1);
    }

    public function test_open_questions_wait_for_review_and_all_revisions_are_preserved(): void
    {
        $s = $this->subject(true);
        $u = $this->student();
        $admin = $this->admin();
        $service = app(ExamService::class);
        $a = $service->start($u, $s);
        $service->submit($u, $a, ['1_a' => 'C', '2' => 'Resposta do aluno']);
        $this->assertSame('pending_review', $a->fresh()->status);
        $this->assertNull($a->fresh()->score);
        $this->assertSame('provisional', $a->grades()->first()->kind);
        $scores = $a->answers()->get()->mapWithKeys(fn ($v) => [$v->id => 1])->all();
        $service->review($a, $admin, $scores, [], 'Correção inicial das respostas.');
        $this->assertEquals(10, $a->fresh()->score);
        $scores[array_key_last($scores)] = .5;
        $service->review($a, $admin, $scores, [], 'Revisão com pontuação parcial.');
        $this->assertEquals(7.5, $a->fresh()->score);
        $this->assertCount(3, $a->grades()->get());
        $this->assertEquals(10, $a->grades()->where('revision', 2)->first()->grade);
        $this->actingAs($u)->get(route('attempt.show', $a))->assertOk()->assertSee('Revisão 3')->assertSee('Resposta do aluno');
    }

    public function test_existing_attempt_uses_its_original_question_snapshot(): void
    {
        $s = $this->subject();
        $u = $this->student();
        $service = app(ExamService::class);
        $a = $service->start($u, $s);
        $s->questions()->update(['correct_answer' => 'E']);
        $service->submit($u, $a, ['1_a' => 'C']);
        $this->assertEquals(10, $a->fresh()->score);
    }

    public function test_suspension_blocks_submit_even_for_an_open_attempt(): void
    {
        $s = $this->subject();
        $u = $this->student();
        $a = app(ExamService::class)->start($u, $s);
        $u->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($u)->post(route('attempt.submit', $a), ['answers' => ['1_a' => 'C']])->assertForbidden();
        $this->assertDatabaseCount('grade_records', 0);
    }

    public function test_grade_history_is_immutable(): void
    {
        $s = $this->subject();
        $u = $this->student();
        $service = app(ExamService::class);
        $a = $service->start($u, $s);
        $service->submit($u, $a, ['1_a' => 'C']);
        $g = $a->grades()->first();
        $this->expectException(\LogicException::class);
        $g->update(['grade' => 0]);
    }

    public function test_certificate_requires_approval_every_subject_and_official_hours(): void
    {
        $s = $this->subject();
        $u = $this->student();
        $u->update(['document' => 'DOCUMENTOTESTE123', 'birth_date' => '1980-08-07', 'nationality' => 'Brasileira']);
        $admin = $this->admin();
        $academic = app(AcademicService::class);
        try {
            $academic->issue($u, $s->course, $admin);
            $this->fail('Deveria exigir aprovação.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('certificate', $e->errors());
        }
        $service = app(ExamService::class);
        $a = $service->start($u, $s);
        $service->submit($u, $a, ['1_a' => 'C']);
        $s->course->update(['workload_hours' => null]);
        try {
            $academic->issue($u, $s->course->fresh(), $admin);
            $this->fail('Deveria exigir carga horária.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('certificate', $e->errors());
        }
        $s->course->update(['workload_hours' => 200]);
        $c = $academic->issue($u, $s->course->fresh(), $admin);
        $this->assertSame($c->id, $academic->issue($u, $s->course->fresh(), $admin)->id);
        $this->assertEquals(10, $c->snapshot['average']);
        $this->assertSame('DOCUMENTOTESTE123', $c->snapshot['student_document']);
        $this->assertSame('1980-08-07', $c->snapshot['student_birth_date']);
        $this->assertSame('Brasileira', $c->snapshot['student_nationality']);
        $this->assertDatabaseCount('certificates', 1);
        $u->update(['document' => 'DOCUMENTOATUAL456', 'birth_date' => '1990-01-01']);
        $this->assertSame('DOCUMENTOTESTE123', $c->fresh()->snapshot['student_document']);
        $this->assertSame('1980-08-07', $c->fresh()->snapshot['student_birth_date']);
        $this->actingAs($u)->get(route('certificate.show', $c))->assertOk()->assertSee('Diploma e histórico');
        $this->get(route('certificate.verify', $c))->assertOk()->assertDontSee($u->email);
        $service->review($a, $admin, $a->answers()->get()->mapWithKeys(fn ($v) => [$v->id => 0])->all(), [], 'Revisão posterior à emissão.');
        $this->assertEquals(10, $c->fresh()->snapshot['average']);
    }

    public function test_all_admin_screens_and_private_materials_render(): void
    {
        $s = $this->subject(true);
        $student = $this->student('pending');
        $admin = $this->admin();
        $this->actingAs($admin);
        foreach (['admin.dashboard', 'admin.students', 'admin.attempts', 'admin.settings'] as $route) {
            $this->get(route($route))->assertOk();
        }$this->get(route('admin.student', $student))->assertOk()->assertSee('Liberado');
        $this->get(route('subject.material', [$s, 'livro']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/assets/../resources/course/questions.json')->assertNotFound();
    }

    public function test_bundled_course_is_complete_and_seeding_preserves_existing_records(): void
    {
        $this->seed();
        $this->assertDatabaseCount('subjects', 21);
        $this->assertDatabaseCount('questions', 761);
        $this->assertDatabaseCount('users', 0);
        $s = Subject::first();
        $u = $this->student();
        $a = app(ExamService::class)->start($u, $s);
        $id = $s->questions()->first()->id;
        $this->seed();
        $this->assertDatabaseCount('questions', 761);
        $this->assertSame($id, $s->questions()->first()->id);
        $this->assertNotNull($a->fresh());
        $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('Introdução Bíblica')->assertSee('Escola Bíblica Dominical');
        $this->get(route('history'))->assertOk();
        foreach (Subject::all() as $subject) {
            foreach (['books', 'exams'] as $folder) {
                $this->assertFileExists(resource_path('course/materials/'.$folder.'/'.sprintf('%02d', $subject->number).'.pdf'));
            }
        }
    }
}
