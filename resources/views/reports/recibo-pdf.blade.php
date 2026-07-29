<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Recibo nº {{ $recibo->codigo }}</title>
    @include('reports.partials.recibo-document-styles')
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .recibo-doc {
            border: none;
            border-radius: 0;
        }
    </style>
</head>
<body>
    @include('reports.partials.recibo-document-body')
</body>
</html>
