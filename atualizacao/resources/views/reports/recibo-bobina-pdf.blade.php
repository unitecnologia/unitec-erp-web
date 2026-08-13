<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Recibo Bobina nº {{ $recibo->codigo }}</title>
    @include('reports.partials.recibo-bobina-styles')
    <style>
        @page {
            margin: 2mm;
            size: 80mm auto;
        }

        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .recibo-bobina {
            padding: 0;
        }
    </style>
</head>
<body>
    @include('reports.partials.recibo-bobina-body')
</body>
</html>
