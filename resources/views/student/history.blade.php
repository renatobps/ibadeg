@extends('layouts.app')
@section('title','Histórico acadêmico')
@section('page_title','Histórico e notas')
@section('content')
<div class="page-heading heading-row"><div><span class="eyebrow">SUA CAMINHADA REGISTRADA</span><h1>Histórico acadêmico</h1><p>{{ $student->name }} · {{ $course->name }}</p></div><div class="actions"><a class="btn" href="{{ route('history.pdf') }}"><x-icon name="download" /> Baixar histórico PDF</a><button class="btn secondary" data-print><x-icon name="download" /> Imprimir histórico</button></div></div>
@include('partials.transcript')
<section class="card certificate-panel" id="certificado"><span class="round-icon"><x-icon name="award" /></span><div><span class="eyebrow">A CONCLUSÃO DA SUA JORNADA</span><h2>Seu certificado</h2>@if($certificate=$student->certificates()->where('course_id',$course->id)->first())<p>Seu certificado foi emitido em {{ $certificate->issued_at->format('d/m/Y') }}.</p><a class="btn" href="{{ route('certificate.show',$certificate) }}">Ver certificado <x-icon name="arrow" /></a> <a class="btn secondary" href="{{ route('certificate.pdf',$certificate) }}"><x-icon name="download" /> Baixar certificado PDF</a>@else<p>É necessário ter notas finais aprovadas nas {{ count($rows) }} disciplinas. Após a conferência do histórico, a administração poderá emitir o certificado.</p><span class="pill neutral">{{ collect($rows)->where('passed',true)->count() }} de {{ count($rows) }} disciplinas aprovadas</span>@endif</div></section>
@endsection
