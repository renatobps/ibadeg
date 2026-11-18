<?php

use App\Http\Controllers\AcademicPdfController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Models\Certificate;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('home'))->name('home');
Route::get('/validar/{certificate:uuid}', fn (Certificate $certificate) => view('certificate.verify', compact('certificate')))->name('certificate.verify')->middleware('throttle:30,1');
Route::middleware('guest')->group(function () {
    Route::view('/entrar', 'auth.login')->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
    Route::get('/matricula', [AuthController::class, 'register'])->name('register');
    Route::post('/matricula', [AuthController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
});
Route::middleware('auth')->group(function () {
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');
    Route::get('/painel', [StudentController::class, 'dashboard'])->name('dashboard');
    Route::view('/minha-conta', 'auth.account')->name('account');
    Route::put('/minha-conta/senha', [AuthController::class, 'password'])->middleware('throttle:6,1')->name('account.password');
    Route::get('/historico/pdf', [AcademicPdfController::class, 'history'])->name('history.pdf');
    Route::get('/historico', [StudentController::class, 'history'])->name('history');
    Route::get('/disciplinas/{subject}', [StudentController::class, 'subject'])->name('subject.show');
    Route::get('/tentativas/{attempt}', [StudentController::class, 'attempt'])->name('attempt.show');
    Route::get('/certificados/{certificate:uuid}/pdf', [AcademicPdfController::class, 'certificate'])->name('certificate.pdf');
    Route::get('/certificados/{certificate:uuid}', [StudentController::class, 'certificate'])->name('certificate.show');
    Route::middleware('approved')->group(function () {
        Route::get('/disciplinas/{subject}/material/{kind}', [StudentController::class, 'material'])->name('subject.material');
        Route::post('/disciplinas/{subject}/iniciar', [StudentController::class, 'start'])->middleware('throttle:20,1')->name('subject.start');
        Route::put('/tentativas/{attempt}/rascunho', [StudentController::class, 'draft'])->middleware('throttle:120,1')->name('attempt.draft');
        Route::post('/tentativas/{attempt}/enviar', [StudentController::class, 'submit'])->middleware('throttle:10,1')->name('attempt.submit');
    });
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/alunos', [AdminController::class, 'students'])->name('students');
        Route::get('/alunos/{student}/historico/pdf', [AcademicPdfController::class, 'studentHistory'])->name('student.history.pdf');
        Route::get('/alunos/{student}', [AdminController::class, 'student'])->name('student');
        Route::put('/alunos/{student}', [AdminController::class, 'updateStudent'])->name('student.update');
        Route::post('/alunos/{student}/certificado', [AdminController::class, 'certificate'])->name('certificate.issue');
        Route::get('/provas', [AdminController::class, 'attempts'])->name('attempts');
        Route::get('/provas/{attempt}', [AdminController::class, 'attempt'])->name('attempt');
        Route::post('/provas/{attempt}/corrigir', [AdminController::class, 'review'])->name('attempt.review');
        Route::get('/configuracoes', [AdminController::class, 'settings'])->name('settings');
        Route::put('/configuracoes', [AdminController::class, 'saveSettings'])->name('settings.save');
        Route::get('/exportar-historico/pdf', [AcademicPdfController::class, 'completeHistory'])->name('export.pdf');
        Route::get('/exportar-historico', [AdminController::class, 'export'])->name('export');
    });
});
