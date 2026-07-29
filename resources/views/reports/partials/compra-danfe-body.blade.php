@php
    $transportador = $transportador ?? [
        'nome' => '', 'cnpj' => '', 'ie' => '', 'endereco' => '', 'municipio' => '', 'uf' => '',
        'placa' => '', 'placa_uf' => '', 'antt' => '', 'mod_frete' => '', 'mod_frete_label' => '',
    ];
    $volumes = $volumes ?? [
        'quantidade' => '', 'especie' => '', 'marca' => '', 'numeracao' => '',
        'peso_bruto' => '', 'peso_liquido' => '',
    ];
    $duplicatas = $duplicatas ?? [];
    $fatura = $fatura ?? ['numero' => '', 'valor_original' => '', 'valor_desconto' => '', 'valor_liquido' => ''];
    $informacoesFisco = $informacoesFisco ?? '';
    $horaEntrada = $horaEntrada ?? '';
@endphp
<table class="danfe" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="3" class="danfe__canhoto">
            RECEBEMOS DE {{ $emitente['nome'] }} OS PRODUTOS/SERVIÇOS CONSTANTES DA NOTA FISCAL INDICADA AO LADO.
        </td>
        <td style="width: 18%;" class="danfe__nfe-box">
            <div class="danfe__nfe-box-title">NF-e</div>
            <div class="danfe__value danfe__value--center">Nº {{ $numeroNota }}</div>
            <div class="danfe__value danfe__value--center">Série {{ $serie }}</div>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="width: 34%;">
            <span class="danfe__label">DATA DE RECEBIMENTO</span>
            <span class="danfe__value danfe__value--normal">&nbsp;</span>
        </td>
        <td style="width: 34%;">
            <span class="danfe__label">IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR</span>
            <span class="danfe__value danfe__value--normal">&nbsp;</span>
        </td>
        <td class="danfe__nfe-box">
            <div class="danfe__value danfe__value--center">Folha 1/1</div>
        </td>
    </tr>
</table>

<table class="danfe" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td style="width: 40%;">
            <span class="danfe__label">IDENTIFICAÇÃO DO EMITENTE</span>
            <span class="danfe__value">{{ $emitente['nome'] }}</span>
            <span class="danfe__value danfe__value--normal">{{ $emitente['endereco'] }}</span>
            @if (filled($emitente['bairro'] ?? null) || filled($emitente['cep'] ?? null))
                <span class="danfe__value danfe__value--normal">
                    {{ trim(($emitente['bairro'] ?? '').(filled($emitente['cep'] ?? null) ? ' — CEP '.($emitente['cep'] ?? '') : '')) }}
                </span>
            @endif
            <span class="danfe__value danfe__value--normal">{{ $emitente['municipio'] }} - {{ $emitente['uf'] }}</span>
            <span class="danfe__value danfe__value--normal">Fone/Fax: {{ $emitente['telefone'] ?: '&nbsp;' }}</span>
        </td>
        <td style="width: 22%; text-align: center;">
            <div class="danfe__title">{{ ($espelho ?? false) ? 'ESPELHO' : 'DANFE' }}</div>
            <div class="danfe__subtitle">
                @if ($espelho ?? false)
                    Pré-visualização da<br>Nota Fiscal Eletrônica
                @else
                    Documento Auxiliar da<br>Nota Fiscal Eletrônica
                @endif
            </div>
            <div class="danfe__tipo">{{ $tipoOperacao }} - {{ $tipoOperacaoLabel }}</div>
            <div class="danfe__value danfe__value--center" style="margin-top: 4px;">Nº {{ $numeroNota }}</div>
            <div class="danfe__value danfe__value--center">Série {{ $serie }}</div>
            <div class="danfe__value danfe__value--center">Folha 1/1</div>
        </td>
        <td style="width: 38%;">
            @if ($barcodeDataUri)
                <img src="{{ $barcodeDataUri }}" alt="Código de barras" class="danfe__barcode">
            @endif
            <div class="danfe__chave">CHAVE DE ACESSO</div>
            <div class="danfe__chave">{{ $chaveFormatada }}</div>
            @unless ($espelho ?? false)
                <div class="danfe__portal" style="margin-top: 4px;">
                    Consulta de autenticidade no portal nacional da NF-e<br>
                    www.nfe.fazenda.gov.br/portal ou no site da Sefaz Autorizadora
                </div>
            @endunless
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="danfe__label">NATUREZA DA OPERAÇÃO</span>
            <span class="danfe__value">{{ $naturezaOperacao }}</span>
        </td>
        <td>
            <span class="danfe__label">PROTOCOLO DE AUTORIZAÇÃO DE USO</span>
            <span class="danfe__value danfe__value--normal">{{ $protocolo ?: '&nbsp;' }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="danfe__label">INSCRIÇÃO ESTADUAL</span>
            <span class="danfe__value danfe__value--normal">{{ $emitente['ie'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">IE DO SUBST. TRIB.</span>
            <span class="danfe__value danfe__value--normal">{{ ($emitente['ie_st'] ?? '') ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">CNPJ</span>
            <span class="danfe__value">{{ $emitente['cnpj'] }}</span>
        </td>
    </tr>
</table>

<table class="danfe" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td colspan="5" class="danfe__section-title">DESTINATÁRIO / REMETENTE</td>
    </tr>
    <tr>
        <td colspan="3">
            <span class="danfe__label">NOME / RAZÃO SOCIAL</span>
            <span class="danfe__value">{{ $destinatario['nome'] }}</span>
        </td>
        <td>
            <span class="danfe__label">CNPJ / CPF</span>
            <span class="danfe__value">{{ $destinatario['cnpj'] }}</span>
        </td>
        <td>
            <span class="danfe__label">DATA DA EMISSÃO</span>
            <span class="danfe__value danfe__value--center">{{ $dataEmissao }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="danfe__label">ENDEREÇO</span>
            <span class="danfe__value danfe__value--normal">{{ $destinatario['endereco'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">BAIRRO / DISTRITO</span>
            <span class="danfe__value danfe__value--normal">{{ ($destinatario['bairro'] ?? '') ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">CEP</span>
            <span class="danfe__value danfe__value--center">{{ ($destinatario['cep'] ?? '') ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">DATA DA ENTRADA / SAÍDA</span>
            <span class="danfe__value danfe__value--center">{{ $dataEntrada }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="danfe__label">MUNICÍPIO</span>
            <span class="danfe__value danfe__value--normal">{{ $destinatario['municipio'] ?: '&nbsp;' }}</span>
        </td>
        <td style="width: 7%;">
            <span class="danfe__label">UF</span>
            <span class="danfe__value danfe__value--center">{{ $destinatario['uf'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">FONE / FAX</span>
            <span class="danfe__value danfe__value--normal">{{ $destinatario['telefone'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">INSCRIÇÃO ESTADUAL</span>
            <span class="danfe__value danfe__value--normal">{{ $destinatario['ie'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">HORA DA ENTRADA / SAÍDA</span>
            <span class="danfe__value danfe__value--center">{{ $horaEntrada ?: '&nbsp;' }}</span>
        </td>
    </tr>
</table>

<table class="danfe" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td colspan="3" class="danfe__section-title">FATURA / DUPLICATA</td>
    </tr>
    <tr>
        <td colspan="3" style="padding: 2px;">
            @if ($duplicatas !== [])
                <div class="danfe__fatura-list">
                    @foreach ($duplicatas as $dupIndex => $dup)
                        @if ($dupIndex > 0)
                            <span class="danfe__fatura-sep" aria-hidden="true">|</span>
                        @endif
                        <span class="danfe__fatura-item">
                            {{ $dup['numero'] ?: '—' }}
                            {{ filled($dup['vencimento']) ? $dup['vencimento'] : '' }}
                            {{ filled($dup['valor']) ? 'R$ '.$dup['valor'] : '' }}
                        </span>
                    @endforeach
                </div>
            @elseif (filled($fatura['numero'] ?? null) || filled($fatura['valor_liquido'] ?? null))
                <span class="danfe__value danfe__value--normal">
                    Fat. {{ $fatura['numero'] ?: '—' }}
                    @if (filled($fatura['valor_liquido'] ?? null))
                        — Líq. R$ {{ $fatura['valor_liquido'] }}
                    @endif
                </span>
            @else
                &nbsp;
            @endif
        </td>
    </tr>
</table>

<table class="danfe__grid-total" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td><span class="danfe__label">BASE DE CÁLC. DO ICMS</span><span class="danfe__value danfe__value--right">{{ $totais['base_icms'] }}</span></td>
        <td><span class="danfe__label">VALOR DO ICMS</span><span class="danfe__value danfe__value--right">{{ $totais['valor_icms'] }}</span></td>
        <td><span class="danfe__label">BASE DE CÁLC. ICMS S.T.</span><span class="danfe__value danfe__value--right">{{ $totais['base_icms_st'] }}</span></td>
        <td><span class="danfe__label">VALOR DO ICMS SUBSTITUIÇÃO</span><span class="danfe__value danfe__value--right">{{ $totais['valor_icms_st'] }}</span></td>
        <td><span class="danfe__label">VALOR TOTAL DOS PRODUTOS</span><span class="danfe__value danfe__value--right">{{ $totais['total_produtos'] }}</span></td>
    </tr>
    <tr>
        <td><span class="danfe__label">VALOR DO FRETE</span><span class="danfe__value danfe__value--right">{{ $totais['frete'] }}</span></td>
        <td><span class="danfe__label">VALOR DO SEGURO</span><span class="danfe__value danfe__value--right">{{ $totais['seguro'] }}</span></td>
        <td><span class="danfe__label">DESCONTO</span><span class="danfe__value danfe__value--right">{{ $totais['desconto'] }}</span></td>
        <td><span class="danfe__label">OUTRAS DESPESAS</span><span class="danfe__value danfe__value--right">{{ $totais['outras'] }}</span></td>
        <td><span class="danfe__label">VALOR TOTAL DO IPI</span><span class="danfe__value danfe__value--right">{{ $totais['total_ipi'] }}</span></td>
    </tr>
    <tr>
        <td><span class="danfe__label">VALOR DO PIS</span><span class="danfe__value danfe__value--right">{{ $totais['total_pis'] ?? '0,00' }}</span></td>
        <td><span class="danfe__label">VALOR DA COFINS</span><span class="danfe__value danfe__value--right">{{ $totais['total_cofins'] ?? '0,00' }}</span></td>
        <td colspan="2">&nbsp;</td>
        <td><span class="danfe__label">VALOR TOTAL DA NOTA</span><span class="danfe__value danfe__value--right">{{ $totais['total_nota'] }}</span></td>
    </tr>
</table>

<table class="danfe" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td colspan="6" class="danfe__section-title">TRANSPORTADOR / VOLUMES TRANSPORTADOS</td>
    </tr>
    <tr>
        <td colspan="2" style="width: 36%;">
            <span class="danfe__label">NOME / RAZÃO SOCIAL</span>
            <span class="danfe__value danfe__value--normal">{{ $transportador['nome'] ?: '&nbsp;' }}</span>
        </td>
        <td style="width: 18%;">
            <span class="danfe__label">FRETE POR CONTA</span>
            <span class="danfe__value danfe__value--normal">{{ $transportador['mod_frete_label'] ?: '&nbsp;' }}</span>
        </td>
        <td style="width: 12%;">
            <span class="danfe__label">CÓDIGO ANTT</span>
            <span class="danfe__value danfe__value--normal">{{ $transportador['antt'] ?: '&nbsp;' }}</span>
        </td>
        <td style="width: 12%;">
            <span class="danfe__label">PLACA DO VEÍCULO</span>
            <span class="danfe__value danfe__value--center">{{ $transportador['placa'] ?: '&nbsp;' }}</span>
        </td>
        <td style="width: 10%;">
            <span class="danfe__label">UF</span>
            <span class="danfe__value danfe__value--center">{{ $transportador['placa_uf'] ?: '&nbsp;' }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="danfe__label">CNPJ / CPF</span>
            <span class="danfe__value danfe__value--normal">{{ $transportador['cnpj'] ?: '&nbsp;' }}</span>
        </td>
        <td colspan="2">
            <span class="danfe__label">ENDEREÇO</span>
            <span class="danfe__value danfe__value--normal">{{ $transportador['endereco'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">MUNICÍPIO</span>
            <span class="danfe__value danfe__value--normal">{{ $transportador['municipio'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">UF</span>
            <span class="danfe__value danfe__value--center">{{ $transportador['uf'] ?: '&nbsp;' }}</span>
        </td>
    </tr>
    <tr>
        <td style="width: 12%;">
            <span class="danfe__label">QUANTIDADE</span>
            <span class="danfe__value danfe__value--center">{{ $volumes['quantidade'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">ESPÉCIE</span>
            <span class="danfe__value danfe__value--normal">{{ $volumes['especie'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">MARCA</span>
            <span class="danfe__value danfe__value--normal">{{ $volumes['marca'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">NUMERAÇÃO</span>
            <span class="danfe__value danfe__value--normal">{{ $volumes['numeracao'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">PESO BRUTO</span>
            <span class="danfe__value danfe__value--right">{{ $volumes['peso_bruto'] ?: '&nbsp;' }}</span>
        </td>
        <td>
            <span class="danfe__label">PESO LÍQUIDO</span>
            <span class="danfe__value danfe__value--right">{{ $volumes['peso_liquido'] ?: '&nbsp;' }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="6">
            <span class="danfe__label">INSCRIÇÃO ESTADUAL</span>
            <span class="danfe__value danfe__value--normal">{{ $transportador['ie'] ?: '&nbsp;' }}</span>
        </td>
    </tr>
</table>

<table class="danfe" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td class="danfe__section-title">DADOS DOS PRODUTOS / SERVIÇOS</td>
    </tr>
    <tr>
        <td style="padding: 0; border-top: none;">
            <table class="danfe__items" cellspacing="0" cellpadding="0">
                <colgroup>
                    <col style="width: 8%;">
                    <col style="width: 24%;">
                    <col style="width: 7%;">
                    <col style="width: 4%;">
                    <col style="width: 4%;">
                    <col style="width: 3%;">
                    <col style="width: 6%;">
                    <col style="width: 7%;">
                    <col style="width: 7%;">
                    <col style="width: 5%;">
                    <col style="width: 6%;">
                    <col style="width: 6%;">
                    <col style="width: 5%;">
                    <col style="width: 4%;">
                    <col style="width: 4%;">
                </colgroup>
                <thead>
                    <tr>
                        <th style="width: 8%;">CÓDIGO<br>PRODUTO</th>
                        <th style="width: 24%;">DESCRIÇÃO DO PRODUTO / SERVIÇO</th>
                        <th style="width: 7%;">NCM/SH</th>
                        <th style="width: 4%;">O/CST</th>
                        <th style="width: 4%;">CFOP</th>
                        <th style="width: 3%;">UN</th>
                        <th style="width: 6%;">QUANT</th>
                        <th style="width: 7%;">VALOR<br>UNIT</th>
                        <th style="width: 7%;">VALOR<br>TOTAL</th>
                        <th style="width: 5%;">VALOR<br>DESC</th>
                        <th style="width: 6%;">B.CALC<br>ICMS</th>
                        <th style="width: 6%;">VALOR<br>ICMS</th>
                        <th style="width: 5%;">VALOR<br>IPI</th>
                        <th style="width: 4%;">ALIQ.<br>ICMS</th>
                        <th style="width: 4%;">ALIQ.<br>IPI</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($itens as $item)
                        <tr>
                            <td class="danfe__item-cell danfe__item-cell--center">{{ $item['codigo'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--desc">{{ $item['descricao'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--center">{{ $item['ncm'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--center">{{ $item['cst'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--center">{{ $item['cfop'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--center">{{ $item['un'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--right">{{ $item['quant'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--right">{{ $item['valor_unit'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--right">{{ $item['valor_total'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--right">{{ $item['desconto'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--right">{{ $item['base_icms'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--right">{{ $item['valor_icms'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--right">{{ $item['valor_ipi'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--right">{{ $item['aliq_icms'] }}</td>
                            <td class="danfe__item-cell danfe__item-cell--right">{{ $item['aliq_ipi'] }}</td>
                        </tr>
                    @endforeach
                    @for ($i = count($itens); $i < 8; $i++)
                        <tr class="danfe__blank-row">
                            <td colspan="15">&nbsp;</td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </td>
    </tr>
</table>

<table class="danfe" cellspacing="0" cellpadding="0" style="margin-top: -1px;">
    <tr>
        <td style="width: 72%;">
            <span class="danfe__label">INFORMAÇÕES COMPLEMENTARES</span>
            <div class="danfe__info-box">{{ $informacoesComplementares }}</div>
        </td>
        <td>
            <span class="danfe__label">RESERVADO AO FISCO</span>
            <div class="danfe__info-box">{{ $informacoesFisco !== '' ? $informacoesFisco : '&nbsp;' }}</div>
        </td>
    </tr>
</table>
