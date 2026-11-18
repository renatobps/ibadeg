@extends('layouts.app')
@section('title', 'Diploma e histórico')
@section('page_title', 'Diploma e histórico')
@section('content')
<div class="page-heading heading-row no-print">
    <div><span class="eyebrow">CONCLUSÃO DE CURSO</span><h1>Diploma e histórico</h1><p>{{ $certificate->snapshot['student_name'] }}</p></div>
    <div class="actions">
        <a class="btn" href="{{ route('certificate.pdf', $certificate) }}"><x-icon name="download" /> Baixar diploma e histórico</a>
        <a class="btn secondary" href="{{ route('certificate.pdf', [$certificate, 'inline' => 1]) }}" target="_blank" rel="noopener">Abrir para imprimir</a>
    </div>
</div>
<p class="muted">Duas páginas A4 em paisagem: diploma na frente e histórico no verso. Para imprimir frente e verso, selecione tamanho real (100%) e virar pela borda curta.</p>
<iframe src="{{ route('certificate.pdf', [$certificate, 'inline' => 1]) }}" title="Diploma e histórico de {{ $certificate->snapshot['student_name'] }}" style="width: 100%; height: 75vh; min-height: 500px; border: 1px solid #e4e5e1; border-radius: 8px"></iframe>
<p class="small muted">Registro {{ $certificate->uuid }} · Emitido em {{ $certificate->issued_at->format('d/m/Y') }} · <a href="{{ route('certificate.verify', $certificate) }}">Verificar autenticidade</a></p>
@endsection
