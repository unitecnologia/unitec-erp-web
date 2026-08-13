<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Listagem de Ajustes de Estoque</title>
    @include('reports.partials.pessoas-listagem-document-styles')
    <style>
        @page { margin: 10mm; size: A4 portrait; }
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
    @include('reports.partials.ajustes-estoque-listagem-document-body')
</body>
</html>
