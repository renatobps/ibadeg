@extends('pdf.layout')
@section('title','Histórico completo de notas')
@section('content')
@include('pdf.header')
<h1>Histórico completo de notas</h1>
<p class="muted">Relatório administrativo de todas as provas enviadas e suas revisões de nota.</p>
<p class="small">{{ $attempts->count() }} prova(s) enviada(s) · {{ $attempts->sum(fn($attempt)=>$attempt->grades->count()) }} revisão(ões) registrada(s)</p>
@forelse($attempts as $attempt)
    <h2>{{ $attempt->student->name }}</h2>
    <p class="small">Matrícula {{ str_pad($attempt->user_id,6,'0',STR_PAD_LEFT) }} · {{ $attempt->student->email }}</p>
    <h3>{{ sprintf('%02d',$attempt->subject->number) }} - {{ $attempt->subject->title }} · Tentativa {{ $attempt->number }}</h3>
    <p class="small muted">{{ $attempt->status_label }} · Enviada em {{ $attempt->submitted_at->format('d/m/Y H:i') }}</p>
    @foreach($attempt->grades->sortBy('revision') as $grade)
        @include('pdf.revision')
    @endforeach
@empty
    <p>Nenhuma prova enviada até o momento.</p>
@endforelse
@endsection
