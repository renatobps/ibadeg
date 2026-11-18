@extends('layouts.app')
@section('title','Validação de certificado')
@section('content')
<section class="verification card"><span class="round-icon"><x-icon name="award" /></span><span class="eyebrow">CONFERÊNCIA DE AUTENTICIDADE</span><h1>Certificado registrado</h1><p>Este documento consta nos registros do IBADEG.</p><dl class="info-list"><dt>Aluno</dt><dd>{{ $certificate->snapshot['student_name'] }}</dd><dt>Curso</dt><dd>{{ $certificate->snapshot['course_name'] }}</dd><dt>Emitido em</dt><dd>{{ $certificate->issued_at->format('d/m/Y') }}</dd><dt>Identificador</dt><dd class="break-word">{{ $certificate->uuid }}</dd></dl></section>
@endsection
