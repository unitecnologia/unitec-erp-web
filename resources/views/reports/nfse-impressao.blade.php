<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ ! empty($espelho) ? 'Espelho da NFS-e — SEM VALIDADE FISCAL' : ('DANFSe '.($danfse['numero_nfse'] ?? $danfse['numero_dps'])) }}</title>
    @include('reports.partials.nfse-danfse-styles')
</head>
<body class="{{ ($danfse['fonte'] ?? '') === 'xml' ? 'danfse--xml' : '' }}">
@php
    $d = $danfse;
@endphp
@if (($d['fonte'] ?? '') === 'xml')
    @if (! empty($d['marca_dagua']))
        <div class="danfse__marca">{{ $d['marca_dagua'] }}</div>
    @endif
    @if (! empty($d['aviso_espelho']))
        <div class="danfse__aviso danfse__aviso--espelho">{{ $d['aviso_espelho'] }}</div>
        @if (! empty($d['aviso_espelho_sub']))
            <div class="danfse__aviso-sub">{{ $d['aviso_espelho_sub'] }}</div>
        @endif
    @endif

    <table class="danfse" cellspacing="0" cellpadding="0">
        <tr class="danfse__shade">
            <td style="width: 28%;">
                @if (! empty($d['logo_data_uri']))
                    <img src="{{ $d['logo_data_uri'] }}" alt="NFS-e" class="danfse__logo">
                @endif
            </td>
            <td style="width: 44%;">
                <div class="danfse__title">{{ $d['versao'] }}</div>
                <div class="danfse__subtitle">{{ $d['subtitulo'] }}</div>
                @if (! empty($d['sem_validade_juridica']) && empty($d['aviso_espelho']))
                    <div class="danfse__homolog">NFS-e SEM VALIDADE JURÍDICA</div>
                @endif
            </td>
            <td style="width: 28%;">
                @if (($d['municipio'] ?? '') !== '')
                    <div class="danfse__cabeca-mun">{{ $d['municipio'] }}</div>
                @endif
                <div class="danfse__cabeca-amb">{{ $d['amb_ger'] }}</div>
                <div class="danfse__cabeca-amb">{{ $d['tp_amb'] }}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="danfse__label">Chave de Acesso da NFS-e</span>
                <span class="danfse__chave">{{ $d['chave_formatada'] }}</span>
            </td>
            <td rowspan="4" style="text-align: center;">
                @if (! empty($d['qr_data_uri']))
                    <img src="{{ $d['qr_data_uri'] }}" alt="QR Code" class="danfse__qr">
                    <div class="danfse__qr-msg">{{ $d['qr_mensagem'] }}</div>
                @else
                    <span class="danfse__value danfse__value--normal danfse__value--center">-</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>
                <span class="danfse__label">Número da NFS-e</span>
                <span class="danfse__value">{{ $d['numero_nfse'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Competência da NFS-e</span>
                <span class="danfse__value">{{ $d['competencia'] }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="danfse__label">Data e Hora da emissão da NFS-e</span>
                <span class="danfse__value">{{ $d['dh_emissao_nfse'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Número da DPS</span>
                <span class="danfse__value">{{ $d['numero_dps'] }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="danfse__label">Série da DPS</span>
                <span class="danfse__value">{{ $d['serie_dps'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Data e Hora da emissão da DPS</span>
                <span class="danfse__value">{{ $d['dh_emissao_dps'] }}</span>
            </td>
        </tr>
    </table>

    <table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
        <tr>
            <td class="danfse__shade" style="width: 34%;">
                <span class="danfse__label">Emitente da NFS-e</span>
                <span class="danfse__value danfse__value--normal">{{ $d['emitente_nfse'] }}</span>
            </td>
            <td style="width: 33%;">
                <span class="danfse__label">Situação da NFS-e</span>
                <span class="danfse__value danfse__value--normal">{{ $d['situacao'] }}</span>
            </td>
            <td style="width: 33%;">
                <span class="danfse__label">Finalidade</span>
                <span class="danfse__value danfse__value--normal">{{ $d['finalidade'] }}</span>
            </td>
        </tr>
    </table>

    <table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
        <tr>
            <td colspan="4" class="danfse__section">Prestador / Fornecedor</td>
        </tr>
        <tr>
            <td style="width: 25%;">
                <span class="danfse__label">CNPJ / CPF / NIF</span>
                <span class="danfse__value">{{ $d['prestador']['documento'] }}</span>
            </td>
            <td style="width: 25%;">
                <span class="danfse__label">Indicador Municipal (Inscrição)</span>
                <span class="danfse__value">{{ $d['prestador']['im'] }}</span>
            </td>
            <td style="width: 25%;">
                <span class="danfse__label">Telefone</span>
                <span class="danfse__value">{{ $d['prestador']['telefone'] }}</span>
            </td>
            <td style="width: 25%;">
                <span class="danfse__label">Nome / Nome Empresarial</span>
                <span class="danfse__value">{{ $d['prestador']['nome'] }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="danfse__label">Município / Sigla UF</span>
                <span class="danfse__value danfse__value--normal">{{ $d['prestador']['municipio_uf'] }}</span>
            </td>
            <td colspan="2">
                <span class="danfse__label">Código IBGE / CEP</span>
                <span class="danfse__value">{{ $d['prestador']['ibge_cep'] }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="danfse__label">Endereço</span>
                <span class="danfse__value danfse__value--normal">{{ $d['prestador']['endereco'] }}</span>
            </td>
            <td colspan="2">
                <span class="danfse__label">E-mail</span>
                <span class="danfse__value danfse__value--normal">{{ $d['prestador']['email'] }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="danfse__label">Simples Nacional na Data de Competência</span>
                <span class="danfse__value danfse__value--normal">{{ $d['prestador']['simples'] }}</span>
            </td>
            <td colspan="2">
                <span class="danfse__label">Regime de Apuração Tributária pelo SN</span>
                <span class="danfse__value danfse__value--normal">{{ $d['prestador']['regime_sn'] }}</span>
            </td>
        </tr>
    </table>

    <table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
        <tr>
            <td colspan="4" class="danfse__section">Tomador / Adquirente da Operação</td>
        </tr>
        @if (! empty($d['tomador_frase']))
            <tr>
                <td class="danfse__frase">{{ $d['tomador_frase'] }}</td>
            </tr>
        @else
            <tr>
                <td style="width: 25%;">
                    <span class="danfse__label">CNPJ / CPF / NIF</span>
                    <span class="danfse__value">{{ $d['tomador']['documento'] }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="danfse__label">Indicador Municipal (Inscrição)</span>
                    <span class="danfse__value">{{ $d['tomador']['im'] }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="danfse__label">Telefone</span>
                    <span class="danfse__value">{{ $d['tomador']['telefone'] }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="danfse__label">Nome / Nome Empresarial</span>
                    <span class="danfse__value">{{ $d['tomador']['nome'] }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="danfse__label">Município / Sigla UF</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['tomador']['municipio_uf'] }}</span>
                </td>
                <td colspan="2">
                    <span class="danfse__label">Código IBGE / CEP</span>
                    <span class="danfse__value">{{ $d['tomador']['ibge_cep'] }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="danfse__label">Endereço</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['tomador']['endereco'] }}</span>
                </td>
                <td colspan="2">
                    <span class="danfse__label">E-mail</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['tomador']['email'] }}</span>
                </td>
            </tr>
        @endif
    </table>

    <table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
        <tr>
            <td colspan="4" class="danfse__section">Destinatário da Operação</td>
        </tr>
        @if (! empty($d['destinatario_frase']))
            <tr>
                <td class="danfse__frase">{{ $d['destinatario_frase'] }}</td>
            </tr>
        @else
            <tr>
                <td style="width: 34%;">
                    <span class="danfse__label">CNPJ / CPF / NIF</span>
                    <span class="danfse__value">{{ $d['destinatario']['documento'] }}</span>
                </td>
                <td style="width: 33%;">
                    <span class="danfse__label">Telefone</span>
                    <span class="danfse__value">{{ $d['destinatario']['telefone'] }}</span>
                </td>
                <td style="width: 33%;">
                    <span class="danfse__label">Nome / Nome Empresarial</span>
                    <span class="danfse__value">{{ $d['destinatario']['nome'] }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="danfse__label">Município / Sigla UF</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['destinatario']['municipio_uf'] }}</span>
                </td>
                <td>
                    <span class="danfse__label">Código IBGE / CEP</span>
                    <span class="danfse__value">{{ $d['destinatario']['ibge_cep'] }}</span>
                </td>
                <td>
                    <span class="danfse__label">E-mail</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['destinatario']['email'] }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="danfse__label">Endereço</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['destinatario']['endereco'] }}</span>
                </td>
            </tr>
        @endif
    </table>

    <table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
        <tr>
            <td colspan="4" class="danfse__section">Intermediário da Operação</td>
        </tr>
        @if (! empty($d['intermediario_frase']))
            <tr>
                <td class="danfse__frase">{{ $d['intermediario_frase'] }}</td>
            </tr>
        @else
            <tr>
                <td style="width: 25%;">
                    <span class="danfse__label">CNPJ / CPF / NIF</span>
                    <span class="danfse__value">{{ $d['intermediario']['documento'] }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="danfse__label">Indicador Municipal (Inscrição)</span>
                    <span class="danfse__value">{{ $d['intermediario']['im'] }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="danfse__label">Telefone</span>
                    <span class="danfse__value">{{ $d['intermediario']['telefone'] }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="danfse__label">Nome / Nome Empresarial</span>
                    <span class="danfse__value">{{ $d['intermediario']['nome'] }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="danfse__label">Município / Sigla UF</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['intermediario']['municipio_uf'] }}</span>
                </td>
                <td colspan="2">
                    <span class="danfse__label">Código IBGE / CEP</span>
                    <span class="danfse__value">{{ $d['intermediario']['ibge_cep'] }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="danfse__label">Endereço</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['intermediario']['endereco'] }}</span>
                </td>
                <td colspan="2">
                    <span class="danfse__label">E-mail</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['intermediario']['email'] }}</span>
                </td>
            </tr>
        @endif
    </table>

    <table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
        <tr>
            <td colspan="3" class="danfse__section">Serviço Prestado</td>
        </tr>
        <tr>
            <td style="width: 34%;">
                <span class="danfse__label">Código de Tributação Nacional / Municipal</span>
                <span class="danfse__value">{{ $d['servico']['codigo'] }}</span>
            </td>
            <td style="width: 33%;">
                <span class="danfse__label">Código da NBS</span>
                <span class="danfse__value">{{ $d['servico']['nbs'] }}</span>
            </td>
            <td style="width: 33%;">
                <span class="danfse__label">Local da Prestação / Sigla UF / País</span>
                <span class="danfse__value danfse__value--normal">{{ $d['servico']['local'] }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <span class="danfse__desc-codigo">{{ $d['servico']['descricao_codigo'] }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <span class="danfse__label">Descrição do Serviço</span>
                <span class="danfse__value danfse__value--normal">{{ $d['servico']['descricao'] }}</span>
            </td>
        </tr>
    </table>

    <table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
        <tr>
            <td colspan="4" class="danfse__section">Tributação Municipal (ISSQN)</td>
        </tr>
        @if (! empty($d['issqn_frase']))
            <tr>
                <td class="danfse__frase">{{ $d['issqn_frase'] }}</td>
            </tr>
        @else
            <tr>
                <td style="width: 25%;">
                    <span class="danfse__label">Tipo de Tributação do ISSQN</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['municipal']['trib_issqn'] }}</span>
                </td>
                <td style="width: 50%;" colspan="2">
                    <span class="danfse__label">Município / Sigla UF / País de Incidência do ISSQN</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['municipal']['incidencia'] }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="danfse__label">Regime Especial de Tributação do ISSQN</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['municipal']['regime_especial'] }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="danfse__label">Tipo de Imunidade do ISSQN</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['municipal']['tipo_imunidade'] }}</span>
                </td>
                <td>
                    <span class="danfse__label">Suspensão da Exigibilidade do ISSQN</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['municipal']['suspensao'] }}</span>
                </td>
                <td>
                    <span class="danfse__label">Número Processo Suspensão</span>
                    <span class="danfse__value">{{ $d['municipal']['nro_processo'] }}</span>
                </td>
                <td>
                    <span class="danfse__label">Benefício Municipal</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['municipal']['beneficio_municipal'] }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="danfse__label">Cálculo do BM</span>
                    <span class="danfse__value">{{ $d['municipal']['calculo_bm'] }}</span>
                </td>
                <td>
                    <span class="danfse__label">Total Deduções/Reduções</span>
                    <span class="danfse__value">{{ $d['municipal']['total_deducoes'] }}</span>
                </td>
                <td>
                    <span class="danfse__label">Desconto Incondicionado</span>
                    <span class="danfse__value">{{ $d['municipal']['desconto_incondicionado'] }}</span>
                </td>
                <td>
                    <span class="danfse__label">BC ISSQN</span>
                    <span class="danfse__value">{{ $d['municipal']['bc_issqn'] }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="danfse__label">Alíquota Aplicada</span>
                    <span class="danfse__value">{{ $d['municipal']['aliquota'] }}</span>
                </td>
                <td>
                    <span class="danfse__label">Retenção do ISSQN</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['municipal']['retencao_issqn'] }}</span>
                </td>
                <td colspan="2">
                    <span class="danfse__label">ISSQN Apurado</span>
                    <span class="danfse__value">{{ $d['municipal']['issqn_apurado'] }}</span>
                </td>
            </tr>
        @endif
    </table>

    <table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
        <tr>
            <td colspan="3" class="danfse__section">Tributação Federal (Exceto CBS)</td>
        </tr>
        <tr>
            <td style="width: 34%;">
                <span class="danfse__label">IRRF</span>
                <span class="danfse__value">{{ $d['federal']['irrf'] }}</span>
            </td>
            <td style="width: 33%;">
                <span class="danfse__label">Contribuição Previdenciária - Retida</span>
                <span class="danfse__value">{{ $d['federal']['cp'] }}</span>
            </td>
            <td style="width: 33%;">
                <span class="danfse__label">Contribuições Sociais - Retidas</span>
                <span class="danfse__value">{{ $d['federal']['csll'] }}</span>
            </td>
        </tr>
        @if (! empty($d['federal_ate_2026']))
            <tr>
                <td>
                    <span class="danfse__label">PIS - Débito Apuração Própria</span>
                    <span class="danfse__value">{{ $d['federal']['pis'] }}</span>
                </td>
                <td>
                    <span class="danfse__label">COFINS - Débito Apuração Própria</span>
                    <span class="danfse__value">{{ $d['federal']['cofins'] }}</span>
                </td>
                <td>
                    <span class="danfse__label">Descrição Contrib. Sociais - Retidas</span>
                    <span class="danfse__value danfse__value--normal">{{ $d['federal']['descricao_retencao'] }}</span>
                </td>
            </tr>
        @endif
    </table>

    <table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
        <tr>
            <td colspan="4" class="danfse__section">Tributação IBS / CBS</td>
        </tr>
        <tr>
            <td style="width: 25%;">
                <span class="danfse__label">CST / cClassTrib</span>
                <span class="danfse__value">{{ $d['ibs']['cst'] }}</span>
            </td>
            <td colspan="3">
                <span class="danfse__label">Indicador de Operação / Código IBGE / Município / UF</span>
                <span class="danfse__value danfse__value--normal">{{ $d['ibs']['indicador'] }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="danfse__label">Exclusões e Reduções da Base de Cálculo</span>
                <span class="danfse__value">{{ $d['ibs']['exclusoes'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Base de Cálculo Após Exclusões e Reduções</span>
                <span class="danfse__value">{{ $d['ibs']['bc'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Red. Alíquota IBS / Red. Alíquota CBS</span>
                <span class="danfse__value">{{ $d['ibs']['red_aliq'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Alíquota IBS UF / IBS Mun</span>
                <span class="danfse__value">{{ $d['ibs']['aliq_ibs'] }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="danfse__label">Alíq. Efetiva Municipal - IBS</span>
                <span class="danfse__value">{{ $d['ibs']['aliq_efet_mun'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Valor Apurado Municipal - IBS</span>
                <span class="danfse__value">{{ $d['ibs']['valor_mun'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Alíq. Efetiva Estadual - IBS</span>
                <span class="danfse__value">{{ $d['ibs']['aliq_efet_uf'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Valor Apurado Estadual - IBS</span>
                <span class="danfse__value">{{ $d['ibs']['valor_uf'] }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="danfse__label">Valor Total Apurado - IBS</span>
                <span class="danfse__value">{{ $d['ibs']['valor_ibs'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Alíquota - CBS</span>
                <span class="danfse__value">{{ $d['ibs']['aliq_cbs'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Alíquota Efetiva - CBS</span>
                <span class="danfse__value">{{ $d['ibs']['aliq_efet_cbs'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Valor Total Apurado - CBS</span>
                <span class="danfse__value">{{ $d['ibs']['valor_cbs'] }}</span>
            </td>
        </tr>
    </table>

    <table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
        <tr>
            <td colspan="4" class="danfse__section">Valor Total da NFS-e</td>
        </tr>
        <tr>
            <td style="width: 25%;">
                <span class="danfse__label">Valor da Operação / Serviço</span>
                <span class="danfse__value">{{ $d['totais']['valor_servico'] }}</span>
            </td>
            <td style="width: 25%;">
                <span class="danfse__label">Desconto Incondicionado</span>
                <span class="danfse__value">{{ $d['totais']['desconto_incondicionado'] }}</span>
            </td>
            <td style="width: 25%;">
                <span class="danfse__label">Desconto Condicionado</span>
                <span class="danfse__value">{{ $d['totais']['desconto_condicionado'] }}</span>
            </td>
            <td style="width: 25%;">
                <span class="danfse__label">Total das Retenções (ISSQN / Federais)</span>
                <span class="danfse__value">{{ $d['totais']['total_retencoes'] }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="danfse__label">Valor Líquido da NFS-e</span>
                <span class="danfse__value">{{ $d['totais']['valor_liquido'] }}</span>
            </td>
            <td>
                <span class="danfse__label">Total do IBS/CBS</span>
                <span class="danfse__value">{{ $d['totais']['total_ibs_cbs'] }}</span>
            </td>
            <td colspan="2" class="danfse__shade">
                <span class="danfse__label">Valor Líquido da NFS-e + IBS/CBS</span>
                <span class="danfse__value">{{ $d['totais']['valor_liquido_ibs'] }}</span>
            </td>
        </tr>
    </table>

    <table class="danfse" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
        <tr>
            <td class="danfse__section">Informações Complementares</td>
        </tr>
        <tr>
            <td>
                <span class="danfse__value danfse__value--normal">{{ $d['complementares'] }}</span>
            </td>
        </tr>
    </table>
@else
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
@endif

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
