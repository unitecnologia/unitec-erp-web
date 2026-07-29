<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }}</title>
    @include('reports.partials.nfce-relatorio-document-styles')
    <style>
        @page { margin: 8mm; size: A4 landscape; }
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
    @include('reports.partials.nfce-relatorio-document-body')
</body>
</html>
