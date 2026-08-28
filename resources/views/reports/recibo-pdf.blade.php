<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Recibo nº {{ $recibo->codigo }}</title>
    <style>
        @page { margin: 10mm; size: A4 portrait; }
        body { margin: 0; padding: 0; }
    </style>
    @include('reports.partials.recibo-document-styles')
</head>
<body>
    @include('reports.partials.recibo-document-body')
</body>
</html>
