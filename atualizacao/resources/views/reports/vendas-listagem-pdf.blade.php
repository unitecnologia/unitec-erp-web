<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Listagem de Vendas</title>
    @include('reports.partials.vendas-listagem-document-styles')
    <style>
        @page { margin: 10mm; size: A4 landscape; }
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
    @include('reports.partials.vendas-listagem-document-body')
</body>
</html>
