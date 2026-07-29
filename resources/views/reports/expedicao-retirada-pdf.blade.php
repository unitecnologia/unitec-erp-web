<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Romaneio de Retirada</title>
    @include('reports.partials.pessoas-listagem-document-styles')
    <style>
        @page { margin: 10mm; size: A4 portrait; }
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
    @include('reports.partials.expedicao-retirada-document-body')
</body>
</html>
