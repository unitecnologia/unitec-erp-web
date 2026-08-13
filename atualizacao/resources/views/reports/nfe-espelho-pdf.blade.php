<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Espelho — NF-e {{ $numeroNota }}</title>
    @include('reports.partials.compra-danfe-styles')
    <style>
        .nfe-espelho-banner {
            margin-bottom: 4px;
            padding: 6px 8px;
            border: 2px solid #b91c1c;
            background: #fee2e2;
            color: #7f1d1d;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            text-align: center;
        }

        .nfe-espelho-banner strong {
            display: block;
            font-size: 11px;
            margin-bottom: 2px;
        }
    </style>
</head>
<body style="margin:0;padding:0;">
    @include('reports.partials.nfe-espelho-banner')
    @include('reports.partials.compra-danfe-body')
</body>
</html>
