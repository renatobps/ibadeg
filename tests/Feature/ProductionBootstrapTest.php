<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Certificate;
use App\Models\User;
use Database\Seeders\DemoStudentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class ProductionBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_bootstrap_seeds_demo_only_when_enabled_and_preserves_existing_credentials(): void
    {
        $this->app->instance('env', 'production');
        config([
            'ibadeg.seed_demo' => true,
            'ibadeg.admin.email' => 'coordenacao@example.com',
            'ibadeg.admin.password' => 'InicialSegura2026!',
        ]);

        $this->artisan('ibadeg:bootstrap')->assertSuccessful();
        $this->assertDatabaseCount('users', 17);
        $this->assertDatabaseCount('certificates', 4);
        $this->assertDatabaseHas('users', ['email' => 'ana.silva.teste@example.com', 'status' => 'pending']);
        $administrator = User::where('email', 'coordenacao@example.com')->firstOrFail();
        $this->assertTrue($administrator->isAdmin());
        $this->assertTrue(Hash::check('InicialSegura2026!', $administrator->password));
        $administrator->update(['password' => 'AlteradaDepois2026!']);
        $attemptCount = Attempt::count();
        $certificateSnapshot = Certificate::firstOrFail()->snapshot;

        $this->artisan('ibadeg:bootstrap')->assertSuccessful();
        $this->assertDatabaseCount('users', 17);
        $this->assertDatabaseCount('attempts', $attemptCount);
        $this->assertDatabaseCount('certificates', 4);
        $this->assertSame($certificateSnapshot, Certificate::firstOrFail()->snapshot);
        $this->assertTrue(Hash::check('AlteradaDepois2026!', $administrator->fresh()->password));
    }

    public function test_production_seeder_requires_explicit_demo_setting(): void
    {
        $this->app->instance('env', 'production');
        config(['ibadeg.seed_demo' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('IBADEG_SEED_DEMO');
        app(DemoStudentsSeeder::class)->run();
    }

    public function test_bootstrap_without_demo_creates_only_the_administrator(): void
    {
        config([
            'ibadeg.seed_demo' => false,
            'ibadeg.admin.email' => 'coordenacao@example.com',
            'ibadeg.admin.password' => 'InicialSegura2026!',
        ]);

        $this->artisan('ibadeg:bootstrap')->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('attempts', 0);
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_bootstrap_does_not_elevate_an_existing_student_to_administrator(): void
    {
        $student = User::factory()->create(['email' => 'aluno@example.com']);
        config([
            'ibadeg.admin.email' => $student->email,
            'ibadeg.admin.password' => 'InicialSegura2026!',
        ]);

        $this->artisan('ibadeg:bootstrap')->assertFailed();
        $this->assertFalse($student->fresh()->isAdmin());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_bootstrap_requires_credentials_before_creating_the_first_administrator(): void
    {
        config(['ibadeg.admin.email' => null, 'ibadeg.admin.password' => null]);

        $this->artisan('ibadeg:bootstrap')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_configured_reverse_proxy_generates_https_links(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.2',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/entrar')->assertOk()->assertSee('action="https://127.0.0.1/entrar"', false);
    }
}
