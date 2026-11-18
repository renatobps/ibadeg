<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#841d26"><title>@yield('title', 'Portal do aluno') · IBADEG</title><link rel="icon" href="{{ asset('assets/logo-ibadeg.png') }}"><link rel="stylesheet" href="{{ asset('assets/app.css') }}"><script src="{{ asset('assets/app.js') }}" defer></script></head>
<body class="{{ auth()->check() ? 'app-body' : 'public-body' }}">
<a class="skip-link" href="#main">Ir para o conteúdo</a>
@auth
<button class="mobile-menu icon-button" aria-label="Abrir menu" aria-expanded="false" data-menu><x-icon name="menu" /></button>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="{{ route('dashboard') }}"><img src="{{ asset('assets/logo-ibadeg.png') }}" alt="IBADEG — Instituto Educacional Teológico do Gama"></a>
    <p class="nav-label">{{ auth()->user()->isAdmin() ? 'GESTÃO ACADÊMICA' : 'PORTAL DO ALUNO' }}</p>
    <nav aria-label="Principal">
    @if(auth()->user()->isAdmin())
        <a @class(['nav-link','active'=>request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}"><x-icon name="home" /> Visão geral</a>
        <a @class(['nav-link','active'=>request()->routeIs('admin.student*')]) href="{{ route('admin.students') }}"><x-icon name="users" /> Alunos e matrículas</a>
        <a @class(['nav-link','active'=>request()->routeIs('admin.attempt*')]) href="{{ route('admin.attempts') }}"><x-icon name="clipboard" /> Provas e correções</a>
        <a @class(['nav-link','active'=>request()->routeIs('admin.settings*')]) href="{{ route('admin.settings') }}"><x-icon name="settings" /> Configurações</a>
    @else
        <a @class(['nav-link','active'=>request()->routeIs('dashboard','subject.*','attempt.*')]) href="{{ route('dashboard') }}"><x-icon name="book" /> Meu curso</a>
        <a @class(['nav-link','active'=>request()->routeIs('history')]) href="{{ route('history') }}"><x-icon name="chart" /> Histórico e notas</a>
        <a class="nav-link" href="{{ route('history') }}#certificado"><x-icon name="award" /> Meu certificado</a>
    @endif
        <a @class(['nav-link','active'=>request()->routeIs('account*')]) href="{{ route('account') }}"><x-icon name="user" /> Minha conta</a>
    </nav>
    <div class="sidebar-bottom"><div class="verse">“Crescei na graça e no conhecimento.”<small>2 Pedro 3:18</small></div><form method="post" action="{{ route('logout') }}">@csrf<button class="nav-link logout"><x-icon name="exit" /> Sair da conta</button></form><small class="muted">IBADEG · Formação para servir</small></div>
</aside>
<div class="app-main"><header class="topbar"><span class="breadcrumb">IBADEG <span>/</span> @yield('page_title','Meu curso')</span><a class="profile" href="{{ route('account') }}"><span class="avatar">{{ auth()->user()->initials }}</span><span>{{ str(auth()->user()->name)->before(' ') }}<small>{{ auth()->user()->isAdmin() ? 'Administração' : 'Aluno' }}</small></span></a></header>
<main id="main" class="content">@include('partials.alerts')@yield('content')</main><footer class="app-footer">Instituto Educacional Teológico do Gama <span>IBADEG</span></footer></div>
@else
<header class="public-nav"><a class="public-brand" href="{{ route('home') }}"><img src="{{ asset('assets/logo-ibadeg.png') }}" alt="IBADEG"></a><nav><a class="subtle-link" href="{{ route('home') }}#curso">O curso</a><a class="subtle-link" href="{{ route('login') }}">Área do aluno</a><a class="btn small" href="{{ route('register') }}">Quero me matricular <x-icon name="arrow" /></a></nav></header>
<main id="main">@include('partials.alerts')@yield('content')</main><footer class="public-footer"><strong>IBADEG</strong><span>Instituto Educacional Teológico do Gama</span><span>Conhecimento que transforma. Fé que serve.</span></footer>
@endauth
</body></html>
