<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>NFS-e {{ $ipm['numero'] ?? '' }}</title>
    @include('reports.partials.nfse-ipm-styles')
</head>
<body>
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
        <td rowspan="2" style="width: 62%;">
            <div class="ipm__razao">{{ $p['nome'] }}</div>
            <div class="ipm__linha">CNPJ: {{ $p['documento'] }}</div>
            <div class="ipm__linha">{{ $p['endereco'] }}</div>
            <div class="ipm__linha">{{ $p['cep_bairro'] }}</div>
            <div class="ipm__linha">Município: {{ $p['municipio'] }}</div>
            <div class="ipm__linha">Insc. Municipal: {{ $p['im'] !== '' ? $p['im'] : 'Não informado' }} - Insc. Estadual: {{ $p['ie'] }}</div>
            <div class="ipm__linha">Email: {{ $p['email'] }} &nbsp; Telefone: {{ $p['telefone'] }} &nbsp; Celular: {{ $p['celular'] }}</div>
        </td>
        <td class="ipm__centro" style="width: 13%;">
            <span class="ipm__lbl">Número da NFS-e</span>
            <span class="ipm__num">{{ $d['numero'] }}</span>
        </td>
        <td class="ipm__centro" style="width: 12%;">
            <span class="ipm__lbl">Situação</span>
            <span class="ipm__sit">{{ $d['situacao'] }}</span>
        </td>
        <td class="ipm__qr" rowspan="2" style="width: 13%;">
            <span class="ipm__lbl">Autenticidade</span>
            @if (! empty($d['qr_data_uri']))
                <img src="{{ $d['qr_data_uri'] }}" alt="QR Code">
            @else
                <span class="ipm__val ipm__centro">-</span>
            @endif
        </td>
    </tr>
    <tr>
        <td colspan="2" class="ipm__centro">
            <span class="ipm__lbl">Tipo</span>
            <span class="ipm__sit">{{ $d['tipo'] }}</span>
        </td>
    </tr>
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td class="ipm__pref">
            <div class="ipm__pref-titulo">Nota Fiscal de Serviço Eletrônica - Série NFS-e</div>
            @if ($p['estado'] !== '')
                <div class="ipm__pref-linha">Estado de {{ $p['estado'] }}</div>
            @endif
            @if ($p['prefeitura'] !== '')
                <div class="ipm__pref-linha">Prefeitura Municipal de {{ $p['prefeitura'] }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td>
            <span class="ipm__lbl">Identificador</span>
            <span class="ipm__id">{{ $d['identificador'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="ipm__lbl">Chave de acesso da NFS-e</span>
            <span class="ipm__chave">{{ $d['chave_acesso'] }}</span>
        </td>
    </tr>
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td style="width: 50%;">
            <span class="ipm__lbl">Data do fato gerador</span>
            <span class="ipm__val">{{ $d['fato_gerador'] }}</span>
        </td>
        <td style="width: 50%;">
            <span class="ipm__lbl">Data/Hora de emissão</span>
            <span class="ipm__val">{{ $d['emissao'] }}</span>
        </td>
    </tr>
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="4" class="ipm__sec">Tomador do Serviço</td>
    </tr>
    <tr>
        <td colspan="3">
            <span class="ipm__lbl">Nome/Razão Social</span>
            <span class="ipm__val">{{ $t['nome'] }}</span>
        </td>
        <td style="width: 28%;">
            <span class="ipm__lbl">CPF/CNPJ</span>
            <span class="ipm__val">{{ $t['documento'] }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="ipm__lbl">Endereço</span>
            <span class="ipm__val ipm__txt">{{ $t['endereco'] }}</span>
        </td>
        <td style="width: 16%;">
            <span class="ipm__lbl">Número</span>
            <span class="ipm__val">{{ $t['numero'] }}</span>
        </td>
        <td>
            <span class="ipm__lbl">Complemento</span>
            <span class="ipm__val ipm__txt">{{ $t['complemento'] }}</span>
        </td>
    </tr>
    <tr>
        <td style="width: 28%;">
            <span class="ipm__lbl">Bairro</span>
            <span class="ipm__val ipm__txt">{{ $t['bairro'] }}</span>
        </td>
        <td style="width: 18%;">
            <span class="ipm__lbl">CEP</span>
            <span class="ipm__val">{{ $t['cep'] }}</span>
        </td>
        <td>
            <span class="ipm__lbl">Cidade</span>
            <span class="ipm__val ipm__txt">{{ $t['cidade'] }}</span>
        </td>
        <td>
            <span class="ipm__lbl">País</span>
            <span class="ipm__val ipm__txt">{{ $t['pais'] }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="ipm__lbl">Telefone</span>
            <span class="ipm__val">{{ $t['telefone'] }}</span>
        </td>
        <td colspan="2">
            <span class="ipm__lbl">Email</span>
            <span class="ipm__val ipm__txt">{{ $t['email'] }}</span>
        </td>
    </tr>
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="7" class="ipm__sec">Descrição dos Serviços Prestados</td>
    </tr>
    <tr>
        <td class="ipm__tot" style="width: 12%;">
            <span class="ipm__lbl">Serviço</span>
            <span class="ipm__val">{{ $d['servico_codigo'] }}</span>
        </td>
        <td class="ipm__tot" style="width: 14%;">
            <span class="ipm__lbl">Local da prestação</span>
            <span class="ipm__val">{{ $d['local_codigo'] }}</span>
        </td>
        <td class="ipm__tot" style="width: 16%;">
            <span class="ipm__lbl">Alíquota</span>
            <span class="ipm__val">{{ $d['aliquota'] }}</span>
        </td>
        <td class="ipm__tot" style="width: 14%;">
            <span class="ipm__lbl">Valor do serviço</span>
            <span class="ipm__val">{{ $d['valor_servico'] }}</span>
        </td>
        <td class="ipm__tot" style="width: 14%;">
            <span class="ipm__lbl">Desconto incondicional</span>
            <span class="ipm__val">{{ $d['desconto_incondicional'] }}</span>
        </td>
        <td class="ipm__tot" style="width: 14%;">
            <span class="ipm__lbl">Dedução</span>
            <span class="ipm__val">{{ $d['deducao'] }}</span>
        </td>
        <td class="ipm__tot" style="width: 16%;">
            <span class="ipm__lbl">Valor ISS</span>
            <span class="ipm__val">{{ $d['valor_iss'] }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="7">
            <span class="ipm__lbl">Natureza da operação</span>
            <span class="ipm__val ipm__txt">{{ $d['natureza'] }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="7">
            <span class="ipm__lbl">NBS</span>
            <span class="ipm__val ipm__txt">{{ $d['nbs'] }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="7">
            <span class="ipm__lbl">Descrição do serviço</span>
            <span class="ipm__val ipm__txt">{!! nl2br(e($d['descricao'])) !!}</span>
        </td>
    </tr>
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        @foreach ([
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
        ] as $rotulo => $valor)
            <td class="ipm__tot">
                <span class="ipm__lbl">{{ $rotulo }}</span>
                <span class="ipm__val">{{ $valor }}</span>
            </td>
        @endforeach
    </tr>
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td>
            <div class="ipm__lbl">Descrição do item da Lista de Serviço da LC 116/03</div>
            <div class="ipm__bloco">{{ $d['lc116'] }}</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="ipm__lbl">Atividade econômica</div>
            <div class="ipm__bloco">{{ $d['atividade'] }}</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="ipm__lbl">Legenda do local da prestação</div>
            <div class="ipm__bloco">{{ $d['legenda_local'] }}</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="ipm__lbl">Outras Informações</div>
            <div class="ipm__bloco">{!! nl2br(e($d['outras'])) !!}</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="ipm__lbl">Observações</div>
            <div class="ipm__bloco">{!! nl2br(e($d['observacoes'])) !!}</div>
        </td>
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
