<?php

use App\Models\User;
use Database\Seeders\DemoStudentsSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

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

Artisan::command('ibadeg:bootstrap', function (): int {
    if (! User::where('role', 'admin')->where('status', 'approved')->exists()) {
        $settings = config('ibadeg.admin');
        $validator = Validator::make($settings, [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            $this->error('Configure IBADEG_ADMIN_EMAIL e IBADEG_ADMIN_PASSWORD com uma senha de pelo menos 10 caracteres, letras e números.');

            return 1;
        }

        $email = mb_strtolower(trim($settings['email']));
        if (User::where('email', $email)->exists()) {
            $this->error('O e-mail do administrador já pertence a outra conta. Configure outro IBADEG_ADMIN_EMAIL.');

            return 1;
        }

        $administrator = new User;
        $administrator->forceFill([
            'name' => $settings['name'],
            'email' => $email,
            'password' => $settings['password'],
            'role' => 'admin',
            'status' => 'approved',
        ])->save();
        $this->info('Conta administrativa inicial criada.');
    }

    if (config('ibadeg.seed_demo')) {
        return $this->call('db:seed', [
            '--class' => DemoStudentsSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }

    $this->info('Preparação inicial concluída.');

    return 0;
})->purpose('Preparar o administrador inicial e os dados fictícios habilitados, preservando contas existentes');
