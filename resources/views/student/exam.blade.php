@extends('layouts.app')
@section('title','Prova · '.$attempt->subject->title)
@section('page_title','Minha avaliação')
@section('content')
<a class="back-link" href="{{ route('subject.show',$attempt->subject) }}">← Voltar à disciplina</a><div class="page-heading"><span class="eyebrow">AVALIAÇÃO · TENTATIVA {{ $attempt->number }}</span><h1>{{ $attempt->subject->title }}</h1><p>Leia cada enunciado e responda a todos os itens. Cada item avaliado vale um ponto.</p></div>
<div class="exam-meta"><span><x-icon name="user" /> {{ $attempt->student_snapshot['name'] }}</span><a href="{{ route('subject.material',[$attempt->subject,'livro']) }}" target="_blank" rel="noopener"><x-icon name="book" /> Consultar apostila</a><span id="draft-status" role="status">Rascunho salvo ao preencher</span></div>
<form method="post" action="{{ route('attempt.submit',$attempt) }}" id="exam-form" data-draft-url="{{ route('attempt.draft',$attempt) }}">@csrf
@foreach(collect($attempt->question_snapshot)->groupBy('group_code') as $group)
<section class="card exam-question"><h2 class="question-instruction">{{ $group->first()['instruction'] }}</h2>
@if($group->first()['kind']==='match')<div class="match-key">@foreach($group->first()['options'] as $o)<span><b>{{ $o['key'] }})</b> {{ $o['text'] }}</span>@endforeach</div>@endif
@foreach($group as $q)@php $key=$q['code'];$value=old('answers.'.$key,$attempt->draft_answers[$key]??''); @endphp
@if(!$q['scorable'])<p class="muted small">{{ $q['prompt'] ?: 'Esta afirmação já está completa no material original e não exige resposta.' }}</p>
@elseif($q['kind']==='ce')<div class="ce-row"><span class="item-label">{{ $q['label'] }})</span><div class="item-copy">{{ $q['prompt'] }} @error('answers.'.$key)<small class="field-error">{{ $message }}</small>@enderror</div><div class="ce-options" role="group" aria-label="{{ $q['label'].') '.$q['prompt'] }}">@foreach($q['options'] as $o)<label><input type="radio" name="answers[{{ $key }}]" value="{{ $o['key'] }}" @checked($value===$o['key']) required><span>{{ $o['text'] }}</span></label>@endforeach</div></div>
@elseif($q['kind']==='single')<div class="choices">@foreach($q['options'] as $o)<label><input type="radio" name="answers[{{ $key }}]" value="{{ $o['key'] }}" @checked($value===$o['key']) required><span><b>{{ $o['key'] }})</b> {{ $o['text'] }}</span></label>@endforeach</div>
@elseif($q['kind']==='match')<label class="match-row"><select name="answers[{{ $key }}]" class="input match-select" required aria-label="Associar: {{ $q['prompt'] }}"><option value="">—</option>@foreach($q['options'] as $o)<option value="{{ $o['key'] }}" @selected((string)$value===(string)$o['key'])>{{ $o['key'] }}</option>@endforeach</select><span>{{ $q['prompt'] }}</span></label>
@else<div class="field open-question"><label for="answer-{{ $key }}">{{ $q['label']?$q['label'].') ':'' }}{{ $q['prompt']?:'Sua resposta' }}</label><textarea id="answer-{{ $key }}" name="answers[{{ $key }}]" class="input" rows="3" maxlength="4000" required>{{ $value }}</textarea><small class="muted">Esta resposta será corrigida pela administração.</small></div>@endif
@endforeach</section>@endforeach
<div class="exam-submit"><div><strong>Revise antes de enviar.</strong><p>Após o envio, as respostas desta tentativa não poderão ser alteradas.</p></div><button class="btn" id="submit-exam">Enviar prova <x-icon name="check" /></button></div></form>
@endsection
