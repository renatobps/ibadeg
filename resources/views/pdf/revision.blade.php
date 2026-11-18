<table class="striped"><thead><tr><th style="width: 12%">Revisão</th><th style="width: 24%">Data</th><th style="width: 27%">Responsável</th><th style="width: 19%">Tipo</th><th>Nota / 10</th></tr></thead><tbody>
<tr><td>{{ $grade->revision }}</td><td>{{ $grade->created_at->format('d/m/Y H:i') }}</td><td>{{ $grade->reviewer?->name ?? 'Correção automática' }}</td><td class="{{ $grade->kind==='final'?'good':'pending' }}">{{ $grade->kind==='final'?'Final':'Provisória' }}</td><td class="grade">{{ number_format($grade->grade,2,',','.') }}</td></tr>
</tbody></table>
<p class="reason small"><strong>Motivo:</strong> {{ $grade->reason }}</p>
