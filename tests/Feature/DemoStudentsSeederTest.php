<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Services\AcademicService;
use Database\Seeders\DemoStudentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DemoStudentsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_students_cover_access_exams_and_printable_documents_without_overwriting_data(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['role' => 'admin', 'status' => 'approved'])->save();
        $existingStudent = User::factory()->create(['name' => 'Aluno já cadastrado']);
        $this->seed(DemoStudentsSeeder::class);

        $students = User::where('email', 'like', '%.teste@example.com')->get();
        $this->assertCount(16, $students);
        $this->assertCount(4, $students->where('status', 'pending'));
        $this->assertCount(12, $students->where('status', 'approved'));
        $this->assertDatabaseCount('enrollments', 16);
        $this->assertDatabaseCount('certificates', 4);
        $this->assertSame('Aluno já cadastrado', $existingStudent->fresh()->name);
        $this->assertTrue(Hash::check('AlunoTeste2026!', $students->first()->password));

        $course = Course::where('slug', 'teologia')->with('subjects')->firstOrFail();
        $this->assertEquals(1680, $course->workload_hours);
        $this->assertEquals(6, $course->passing_grade);
        $pending = $students->firstWhere('email', 'ana.silva.teste@example.com');
        $this->assertFalse($pending->canStudy());
        $this->assertNull($pending->approved_at);
        $this->assertSame(0, $pending->attempts()->count());
        $beginner = $students->firstWhere('email', 'juliana.almeida.teste@example.com');
        $this->assertTrue($beginner->canStudy());
        $this->assertSame($administrator->id, $beginner->approved_by);
        $this->assertSame(0, $beginner->attempts()->count());

        $partial = $students->firstWhere('email', 'lucas.ferreira.teste@example.com');
        $rows = app(AcademicService::class)->transcript($partial, $course);
        $this->assertCount(3, collect($rows)->where('passed', true));
        $this->assertSame(1, $partial->attempts()->where('status', 'in_progress')->count());
        $firstSubjectAttempts = $partial->attempts()->where('subject_id', $course->subjects->first()->id)->orderBy('number')->get();
        $this->assertCount(2, $firstSubjectAttempts);
        $this->assertLessThan(7, (float) $firstSubjectAttempts[0]->score);
        $this->assertGreaterThanOrEqual(7, (float) $firstSubjectAttempts[1]->score);
        $this->assertSame(2, Attempt::where('status', 'pending_review')->count());
        $this->assertTrue(Attempt::where('status', 'pending_review')->whereNull('score')->exists());
        $this->assertNotEmpty($partial->attempts()->where('status', 'in_progress')->firstOrFail()->draft_answers);

        foreach (Certificate::with('student')->get() as $certificate) {
            $rows = app(AcademicService::class)->transcript($certificate->student, $course);
            $this->assertCount(21, collect($rows)->where('passed', true));
            $this->assertCount(21, $certificate->snapshot['subjects']);
            $this->assertEquals(1680, $certificate->snapshot['workload_hours']);
        }
        $graduate = $students->firstWhere('email', 'maria.goncalves.teste@example.com');
        $certificate = $graduate->certificates()->firstOrFail();
        $this->actingAs($graduate)->get(route('certificate.pdf', $certificate))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('history.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');

        $attemptCount = Attempt::count();
        $certificateSnapshot = $certificate->snapshot;
        $partial->update(['name' => 'Nome alterado durante os testes', 'password' => 'NovaSenha2026!']);
        $course->update(['workload_hours' => 1800]);
        $this->seed(DemoStudentsSeeder::class);
        $this->assertDatabaseCount('users', 18);
        $this->assertDatabaseCount('attempts', $attemptCount);
        $this->assertDatabaseCount('certificates', 4);
        $this->assertSame('Nome alterado durante os testes', $partial->fresh()->name);
        $this->assertTrue(Hash::check('NovaSenha2026!', $partial->fresh()->password));
        $this->assertSame($certificateSnapshot, $certificate->fresh()->snapshot);
        $this->assertEquals(1800, $course->fresh()->workload_hours);

        $this->expectException(ValidationException::class);
        app(AcademicService::class)->issue($partial, $course, $administrator);
    }
}
