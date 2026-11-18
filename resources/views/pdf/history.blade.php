@extends('pdf.layout')
@section('title','Histórico acadêmico')
@section('styles')
    .history-table td { font-size: 8pt; line-height: 1.25; padding: 3pt 6pt; }
    .history-details h2 { font-size: 11pt; margin: 12pt 0 6pt; }
    .history-details .reason { margin-bottom: 5pt; padding-bottom: 5pt; }
@endsection
@section('content')
@include('pdf.header')
<h1>Histórico acadêmico</h1>
<table class="metadata">
    <tr><td class="key">Aluno</td><td><strong>{{ $student->name }}</strong></td></tr>
    <tr><td class="key">Matrícula</td><td>{{ str_pad($student->id,6,'0',STR_PAD_LEFT) }}</td></tr>
    <tr><td class="key">E-mail</td><td>{{ $student->email }}</td></tr>
    <tr><td class="key">Curso</td><td>{{ $course->name }}</td></tr>
    <tr><td class="key">Nota mínima</td><td>{{ number_format($course->passing_grade,2,',','.') }} / 10</td></tr>
</table>
<table class="striped history-table"><thead><tr><th style="width: 47%">Disciplina</th><th style="width: 12%">Tentativas</th><th style="width: 16%">Melhor nota final</th><th>Situação</th></tr></thead><tbody>
@foreach($rows as $row)
<tr><td>{{ sprintf('%02d',$row['subject']->number) }} - {{ $row['subject']->title }}</td><td>{{ $row['attempts']->count() }}</td><td class="grade">{{ $row['best']?number_format($row['best']->score,2,',','.'):'-' }}</td><td class="{{ $row['passed']?'good':'pending' }}">{{ $row['passed']?'Aprovado':($row['attempts']->isEmpty()?'Não iniciado':'Em andamento') }}</td></tr>
@endforeach
</tbody></table>
<p class="small muted">A maior nota final de cada disciplina compõe o histórico. Pontuações provisórias aguardam correção e não são usadas para aprovação.</p>
@if(collect($rows)->contains(fn($row)=>$row['attempts']->isNotEmpty()))
<div class="break-before"></div>
<div class="history-details">
<h1>Tentativas e revisões de notas</h1>
<p class="small muted">Todas as tentativas e correções registradas para {{ $student->name }}.</p>
@foreach($rows as $row)
    @foreach($row['attempts']->sortBy('number') as $attempt)
    <h2>{{ sprintf('%02d',$row['subject']->number) }} - {{ $row['subject']->title }} · Tentativa {{ $attempt->number }}</h2>
    <p class="small muted">{{ $attempt->status_label }} · Iniciada em {{ $attempt->started_at->format('d/m/Y H:i') }}@if($attempt->submitted_at) · Enviada em {{ $attempt->submitted_at->format('d/m/Y H:i') }}@endif</p>
    @forelse($attempt->grades->sortBy('revision') as $grade)
        @include('pdf.revision')
    @empty
        <p class="small muted">Prova ainda não enviada. Sem nota registrada.</p>
    @endforelse
    @endforeach
@endforeach
</div>
@endif
@endsection
