@php
    $snapshot = $certificate->snapshot;
    $student = $certificate->student;
    $birthDate = $snapshot['student_birth_date'] ?? $student?->birth_date?->toDateString();
    $birthDate = $birthDate ? \Illuminate\Support\Carbon::parse($birthDate)->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y') : '________________';
    $document = $snapshot['student_document'] ?? $student?->document ?? '________________';
    $nationality = $snapshot['student_nationality'] ?? $student?->nationality ?? 'Brasileiro (a)';
    $courseTitle = 'Curso '.mb_strtoupper(preg_replace('/^Curso\s+/iu', '', $snapshot['course_name']));
    $nameSize = min(54.96, 2100 / max(1, mb_strlen($snapshot['student_name'])));
    $assets = str_replace('\\', '/', public_path('assets'));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Diploma e histórico - {{ $snapshot['student_name'] }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        @font-face { font-family: 'Diploma Arial'; src: url('file://{{ $assets }}/diploma-arial.ttf') format('truetype'); }
        @font-face { font-family: 'Diploma Edwardian'; src: url('file://{{ $assets }}/diploma-edwardian.ttf') format('truetype'); }
        @font-face { font-family: 'Diploma Times'; src: url('file://{{ $assets }}/diploma-times.ttf') format('truetype'); }
        @font-face { font-family: 'Diploma Calibri'; src: url('file://{{ $assets }}/diploma-calibri-bold.ttf') format('truetype'); font-weight: bold; }
        html, body { margin: 0; padding: 0; color: #000; font-family: 'Diploma Arial'; }
        .sheet { position: relative; width: 841.89pt; height: 595.27pt; overflow: hidden; }
        .reverse { page-break-before: always; }
        .artwork { position: absolute; left: 0; top: 0; width: 841.89pt; height: 595.28pt; }
        .field { position: absolute; margin: 0; padding: 0; font-size: 14.04pt; line-height: 16.07pt; white-space: nowrap; }
        .student-name { left: 47.08pt; top: 242.95pt; width: 744pt; height: 83pt; font-family: 'Diploma Edwardian'; font-size: {{ $nameSize }}pt; line-height: 80pt; text-align: center; color: #ff0000; }
        .grade, .hours { top: 0; font-family: 'Diploma Times'; font-size: 11.04pt; line-height: 13.92pt; text-align: center; }
        .grade { left: 331.2pt; width: 99.6pt; }
        .hours { left: 593.4pt; width: 169.9pt; }
        .observation { left: 85.08pt; font-family: 'Diploma Times'; font-size: 9.96pt; line-height: 11.52pt; }
        .registration { left: 542pt; top: 533.48pt; width: 232pt; color: #d00000; font-family: 'Diploma Calibri'; font-weight: bold; font-size: 9.96pt; text-align: center; }
    </style>
</head>
<body>
<div class="sheet front" aria-label="Diploma - frente">
    <img class="artwork" src="{{ $assets }}/diploma-template-1.png" alt="Arte original do diploma IBADEG">
    <div class="field" style="left: 96.84pt; top: 225.89pt">Diretor do Instituto Educacional e Teológico do Gama, no uso de suas atribuições legais e tendo em vista a</div>
    <div class="field" style="left: 85.07pt; top: 241.96pt">conclusão do {{ $courseTitle }}, em {{ $certificate->issued_at->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y') }}, confere a:</div>
    <div class="field student-name">{{ $snapshot['student_name'] }}</div>
    <div class="field" style="left: 93.6pt; top: 370.7pt; font-size: {{ mb_strlen($document) > 20 ? '12' : '14.04' }}pt">{{ $nationality }}, nascido (a) em {{ $birthDate }} portador (a) da Identificação Nº {{ $document }} CIN/CPF</div>
    <div class="field" style="left: 78pt; top: 386.77pt">o presente diploma, a fim de que possa gozar de todos os direitos e prerrogativas legais.</div>
</div>
<div class="sheet reverse" aria-label="Histórico - verso" title="{{ $snapshot['course_name'] }}">
    <img class="artwork" src="{{ $assets }}/diploma-template-2.png" alt="Histórico de conclusão original, com as 21 disciplinas do curso">
    @foreach($snapshot['subjects'] as $subject)
        @php
            $rowTop = 133.42 + ($subject['number'] - 1) * 13.92 + ($subject['number'] >= 15 ? 0.12 : 0);
            $hours = $subject['workload_hours'] ?? ((int) $snapshot['workload_hours'] === 1680 && count($snapshot['subjects']) === 21 ? 80 : null);
        @endphp
        <div class="field grade" style="top: {{ $rowTop }}pt" title="{{ $subject['title'] }}">{{ number_format($subject['grade'], 2, ',', '.') }}</div>
        <div class="field hours" style="top: {{ $rowTop }}pt">{{ $hours !== null ? $hours.' Horas' : '—' }}</div>
    @endforeach
    <div class="field observation" style="top: 444.45pt">A carga horária total do curso é de {{ number_format($snapshot['workload_hours'], 0, ',', '.') }} horas</div>
    <div class="field observation" style="top: 456.09pt">Para aprovação é necessário que o aluno obtenha nota máxima de 10 ou mínima de {{ str_pad(number_format($snapshot['passing_grade'], 0, ',', '.'), 2, '0', STR_PAD_LEFT) }}.</div>
    <div class="field registration">IBADEG, em {{ $certificate->issued_at->format('d/m/Y') }}</div>
</div>
</body>
</html>
