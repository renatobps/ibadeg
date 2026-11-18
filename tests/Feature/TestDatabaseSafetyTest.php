<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TestDatabaseSafetyTest extends TestCase
{
    public function test_application_cache_cannot_override_the_isolated_test_database(): void
    {
        $this->assertFalse($this->app->configurationIsCached());
        $this->assertSame('testing', $this->app->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public static function unsafeDatabases(): array
    {
        return [
            'application MySQL database' => ['testing', 'mysql', 'ibadeg'],
            'persistent SQLite file' => ['testing', 'sqlite', 'database/production.sqlite'],
            'local environment' => ['local', 'sqlite', ':memory:'],
        ];
    }

    #[DataProvider('unsafeDatabases')]
    public function test_refuses_unsafe_database_before_database_test_traits_run(string $environment, string $connection, string $database): void
    {
        $this->app->instance('env', $environment);
        config(['database.default' => $connection, 'database.connections.'.$connection.'.database' => $database]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Testes bloqueados');
        $this->ensureIsolatedTestDatabase($this->app);
    }
}
