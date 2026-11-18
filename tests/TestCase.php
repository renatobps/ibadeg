<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();
        $this->ensureIsolatedTestDatabase($application);

        return $application;
    }

    protected function ensureIsolatedTestDatabase(Application $application): void
    {
        $connection = $application['config']->get('database.default');
        $database = $application['config']->get('database.connections.'.$connection.'.database');
        $isolated = ($connection === 'sqlite' && $database === ':memory:')
            || ($connection === 'mysql' && $database === 'ibadeg_test');

        if (! $application->environment('testing') || ! $isolated) {
            throw new RuntimeException('Testes bloqueados: use o ambiente testing com SQLite em memória ou o banco exclusivo ibadeg_test. O banco da aplicação não pode ser usado.');
        }
    }
}
