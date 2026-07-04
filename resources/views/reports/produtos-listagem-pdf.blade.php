<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Listagem de Produtos</title>
    @include('reports.partials.produtos-listagem-document-styles')
    <style>
        @page {
            margin: 10mm;
            size: A4 landscape;
        }

        body {
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    @include('reports.partials.produtos-listagem-document-body')
</body>
</html>
