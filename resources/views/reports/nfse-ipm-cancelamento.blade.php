<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Cancelamento NFS-e {{ $ipm['numero'] ?? '' }}</title>
    @include('reports.partials.nfse-ipm-styles')
</head>
<body @if (! empty($tela)) class="ipm-tela" @endif>
@php
    $d = $ipm;
    $p = $d['prestador'];
    $c = $d['cancelamento'] ?? ['usuario' => '-', 'data_hora' => '-', 'motivo' => '-'];
@endphp

@if (! empty($d['homologacao']))
    <div class="ipm__aviso ipm__aviso--homolog">NFS-e EMITIDA EM HOMOLOGAÇÃO — SEM VALIDADE JURÍDICA</div>
@endif

@include('reports.partials.nfse-ipm-cabecalho', [
    'titulo' => 'Cancelamento NFS-e - Nº '.$d['numero'],
    'mostrarChaveNacional' => false,
])

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td style="width: 30%;">Usuário</td>
        <td>{{ $c['usuario'] }}</td>
    </tr>
    <tr>
        <td>Data/Hora</td>
        <td>{{ $c['data_hora'] }}</td>
    </tr>
    <tr>
        <td>Motivo</td>
        <td>{{ $c['motivo'] }}</td>
    </tr>
</table>

@unless (! empty($embedded))
    <div class="ipm__acoes">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>
@endunless

@if (! empty($autoPrint))
    <script>window.addEventListener('load', function () { window.print(); });</script>
@endif
</body>
</html>
