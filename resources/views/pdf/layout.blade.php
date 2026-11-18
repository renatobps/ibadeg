<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>@yield('title') - IBADEG</title>
<style>
    @page { margin: 32pt 35pt 42pt; }
    body { font-family: "DejaVu Sans", sans-serif; color: #2c343a; font-size: 9pt; line-height: 1.45; }
    h1 { color: #841d26; font-size: 20pt; line-height: 1.2; margin: 14pt 0 10pt; }
    h2 { color: #173b50; font-size: 13pt; margin: 18pt 0 9pt; page-break-after: avoid; }
    h3 { font-size: 10pt; margin: 14pt 0 6pt; page-break-after: avoid; }
    p { margin: 6pt 0; }
    .header { width: 100%; border-bottom: 2pt solid #841d26; margin-bottom: 12pt; }
    .header td { border: 0; padding: 0 0 9pt; background: none; }
    .header img { width: 92pt; height: 72pt; }
    .institution { color: #841d26; font-weight: bold; font-size: 11pt; text-align: right; }
    .institution small { display: block; color: #797b7c; font-size: 8pt; font-weight: normal; }
    .muted { color: #747a7d; }
    .small { font-size: 8pt; }
    .metadata { width: 100%; margin: 8pt 0 14pt; }
    .metadata td { border: 0; padding: 3pt 0; }
    .metadata .key { width: 22%; color: #747a7d; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th { color: #fff; background: #841d26; text-align: left; font-size: 8pt; padding: 6pt 7pt; }
    td { padding: 5pt 7pt; border-bottom: .5pt solid #e4e5e1; vertical-align: top; overflow-wrap: break-word; }
    .striped tbody tr:nth-child(even) { background: #f7f7f3; }
    .grade { font-weight: bold; color: #173b50; }
    .good { color: #327052; }
    .pending { color: #9b6925; }
    .break-before { page-break-before: always; }
    .reason { color: #60696e; padding: 3pt 0 8pt; margin-bottom: 8pt; border-bottom: .5pt solid #e4e5e1; overflow-wrap: break-word; }
    .footer { position: fixed; bottom: -26pt; left: 0; width: 76%; color: #888; font-size: 7pt; }
    .footer span { color: #841d26; }
    @yield('styles')
</style>
</head>
<body>
<div class="footer"><span>IBADEG</span> - Instituto Educacional Teológico do Gama · Gerado em {{ $generatedAt->format('d/m/Y H:i') }}</div>
@yield('content')
</body>
</html>
