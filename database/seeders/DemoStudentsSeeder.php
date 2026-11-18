<?php

namespace Database\Seeders;

use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicService;
use App\Services\ExamService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoStudentsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Os alunos de demonstração só podem ser criados nos ambientes local e testing.');
        }

        $administrator = User::where('role', 'admin')->where('status', 'approved')->firstOrFail();

        DB::transaction(function () use ($administrator): void {
            $this->call(DatabaseSeeder::class);
            $course = Course::where('slug', 'teologia')->with('subjects')->firstOrFail();
            if (! $course->workload_hours) {
                $course->update(['workload_hours' => 1680]);
            }

            $password = Hash::make('AlunoTeste2026!');
            foreach ($this->profiles() as $index => $profile) {
                $email = $profile['login'].'.teste@example.com';
                if (User::where('email', $email)->exists()) {
                    continue;
                }

                $student = User::create([
                    'name' => $profile['name'],
                    'email' => $email,
                    'password' => $password,
                    'document' => 'IBADEGTESTE'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'birth_date' => sprintf('%d-%02d-15', 1980 + $index, ($index % 12) + 1),
                    'address' => 'Rua de Exemplo, '.(100 + $index).' - Centro',
                    'postal_code' => '01001000',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'phone' => '1199000'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'sex' => $profile['sex'],
                    'nationality' => 'Brasileira',
                    'birthplace' => 'São Paulo - SP',
                    'father_name' => 'José dos Santos',
                    'mother_name' => 'Maria Aparecida dos Santos',
                    'education' => 'Médio completo',
                    'church' => 'Assembleia de Deus - Congregação de Teste',
                    'pastor' => 'Paulo Roberto de Oliveira',
                    'church_role' => 'Membro',
                    'nucleus' => 'Núcleo de Demonstração',
                ]);
                $student->forceFill([
                    'role' => 'student',
                    'status' => $profile['status'],
                    'approved_by' => $profile['status'] === 'approved' ? $administrator->id : null,
                    'approved_at' => $profile['status'] === 'approved' ? now() : null,
                ])->save();
                Enrollment::firstOrCreate(['user_id' => $student->id, 'course_id' => $course->id]);

                foreach ($course->subjects->take($profile['completed']) as $subject) {
                    if ($profile['login'] === 'lucas.ferreira' && $subject->number === 1) {
                        $this->completeAttempt($student, $subject, $administrator, 0.5);
                    }
                    $this->completeAttempt($student, $subject, $administrator, 0.8 + (($index + $subject->number) % 5) * 0.05);
                }

                if ($profile['next'] !== null) {
                    $subject = $course->subjects[$profile['completed']];
                    $attempt = app(ExamService::class)->start($student, $subject);
                    $answers = $this->answers($attempt);
                    if ($profile['next'] === 'pending_review') {
                        app(ExamService::class)->submit($student, $attempt, $answers);
                    } else {
                        $attempt->update(['draft_answers' => array_slice($answers, 0, 3, true)]);
                    }
                }

                if ($profile['certificate']) {
                    app(AcademicService::class)->issue($student, $course, $administrator);
                }
            }
        });
    }

    private function completeAttempt(User $student, Subject $subject, User $administrator, float $points): void
    {
        $service = app(ExamService::class);
        $attempt = $service->start($student, $subject);
        $attempt = $service->submit($student, $attempt, $this->answers($attempt));
        $scores = $attempt->answers()->get()->mapWithKeys(fn ($answer): array => [$answer->id => $points])->all();
        $service->review($attempt, $administrator, $scores, [], 'Correção de demonstração para testes do histórico acadêmico.');
    }

    /** @return array<string, string> */
    private function answers(Attempt $attempt): array
    {
        $answers = [];
        foreach ($attempt->question_snapshot as $question) {
            if (! $question['scorable']) {
                continue;
            }
            $answers[$question['code']] = $question['kind'] === 'fill'
                ? ($question['correct_answer'] ?: 'Resposta discursiva fictícia para avaliação de teste.')
                : (in_array($question['correct_answer'], array_column($question['options'], 'key'), true)
                    ? $question['correct_answer'] : $question['options'][0]['key']);
        }

        return $answers;
    }

    /** @return list<array{name: string, login: string, sex: string, status: string, completed: int, next: ?string, certificate: bool}> */
    private function profiles(): array
    {
        return [
            ['name' => 'Ana Clara da Silva', 'login' => 'ana.silva', 'sex' => 'Feminino', 'status' => 'pending', 'completed' => 0, 'next' => null, 'certificate' => false],
            ['name' => 'Mariana Aparecida dos Santos', 'login' => 'mariana.santos', 'sex' => 'Feminino', 'status' => 'pending', 'completed' => 0, 'next' => null, 'certificate' => false],
            ['name' => 'João Pedro de Oliveira', 'login' => 'joao.oliveira', 'sex' => 'Masculino', 'status' => 'pending', 'completed' => 0, 'next' => null, 'certificate' => false],
            ['name' => 'Rafael Henrique Costa', 'login' => 'rafael.costa', 'sex' => 'Masculino', 'status' => 'pending', 'completed' => 0, 'next' => null, 'certificate' => false],
            ['name' => 'Juliana Cristina Almeida', 'login' => 'juliana.almeida', 'sex' => 'Feminino', 'status' => 'approved', 'completed' => 0, 'next' => null, 'certificate' => false],
            ['name' => 'Gabriel Augusto Pereira', 'login' => 'gabriel.pereira', 'sex' => 'Masculino', 'status' => 'approved', 'completed' => 0, 'next' => null, 'certificate' => false],
            ['name' => 'Beatriz Nascimento Souza', 'login' => 'beatriz.souza', 'sex' => 'Feminino', 'status' => 'approved', 'completed' => 0, 'next' => null, 'certificate' => false],
            ['name' => 'Lucas Vinícius Ferreira', 'login' => 'lucas.ferreira', 'sex' => 'Masculino', 'status' => 'approved', 'completed' => 3, 'next' => 'in_progress', 'certificate' => false],
            ['name' => 'Camila Rodrigues Martins', 'login' => 'camila.martins', 'sex' => 'Feminino', 'status' => 'approved', 'completed' => 8, 'next' => 'pending_review', 'certificate' => false],
            ['name' => 'Pedro Henrique Ribeiro', 'login' => 'pedro.ribeiro', 'sex' => 'Masculino', 'status' => 'approved', 'completed' => 9, 'next' => 'in_progress', 'certificate' => false],
            ['name' => 'Fernanda Luísa Carvalho', 'login' => 'fernanda.carvalho', 'sex' => 'Feminino', 'status' => 'approved', 'completed' => 12, 'next' => 'pending_review', 'certificate' => false],
            ['name' => 'André Luiz Barbosa', 'login' => 'andre.barbosa', 'sex' => 'Masculino', 'status' => 'approved', 'completed' => 18, 'next' => 'in_progress', 'certificate' => false],
            ['name' => 'Maria Eduarda Gonçalves', 'login' => 'maria.goncalves', 'sex' => 'Feminino', 'status' => 'approved', 'completed' => 21, 'next' => null, 'certificate' => true],
            ['name' => 'José Carlos de Araújo', 'login' => 'jose.araujo', 'sex' => 'Masculino', 'status' => 'approved', 'completed' => 21, 'next' => null, 'certificate' => true],
            ['name' => 'Patrícia Aparecida Gomes', 'login' => 'patricia.gomes', 'sex' => 'Feminino', 'status' => 'approved', 'completed' => 21, 'next' => null, 'certificate' => true],
            ['name' => 'Marcos Antônio dos Reis', 'login' => 'marcos.reis', 'sex' => 'Masculino', 'status' => 'approved', 'completed' => 21, 'next' => null, 'certificate' => true],
        ];
    }
}
