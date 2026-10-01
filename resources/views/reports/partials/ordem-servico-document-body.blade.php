@php
    /** @var \App\Models\OrdemServico $ordem */
    /** @var \App\Models\Empresa|null $empresa */
    $tecnica = (bool) ($tecnica ?? false);
@endphp
<div class="os-doc{{ $tecnica ? ' os-doc--tecnica' : '' }}">
    <div class="os-doc__frame">
        <table class="os-doc__header">
            <tr>
                <td style="width: 24mm;">
                    <div class="os-doc__logo">
                        @if (! empty($logoDataUri))
                            <img src="{{ $logoDataUri }}" alt="Logo">
                        @endif
                    </div>
                </td>
                <td style="padding-left: 3mm;">
                    <p class="os-doc__company-name">{{ mb_strtoupper($empresa?->razao_social ?: $empresa?->nome ?: $empresa?->fantasia ?: 'EMPRESA', 'UTF-8') }}</p>
                    @if (filled($empresa?->fantasia) && filled($empresa?->razao_social ?: $empresa?->nome) && mb_strtoupper((string) $empresa->fantasia, 'UTF-8') !== mb_strtoupper((string) ($empresa->razao_social ?: $empresa->nome), 'UTF-8'))
                        <p class="os-doc__company-meta">{{ mb_strtoupper((string) $empresa->fantasia, 'UTF-8') }}</p>
                    @endif
                    <p class="os-doc__company-meta">CNPJ {{ $empresa?->cnpj ?: '—' }}</p>
                    @if (filled($empresa?->telefone))
                        <p class="os-doc__company-meta">Tel. {{ $empresa->telefone }}</p>
                    @endif
                    @if ($empresaEndereco !== '')
                        <p class="os-doc__company-meta">{{ $empresaEndereco }}</p>
                    @endif
                </td>
                <td class="os-doc__title-box" style="width: 52mm;">
                    <p class="os-doc__title">{{ $tecnica ? 'OS TÉCNICA' : 'ORDEM DE SERVIÇO' }}</p>
                    <p class="os-doc__os-num">Nº {{ $numero }}</p>
                    <span class="os-doc__status">{{ $statusLabel }}</span>
                </td>
            </tr>
        </table>

        <table class="os-doc__meta">
            <tr>
                <td><strong>Abertura:</strong> {{ $abertura !== '' ? $abertura : '—' }}</td>
                <td><strong>Conclusão / Entrega:</strong> {{ $conclusao !== '' ? $conclusao : '—' }}</td>
            </tr>
        </table>

        <div class="os-doc__section">
            <div class="os-doc__section-title">Dados do cliente</div>
            <div class="os-doc__section-body">
                <table class="os-doc__kv">
                    <tr>
                        <td class="os-doc__kv-label">Nome</td>
                        <td>{{ mb_strtoupper($ordem->clienteNome(), 'UTF-8') }}</td>
                    </tr>
                    @unless ($tecnica)
                        <tr>
                            <td class="os-doc__kv-label">CPF / CNPJ</td>
                            <td>{{ filled($clienteDocumento) ? $clienteDocumento : '—' }}</td>
                        </tr>
                        <tr>
                            <td class="os-doc__kv-label">Telefone</td>
                            <td>{{ filled($clienteTelefone) ? $clienteTelefone : '—' }}</td>
                        </tr>
                        @if ($clienteEmail !== '')
                            <tr>
                                <td class="os-doc__kv-label">E-mail</td>
                                <td>{{ $clienteEmail }}</td>
                            </tr>
                        @endif
                        @if ($clienteEndereco !== '')
                            <tr>
                                <td class="os-doc__kv-label">Endereço</td>
                                <td>{{ $clienteEndereco }}</td>
                            </tr>
                        @endif
                    @endunless
                </table>
            </div>
        </div>

        @if ($tecnica)
            @include('reports.partials.ordem-servico-document-tecnico')
        @endif

        @if ($equipamentoLinhas !== [])
            <div class="os-doc__section">
                <div class="os-doc__section-title">Equipamento</div>
                <div class="os-doc__section-body">
                    <div class="os-doc__equip">
                        @foreach ($equipamentoLinhas as $linha)
                            <table class="os-doc__equip-row">
                                <tr>
                                    @foreach ($linha as $campo)
                                        <td>
                                            <span class="os-doc__equip-label">{{ $campo['label'] }}</span>
                                            {{ $campo['value'] }}
                                        </td>
                                    @endforeach
                                </tr>
                            </table>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="os-doc__section">
            <div class="os-doc__section-title">Problema relatado pelo cliente</div>
            <div class="os-doc__section-body">
                @if (filled($ordem->problema))
                    <p class="os-doc__text">{{ $ordem->problema }}</p>
                @else
                    <p class="os-doc__empty">Não informado.</p>
                @endif
            </div>
        </div>

        <div class="os-doc__section">
            <div class="os-doc__section-title">Serviços</div>
            <div class="os-doc__section-body" style="padding: 0;">
                @if ($servicos === [])
                    <p class="os-doc__empty" style="padding: 3px 5px;">Nenhum serviço lançado.</p>
                @else
                    <table class="os-doc__table">
                        <thead>
                            <tr>
                                @if ($tecnica)
                                    <th style="width: 14%;" class="center">Qtd</th>
                                    <th>Descrição</th>
                                @else
                                    <th style="width: 12%;">Código</th>
                                    <th style="width: 34%;">Descrição</th>
                                    <th style="width: 8%;" class="center">Qtd</th>
                                    <th style="width: 13%;" class="num">Unitário</th>
                                    <th style="width: 11%;" class="num">Desc.</th>
                                    <th style="width: 11%;" class="num">Acrés.</th>
                                    <th style="width: 11%;" class="num">Total</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($servicos as $item)
                                <tr>
                                    @if ($tecnica)
                                        <td class="center">{{ $item['qtd'] }}</td>
                                        <td>{{ $item['descricao'] }}</td>
                                    @else
                                        <td>{{ $item['codigo'] !== '' ? $item['codigo'] : '—' }}</td>
                                        <td>{{ $item['descricao'] }}</td>
                                        <td class="center">{{ $item['qtd'] }}</td>
                                        <td class="num">{{ $item['unitario'] }}</td>
                                        <td class="num">{{ $item['desconto'] }}</td>
                                        <td class="num">{{ $item['acrescimo'] }}</td>
                                        <td class="num">{{ $item['total'] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        @unless ($tecnica)
            <div class="os-doc__section">
                <div class="os-doc__section-title">Serviços prestados</div>
                <div class="os-doc__section-body">
                    @if (filled($ordem->laudo))
                        <p class="os-doc__text">{{ $ordem->laudo }}</p>
                    @else
                        <p class="os-doc__empty">Não informado.</p>
                    @endif
                </div>
            </div>
        @endunless

        <div class="os-doc__section">
            <div class="os-doc__section-title">Peças / produtos utilizados</div>
            <div class="os-doc__section-body" style="padding: 0;">
                @if ($pecas === [])
                    <p class="os-doc__empty" style="padding: 3px 5px;">Nenhuma peça/produto lançado.</p>
                @else
                    <table class="os-doc__table">
                        <thead>
                            <tr>
                                @if ($tecnica)
                                    <th style="width: 14%;" class="center">Qtd</th>
                                    <th>Descrição</th>
                                @else
                                    <th style="width: 10%;">Código</th>
                                    <th style="width: 26%;">Descrição</th>
                                    <th style="width: 14%;">EAN</th>
                                    <th style="width: 7%;" class="center">Qtd</th>
                                    <th style="width: 11%;" class="num">Unitário</th>
                                    <th style="width: 10%;" class="num">Desc.</th>
                                    <th style="width: 10%;" class="num">Acrés.</th>
                                    <th style="width: 12%;" class="num">Total</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pecas as $item)
                                <tr>
                                    @if ($tecnica)
                                        <td class="center">{{ $item['qtd'] }}</td>
                                        <td>{{ $item['descricao'] }}</td>
                                    @else
                                        <td>{{ $item['codigo'] !== '' ? $item['codigo'] : '—' }}</td>
                                        <td>{{ $item['descricao'] }}</td>
                                        <td>{{ $item['ean'] !== '' ? $item['ean'] : '—' }}</td>
                                        <td class="center">{{ $item['qtd'] }}</td>
                                        <td class="num">{{ $item['unitario'] }}</td>
                                        <td class="num">{{ $item['desconto'] }}</td>
                                        <td class="num">{{ $item['acrescimo'] }}</td>
                                        <td class="num">{{ $item['total'] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        @unless ($tecnica)
            <div class="os-doc__section">
                <table class="os-doc__totals-layout">
                    <tr>
                        <td class="os-doc__pagamentos-cell">
                            @if (! empty($pagamentos))
                                <div class="os-doc__pagamentos">
                                    <div class="os-doc__pagamentos-title">Meio de pagamento</div>
                                    @foreach ($pagamentos as $pagamento)
                                        <div class="os-doc__pagamento-item">
                                            <div class="os-doc__pagamento-forma">
                                                <strong>{{ $pagamento['forma'] }}:</strong> {{ $pagamento['valor'] }}
                                            </div>
                                            @if (! empty($pagamento['parcelas']))
                                                <div class="os-doc__pagamento-parcelas">
                                                    @foreach ($pagamento['parcelas'] as $parcela)
                                                        <span class="os-doc__pagamento-parcela">
                                                            {{ (int) ($parcela['dias'] ?? 0) }} {{ $parcela['vencimento'] ?? '—' }}
                                                            @if (! empty($parcela['valor']))
                                                                <span class="os-doc__pagamento-parcela-valor">({{ $parcela['valor'] }})</span>
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="os-doc__totals-cell">
                            <table class="os-doc__totals">
                                @if (! empty($totais['subtotal_servicos']))
                                    <tr>
                                        <td>Subtotal serviços</td>
                                        <td>{{ $totais['subtotal_servicos'] }}</td>
                                    </tr>
                                @endif
                                @if (! empty($totais['acrescimo_servicos']))
                                    <tr>
                                        <td>Acréscimo serviços</td>
                                        <td>{{ $totais['acrescimo_servicos'] }}</td>
                                    </tr>
                                @endif
                                @if (! empty($totais['desconto_servicos']))
                                    <tr>
                                        <td>Desconto serviços</td>
                                        <td>{{ $totais['desconto_servicos'] }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>Total serviços</td>
                                    <td>{{ $totais['servicos'] }}</td>
                                </tr>
                                @if (! empty($totais['subtotal_produtos']))
                                    <tr>
                                        <td>Subtotal peças/produtos</td>
                                        <td>{{ $totais['subtotal_produtos'] }}</td>
                                    </tr>
                                @endif
                                @if (! empty($totais['acrescimo_produtos']))
                                    <tr>
                                        <td>Acréscimo peças/produtos</td>
                                        <td>{{ $totais['acrescimo_produtos'] }}</td>
                                    </tr>
                                @endif
                                @if (! empty($totais['desconto_produtos']))
                                    <tr>
                                        <td>Desconto peças/produtos</td>
                                        <td>{{ $totais['desconto_produtos'] }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>Total peças/produtos</td>
                                    <td>{{ $totais['produtos'] }}</td>
                                </tr>
                                @if (! empty($totais['desconto']) && empty($totais['desconto_servicos']) && empty($totais['desconto_produtos']))
                                    <tr>
                                        <td>Descontos</td>
                                        <td>{{ $totais['desconto'] }}</td>
                                    </tr>
                                @endif
                                <tr class="os-doc__totals-geral">
                                    <td>Total da OS</td>
                                    <td>{{ $totais['geral'] }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>
        @endunless

        @if ($tecnica || filled($ordem->observacoes))
            <div class="os-doc__section">
                <div class="os-doc__section-title">Observações</div>
                <div class="os-doc__section-body">
                    @if (filled($ordem->observacoes))
                        <p class="os-doc__text">{{ $ordem->observacoes }}</p>
                    @else
                        <p class="os-doc__empty">Não informado.</p>
                    @endif
                </div>
            </div>
        @endif

        @unless ($tecnica)
            @include('reports.partials.ordem-servico-document-tecnico')
        @endunless

        @if ($tecnica)
            <div class="os-doc__section os-doc__section--write">
                <div class="os-doc__section-title">Diagnóstico / Serviço executado</div>
                <div class="os-doc__section-body os-doc__write-body">
                    <table class="os-doc__write-lines">
                        @for ($i = 0; $i < 4; $i++)
                            <tr><td>&nbsp;</td></tr>
                        @endfor
                    </table>
                </div>
            </div>

            <div class="os-doc__section os-doc__section--write">
                <div class="os-doc__section-title">Peças / Serviços adicionais</div>
                <div class="os-doc__section-body os-doc__write-body">
                    <table class="os-doc__write-lines">
                        @for ($i = 0; $i < 3; $i++)
                            <tr><td>&nbsp;</td></tr>
                        @endfor
                    </table>
                </div>
            </div>
        @endif

        @unless ($tecnica)
            @if ($fotos !== [])
                <div class="os-doc__section" style="page-break-before: auto;">
                    <div class="os-doc__section-title">Registro fotográfico</div>
                    <div class="os-doc__section-body">
                        <table class="os-doc__photos">
                            @foreach (array_chunk($fotos, 3) as $row)
                                <tr>
                                    @foreach ($row as $foto)
                                        <td><img src="{{ $foto['data_uri'] }}" alt="Foto da OS"></td>
                                    @endforeach
                                    @for ($i = count($row); $i < 3; $i++)
                                        <td></td>
                                    @endfor
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            @endif

            @if (! empty($assinatura['data_uri']))
                <div class="os-doc__section">
                    <div class="os-doc__section-title">Assinatura do cliente</div>
                    <div class="os-doc__section-body">
                        <img src="{{ $assinatura['data_uri'] }}" alt="Assinatura" class="os-doc__sign-img">
                        <div style="font-size: 8pt; margin-top: 2mm;">{{ mb_strtoupper($ordem->clienteNome(), 'UTF-8') }}</div>
                        @if (! empty($assinatura['em']))
                            <div style="font-size: 7.5pt; color: #64748b;">{{ $assinatura['em'] }}</div>
                        @endif
                    </div>
                </div>
            @endif
        @endunless

        <table class="os-doc__footer">
            <tr>
                <td>{{ $tecnica ? 'OS Técnica' : 'OS' }} {{ $numero }}</td>
                <td style="text-align: center;">Gerado em {{ $printedAt->format('d/m/Y H:i') }}</td>
                <td style="text-align: right;">Unitec ERP</td>
            </tr>
        </table>
    </div>
</div>
