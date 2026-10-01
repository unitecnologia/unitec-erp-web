<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ ! empty($tecnica) ? 'OS Técnica' : 'OS' }} {{ $numero }}</title>
    @include('reports.partials.ordem-servico-document-styles')
</head>
<body style="margin:0;padding:0;">
    @include('reports.partials.ordem-servico-document-body')
</body>
</html>
