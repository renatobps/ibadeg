<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ibadeg:admin {email} {--name=Administrador IBADEG} {--password=}', function () {
    $email = mb_strtolower($this->argument('email'));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $this->error('Informe um e-mail válido.');
    }
    $password = $this->option('password') ?: $this->secret('Senha do administrador (mínimo 10 caracteres, letras e números)');
    if (strlen($password) < 10 || ! preg_match('/[a-zA-Z]/', $password) || ! preg_match('/[0-9]/', $password)) {
        return $this->error('Use pelo menos 10 caracteres com letras e números.');
    }
    $user = User::firstOrNew(['email' => $email]);
    $user->forceFill(['name' => $this->option('name'), 'password' => $password, 'role' => 'admin', 'status' => 'approved'])->save();
    $this->info('Administrador configurado: '.$email);
})->purpose('Criar ou recuperar uma conta administrativa do IBADEG');
