<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>NFS-e {{ $ipm['numero'] ?? '' }}</title>
    @include('reports.partials.nfse-ipm-styles')
</head>
<body @if (! empty($tela)) class="ipm-tela" @endif>
@php
    $d = $ipm;
    $p = $d['prestador'];
    $t = $d['tomador'];
@endphp

@if (! empty($d['cancelada']))
    <div class="ipm__marca">CANCELADA</div>
@elseif (! empty($d['substituida']))
    <div class="ipm__marca">SUBSTITUÍDA</div>
@endif

@if (! empty($espelho) || ! empty($d['espelho']))
    <div class="ipm__aviso ipm__aviso--espelho">ESPELHO DA NFS-e — SEM VALIDADE FISCAL</div>
@elseif (! empty($d['homologacao']))
    <div class="ipm__aviso ipm__aviso--homolog">NFS-e EMITIDA EM HOMOLOGAÇÃO — SEM VALIDADE JURÍDICA</div>
@endif

@include('reports.partials.nfse-ipm-cabecalho', [
    'titulo' => 'Nota Fiscal de Serviço Eletrônica - Série '.$d['serie'],
])

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="3" class="ipm__sec">TOMADOR DO SERVIÇO</td>
    </tr>
    <tr>
        <td colspan="2">Nome/Razão Social<br>{{ $t['nome'] }}</td>
        <td style="width: 40%;">CPF/CNPJ<br>{{ $t['documento'] }}</td>
    </tr>
    <tr>
        <td style="width: 40%;">Endereço<br>{{ $t['endereco'] }}</td>
        <td style="width: 20%;">Número<br>{{ $t['numero'] }}</td>
        <td>Complemento<br>{{ $t['complemento'] }}</td>
    </tr>
    <tr>
        <td>Bairro<br>{{ $t['bairro'] }}</td>
        <td>CEP<br>{{ $t['cep'] }}</td>
        <td>Cidade<br>{{ $t['cidade'] }}</td>
    </tr>
    <tr>
        <td>País<br>{{ $t['pais'] }}</td>
        <td>Telefone<br>{{ $t['telefone'] }}</td>
        <td>Email<br>{{ $t['email'] }}</td>
    </tr>
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="7" class="ipm__sec">DESCRIÇÃO DOS SERVIÇOS PRESTADOS</td>
    </tr>
    <tr>
        @foreach ([
            'Serviço' => $d['servico_codigo'],
            'Local Prestação' => $d['local_codigo'],
            'Alíquota' => $d['aliquota'],
            'Valor Serviço' => $d['valor_servico'],
            'Desc. Incondic.' => $d['desconto_incondicional'],
            'Valor Dedução' => $d['deducao'],
            'Valor ISS' => $d['valor_iss'],
        ] as $rotulo => $valor)
            <td class="ipm__centro ipm__cab">
                <b>{{ $rotulo }}</b><br>
                @if ($loop->first)
                    <b>{{ $valor }}</b>
                @else
                    {{ $valor }}
                @endif
            </td>
        @endforeach
    </tr>
    <tr>
        <td colspan="7"><b>Natureza da Operação:</b> {{ $d['natureza'] }}</td>
    </tr>
    <tr>
        <td colspan="7"><b>NBS:</b> {{ $d['nbs'] }}</td>
    </tr>
    <tr>
        <td colspan="7"><b>Descrição do Serviço:</b><br>{!! nl2br(e($d['descricao'])) !!}</td>
    </tr>
    @if (! empty($d['pagamentos']))
        <tr>
            <td colspan="7"><b>Forma de Pagamento:</b><br>{!! implode('<br>', array_map('e', $d['pagamentos'])) !!}</td>
        </tr>
    @endif
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    @foreach (array_chunk([
        'Valor Total' => $d['valor_total'],
        'Desc. Incondicional' => $d['desconto_incondicional'],
        'Dedução' => $d['deducao'],
        'Base de Cálculo' => $d['base_calculo'],
        'ISSQN' => $d['issqn'],
        'ISSRF' => $d['issrf'],
        'IR' => $d['ir'],
        'INSS' => $d['inss'],
        'CSLL' => $d['csll'],
        'COFINS' => $d['cofins'],
        'PIS' => $d['pis'],
        'Outras Retenções' => $d['outras_retencoes'],
        'Total Trib. Federais' => $d['total_federais'],
        'Desc. Condicional' => $d['desconto_condicional'],
        'Valor Líquido' => $d['valor_liquido'],
    ], 5, true) as $linha)
        <tr>
            @foreach ($linha as $rotulo => $valor)
                <td class="ipm__centro ipm__cab" style="width: 20%;"><b>{{ $rotulo }}</b><br>{{ $valor }}</td>
            @endforeach
        </tr>
    @endforeach
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    @if (($d['cei'] ?? '') !== '')
        <tr>
            <td>Cadastro Específico do INSS (CEI): {{ $d['cei'] }}</td>
        </tr>
    @endif
    <tr>
        <td>Descrição dos subitens da Lista de Serviço em acordo com a Lei Complementar 116/03.</td>
    </tr>
    <tr>
        <td>{{ $d['lc116'] }}</td>
    </tr>
    @if (filled($d['atividade_municipal'] ?? null))
        <tr>
            <td>{{ $d['atividade_municipal'] }}</td>
        </tr>
    @endif
    <tr>
        <td>Legenda do Local de Prestação do Serviço</td>
    </tr>
    <tr>
        <td>{{ $d['legenda_local'] }}</td>
    </tr>
    <tr>
        <td>Outras Informações</td>
    </tr>
    @if (($d['tributacao'] ?? '') !== '')
        <tr>
            <td>{{ $d['tributacao'] }}</td>
        </tr>
    @endif
    <tr>
        <td>
            @foreach (explode("\n\n", (string) $d['outras']) as $paragrafo)
                <p class="ipm__par">{!! nl2br(e($paragrafo)) !!}</p>
            @endforeach
        </td>
    </tr>
</table>

@if (($d['responsavel'] ?? '') !== '')
    <div class="ipm__rodape">Responsável pela Emissão: {{ $d['responsavel'] }}</div>
@endif

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
