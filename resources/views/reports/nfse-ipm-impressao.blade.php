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

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td rowspan="2" class="ipm__prestador" style="width: 49%;">
            <div class="ipm__forte">{{ $p['nome'] }}</div>
            <div><b>CNPJ:</b> {{ $p['documento'] }}</div>
            <div>{{ $p['endereco'] }}</div>
            <div>{{ $p['cep_bairro'] }}</div>
            <div>Município: {{ $p['municipio'] }}</div>
            <div><b>Insc. Municipal:</b> {{ $p['im'] !== '' ? $p['im'] : 'Não informado' }} - <b>Insc. Estadual:</b> {{ $p['ie'] }}</div>
            <div>Telefone: {{ $p['telefone'] }} - Celular: {{ $p['celular'] }}</div>
        </td>
        <td class="ipm__centro ipm__meio" style="width: 17%;">
            Número da NFS-e<br>
            <b>{{ $d['numero'] }}</b>
        </td>
        <td class="ipm__centro ipm__meio" style="width: 17%;">
            Situação<br>
            <b>{{ $d['situacao'] }}</b>
        </td>
        <td rowspan="2" class="ipm__qr" style="width: 17%;">
            @if (! empty($d['qr_data_uri']))
                <img src="{{ $d['qr_data_uri'] }}" alt="QR Code">
            @endif
            @if (! empty($d['consulta_url']))
                <a href="{{ $d['consulta_url'] }}">Autenticidade</a>
            @else
                <span>Autenticidade</span>
            @endif
        </td>
    </tr>
    <tr>
        <td></td>
        <td class="ipm__centro ipm__meio">
            Tipo<br>
            <b>{{ $d['tipo'] }}</b>
        </td>
    </tr>
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="2" class="ipm__titulo">Nota Fiscal de Serviço Eletrônica - Série {{ $d['serie'] }}</td>
    </tr>
    <tr>
        <td rowspan="2" class="ipm__pref" style="width: 55%;">
            <table class="ipm__pref-tabela" cellspacing="0" cellpadding="0">
                <tr>
                    @if (! empty($p['brasao']))
                        <td class="ipm__brasao"><img src="{{ $p['brasao'] }}" alt="Brasão"></td>
                    @endif
                    <td>
                        @if ($p['estado'] !== '')
                            <div class="ipm__forte">ESTADO DE {{ $p['estado'] }}</div>
                        @endif
                        @if ($p['prefeitura'] !== '')
                            <div class="ipm__forte">PREFEITURA MUNICIPAL DE {{ $p['prefeitura'] }}</div>
                        @endif
                        @if (($p['secretaria'] ?? '') !== '')
                            <div>{{ $p['secretaria'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td class="ipm__centro ipm__ident">
            <div><b>Identificador</b></div>
            <div>{{ $d['identificador'] }}</div>
            @if (! empty($d['codigo_barras']))
                <div class="ipm__barras"><img src="{{ $d['codigo_barras'] }}" alt="Código de barras"></div>
            @endif
            <div><b>Chave de Acesso NFS-e Nacional</b></div>
            <div>{{ $d['chave_acesso'] }}</div>
        </td>
    </tr>
    <tr>
        <td class="ipm__sem-pad">
            <table class="ipm ipm--interna" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="ipm__centro" style="width: 50%;">Data Fato Gerador<br><b>{{ $d['fato_gerador'] }}</b></td>
                    <td class="ipm__centro" style="width: 50%;">Data/Hora Emissão<br><b>{{ $d['emissao'] }}</b></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

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
