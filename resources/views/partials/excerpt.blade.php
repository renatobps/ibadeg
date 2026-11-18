@if($q['excerpt'])
<details class="excerpt">
    <summary>Verificar no livro</summary>
    <blockquote>{{ $q['excerpt']['text'] }}</blockquote>
    <div class="small muted">{{ $q['excerpt']['book'] }} · página {{ $q['excerpt']['page'] }}
        @if($q['excerpt']['ocr'])
            · Texto extraído por OCR
        @endif
    </div>
    <a href="{{ route('subject.material',[$attempt->subject,'livro']) }}#page={{ $q['excerpt']['page'] }}" target="_blank" rel="noopener">Abrir apostila nesta página ↗</a>
</details>
@endif
