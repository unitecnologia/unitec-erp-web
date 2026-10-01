<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ ! empty($espelho) ? 'Espelho da NFS-e — SEM VALIDADE FISCAL' : ('DANFSe '.($danfse['numero_nfse'] ?? $danfse['numero_dps'])) }}</title>
    @include('reports.partials.nfse-danfse-styles')
</head>
<body>
@php
    $d = $danfse;
@endphp

@if (! empty($d['aviso_espelho']))
    <div class="danfse__aviso danfse__aviso--espelho">{{ $d['aviso_espelho'] }}</div>
    @if (! empty($d['aviso_espelho_sub']))
        <div class="danfse__aviso-sub">{{ $d['aviso_espelho_sub'] }}</div>
    @endif
@elseif (! empty($d['sem_validade_juridica']))
    <div class="danfse__aviso">NFS-e SEM VALIDADE JURÍDICA</div>
@endif

<table class="danfse" cellspacing="0" cellpadding="0">
    <tr>
        <td style="width: 28%;">
            <div class="danfse__brand">NFS-e</div>
            <div class="danfse__brand-sub">Nota Fiscal de Serviço eletrônica</div>
        </td>
        <td style="width: 44%;">
            <div class="danfse__title">{{ $d['versao'] }}</div>
            <div class="danfse__subtitle">{{ $d['subtitulo'] }}</div>
        </td>
        <td style="width: 28%; text-align: center;">
            @if (! empty($d['qr_data_uri']))
                <img src="{{ $d['qr_data_uri'] }}" alt="QR Code" class="danfse__qr">
                <div class="danfse__qr-msg">{{ $d['qr_mensagem'] }}</div>
            @else
                <span class="danfse__value danfse__value--normal danfse__value--center">-</span>
            @endif
        </td>
    </tr>
    <tr>
        <td colspan="3">
            <span class="danfse__label">Chave de Acesso da NFS-e</span>
            <span class="danfse__chave">{{ $d['chave_formatada'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="danfse__label">Número da NFS-e</span>
            <span class="danfse__value">{{ $d['numero_nfse'] }}</span>
            <span class="danfse__label" style="margin-top: 3px;">Número da DPS</span>
            <span class="danfse__value">{{ $d['numero_dps'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Competência da NFS-e</span>
            <span class="danfse__value">{{ $d['competencia'] }}</span>
            <span class="danfse__label" style="margin-top: 3px;">Série da DPS</span>
            <span class="danfse__value">{{ $d['serie_dps'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Data e Hora da emissão da NFS-e</span>
            <span class="danfse__value">{{ $d['dh_emissao_nfse'] }}</span>
            <span class="danfse__label" style="margin-top: 3px;">Data e Hora da emissão da DPS</span>
            <span class="danfse__value">{{ $d['dh_emissao_dps'] }}</span>
        </td>
    </tr>
</table>

<table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td colspan="3" class="danfse__section">EMITENTE DA NFS-e <span>Prestador do Serviço</span></td>
    </tr>
    <tr>
        <td style="width: 34%;">
            <span class="danfse__label">CNPJ / CPF / NIF</span>
            <span class="danfse__value">{{ $d['emitente']['documento'] }}</span>
        </td>
        <td style="width: 33%;">
            <span class="danfse__label">Inscrição Municipal</span>
            <span class="danfse__value">{{ $d['emitente']['im'] }}</span>
        </td>
        <td style="width: 33%;">
            <span class="danfse__label">Telefone</span>
            <span class="danfse__value">{{ $d['emitente']['telefone'] }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="danfse__label">Nome / Nome empresarial</span>
            <span class="danfse__value">{{ $d['emitente']['nome'] }}</span>
        </td>
        <td>
            <span class="danfse__label">E-mail</span>
            <span class="danfse__value danfse__value--normal">{{ $d['emitente']['email'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="danfse__label">Endereço</span>
            <span class="danfse__value danfse__value--normal">{{ $d['emitente']['endereco'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Município / UF</span>
            <span class="danfse__value danfse__value--normal">{{ $d['emitente']['municipio_uf'] }}</span>
        </td>
        <td>
            <span class="danfse__label">CEP</span>
            <span class="danfse__value">{{ $d['emitente']['cep'] }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="danfse__label">Simples Nacional na Data da Competência</span>
            <span class="danfse__value danfse__value--normal">{{ $d['emitente']['simples'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Regime de Apuração Tributária pelo SN</span>
            <span class="danfse__value danfse__value--normal">{{ $d['emitente']['regime_sn'] }}</span>
        </td>
    </tr>
</table>

<table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td colspan="3" class="danfse__section">TOMADOR DO SERVIÇO</td>
    </tr>
    <tr>
        <td style="width: 34%;">
            <span class="danfse__label">CNPJ / CPF / NIF</span>
            <span class="danfse__value">{{ $d['tomador']['documento'] }}</span>
        </td>
        <td style="width: 33%;">
            <span class="danfse__label">Inscrição Municipal</span>
            <span class="danfse__value">{{ $d['tomador']['im'] }}</span>
        </td>
        <td style="width: 33%;">
            <span class="danfse__label">Telefone</span>
            <span class="danfse__value">{{ $d['tomador']['telefone'] }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="danfse__label">Nome / Nome empresarial</span>
            <span class="danfse__value">{{ $d['tomador']['nome'] }}</span>
        </td>
        <td>
            <span class="danfse__label">E-mail</span>
            <span class="danfse__value danfse__value--normal">{{ $d['tomador']['email'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="danfse__label">Endereço</span>
            <span class="danfse__value danfse__value--normal">{{ $d['tomador']['endereco'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Município / UF</span>
            <span class="danfse__value danfse__value--normal">{{ $d['tomador']['municipio_uf'] }}</span>
        </td>
        <td>
            <span class="danfse__label">CEP</span>
            <span class="danfse__value">{{ $d['tomador']['cep'] }}</span>
        </td>
    </tr>
</table>

<table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td class="danfse__intermediario">INTERMEDIÁRIO DO SERVIÇO NÃO IDENTIFICADO NA NFS-e</td>
    </tr>
</table>

<table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td colspan="4" class="danfse__section">SERVIÇO PRESTADO</td>
    </tr>
    <tr>
        <td style="width: 25%;">
            <span class="danfse__label">Código de Tributação Nacional</span>
            <span class="danfse__value">{{ $d['servico']['c_trib_nac'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">Código de Tributação Municipal</span>
            <span class="danfse__value">{{ $d['servico']['c_trib_mun'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">Local da Prestação</span>
            <span class="danfse__value danfse__value--normal">{{ $d['servico']['local_prestacao'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">País da Prestação</span>
            <span class="danfse__value">{{ $d['servico']['pais_prestacao'] }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="4" style="min-height: 42px;">
            <span class="danfse__label">Descrição do Serviço</span>
            <span class="danfse__value danfse__value--normal">{{ $d['servico']['descricao'] }}</span>
        </td>
    </tr>
</table>

<table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td colspan="4" class="danfse__section">TRIBUTAÇÃO MUNICIPAL</td>
    </tr>
    <tr>
        <td style="width: 25%;">
            <span class="danfse__label">Tributação do ISSQN</span>
            <span class="danfse__value danfse__value--normal">{{ $d['municipal']['trib_issqn'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">País Resultado da Prestação do Serviço</span>
            <span class="danfse__value">{{ $d['municipal']['pais_resultado'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">Município de Incidência do ISSQN</span>
            <span class="danfse__value danfse__value--normal">{{ $d['municipal']['municipio_incidencia'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">Regime Especial de Tributação</span>
            <span class="danfse__value danfse__value--normal">{{ $d['municipal']['regime_especial'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="danfse__label">Tipo de Imunidade</span>
            <span class="danfse__value">{{ $d['municipal']['tipo_imunidade'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Suspensão da Exigibilidade do ISSQN</span>
            <span class="danfse__value">{{ $d['municipal']['suspensao'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Número Processo Suspensão</span>
            <span class="danfse__value">{{ $d['municipal']['nro_processo_suspensao'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Benefício Municipal</span>
            <span class="danfse__value">{{ $d['municipal']['beneficio_municipal'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="danfse__label">Valor do Serviço</span>
            <span class="danfse__value">{{ $d['municipal']['valor_servico'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Desconto Incondicionado</span>
            <span class="danfse__value">{{ $d['municipal']['desconto_incondicionado'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Total Deduções/Reduções</span>
            <span class="danfse__value">{{ $d['municipal']['total_deducoes'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Cálculo do BM</span>
            <span class="danfse__value">{{ $d['municipal']['calculo_bm'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="danfse__label">BC ISSQN</span>
            <span class="danfse__value">{{ $d['municipal']['bc_issqn'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Alíquota Aplicada</span>
            <span class="danfse__value">{{ $d['municipal']['aliquota'] }}</span>
        </td>
        <td>
            <span class="danfse__label">Retenção do ISSQN</span>
            <span class="danfse__value danfse__value--normal">{{ $d['municipal']['retencao_issqn'] }}</span>
        </td>
        <td>
            <span class="danfse__label">ISSQN Apurado</span>
            <span class="danfse__value">{{ $d['municipal']['issqn_apurado'] }}</span>
        </td>
    </tr>
</table>

<table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td colspan="4" class="danfse__section">TRIBUTAÇÃO FEDERAL</td>
    </tr>
    <tr>
        <td style="width: 25%;">
            <span class="danfse__label">IRRF</span>
            <span class="danfse__value">{{ $d['federal']['irrf'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">CP</span>
            <span class="danfse__value">{{ $d['federal']['cp'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">CSLL</span>
            <span class="danfse__value">{{ $d['federal']['csll'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">Total Tributação Federal</span>
            <span class="danfse__value">{{ $d['federal']['total'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="danfse__label">PIS</span>
            <span class="danfse__value">{{ $d['federal']['pis'] }}</span>
        </td>
        <td>
            <span class="danfse__label">COFINS</span>
            <span class="danfse__value">{{ $d['federal']['cofins'] }}</span>
        </td>
        <td colspan="2">
            <span class="danfse__label">Retenção de PIS/COFINS</span>
            <span class="danfse__value">{{ $d['federal']['retencao_pis_cofins'] }}</span>
        </td>
    </tr>
</table>

<table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td colspan="4" class="danfse__section">VALOR TOTAL DA NFS-e</td>
    </tr>
    <tr>
        <td style="width: 25%;">
            <span class="danfse__label">Valor do Serviço</span>
            <span class="danfse__value">{{ $d['totais']['valor_servico'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">Desconto Condicionado</span>
            <span class="danfse__value">{{ $d['totais']['desconto_condicionado'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">Desconto Incondicionado</span>
            <span class="danfse__value">{{ $d['totais']['desconto_incondicionado'] }}</span>
        </td>
        <td style="width: 25%;">
            <span class="danfse__label">ISSQN Retido</span>
            <span class="danfse__value">{{ $d['totais']['issqn_retido'] }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="danfse__label">IRRF, CP, CSLL — Retidos</span>
            <span class="danfse__value">{{ $d['totais']['irrf_cp_csll_retidos'] }}</span>
        </td>
        <td>
            <span class="danfse__label">PIS/COFINS Retidos</span>
            <span class="danfse__value">{{ $d['totais']['pis_cofins_retidos'] }}</span>
        </td>
        <td colspan="2">
            <span class="danfse__label">Valor Líquido da NFS-e</span>
            <span class="danfse__value">{{ $d['totais']['valor_liquido'] }}</span>
        </td>
    </tr>
</table>

<table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td colspan="3" class="danfse__section">TOTAIS APROXIMADOS DOS TRIBUTOS</td>
    </tr>
    <tr>
        <td style="width: 33%;">
            <span class="danfse__label">Federais</span>
            <span class="danfse__value">{{ $d['tributos_aproximados']['federais'] }}</span>
        </td>
        <td style="width: 34%;">
            <span class="danfse__label">Estaduais</span>
            <span class="danfse__value">{{ $d['tributos_aproximados']['estaduais'] }}</span>
        </td>
        <td style="width: 33%;">
            <span class="danfse__label">Municipais</span>
            <span class="danfse__value">{{ $d['tributos_aproximados']['municipais'] }}</span>
        </td>
    </tr>
</table>

<table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td class="danfse__section">INFORMAÇÕES COMPLEMENTARES</td>
    </tr>
    <tr>
        <td style="min-height: 36px;">
            <span class="danfse__value danfse__value--normal">{{ $d['complementares'] }}</span>
        </td>
    </tr>
</table>

@unless (! empty($embedded))
    <div class="danfse__acoes">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>
@endunless

@if (! empty($autoPrint))
    <script>window.addEventListener('load', function () { window.print(); });</script>
@endif
</body>
</html>
