@if ($this->nfeHistoricoModalOpen)
    @php
        $cabecalho = $this->nfeHistoricoData['cabecalho'] ?? [];
        $destinatario = $this->nfeHistoricoData['destinatario'] ?? [];
        $itens = $this->nfeHistoricoData['itens'] ?? [];
        $totais = $this->nfeHistoricoData['totais'] ?? [];
        $transporte = $this->nfeHistoricoData['transporte'] ?? [];
        $faturas = $this->nfeHistoricoData['faturas'] ?? [];
        $observacoes = $this->nfeHistoricoData['observacoes'] ?? [];
        $eventos = $this->nfeHistoricoData['eventos'] ?? [];
    @endphp

    <div
        class="erp-lookup-modal erp-nfe-historico-modal"
        wire:keydown.escape.window="closeNfeHistorico"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeHistorico"></div>

        <div
            class="erp-lookup-modal__window erp-nfe-historico-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-nfe-historico-modal-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-historico-modal-title">{{ $this->nfeHistoricoData['titulo'] ?? 'Histórico da NF-e' }}</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeNfeHistorico"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-historico-modal__body">
                <section class="erp-nfe-historico-modal__card erp-nfe-historico-modal__card--resumo">
                    <div class="erp-nfe-historico-modal__card-head">
                        <h3 class="erp-nfe-historico-modal__section-title">Identificação</h3>
                        <span @class([
                            'erp-nfe-historico-modal__status',
                            'erp-nfe-historico-modal__status--' . ($cabecalho['status_codigo'] ?? 'aberta'),
                        ])>{{ $cabecalho['status'] ?? '—' }}</span>
                    </div>

                    <div class="erp-nfe-historico-modal__resumo">
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Número / Série</span>
                            <span class="erp-nfe-historico-modal__value">{{ ($cabecalho['numero'] ?? '—') . ' / ' . ($cabecalho['serie'] ?? '—') }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Modelo</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['modelo'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Emissão</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['emissao'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Saída</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['saida'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">CFOP</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['cfop'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Finalidade</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['finalidade'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Movimento</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['movimento'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Pedido</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['pedido'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Venda</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['venda'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Forma / Meio</span>
                            <span class="erp-nfe-historico-modal__value">{{ ($cabecalho['forma_pgto'] ?? '—') . ' / ' . ($cabecalho['meio_pgto'] ?? '—') }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Itens</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['qtd_itens'] ?? '0' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Total</span>
                            <span class="erp-nfe-historico-modal__value erp-nfe-historico-modal__value--total">R$ {{ $cabecalho['total'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field erp-nfe-historico-modal__field--inline">
                            <span class="erp-nfe-historico-modal__label">Chave de acesso</span>
                            <span class="erp-nfe-historico-modal__value erp-nfe-historico-modal__value--mono">{{ $cabecalho['chave'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field erp-nfe-historico-modal__field--inline">
                            <span class="erp-nfe-historico-modal__label">Protocolo</span>
                            <span class="erp-nfe-historico-modal__value erp-nfe-historico-modal__value--mono">{{ $cabecalho['protocolo'] ?? '—' }}</span>
                        </div>
                    </div>
                </section>

                <section class="erp-nfe-historico-modal__card">
                    <h3 class="erp-nfe-historico-modal__section-title">Destinatário</h3>
                    <div class="erp-nfe-historico-modal__resumo erp-nfe-historico-modal__resumo--dest">
                        <div class="erp-nfe-historico-modal__field erp-nfe-historico-modal__field--wide">
                            <span class="erp-nfe-historico-modal__label">Nome / Razão social</span>
                            <span class="erp-nfe-historico-modal__value">{{ $destinatario['nome'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">CPF / CNPJ</span>
                            <span class="erp-nfe-historico-modal__value">{{ $destinatario['documento'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">IE</span>
                            <span class="erp-nfe-historico-modal__value">{{ $destinatario['ie'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field erp-nfe-historico-modal__field--wide">
                            <span class="erp-nfe-historico-modal__label">Endereço</span>
                            <span class="erp-nfe-historico-modal__value">{{ $destinatario['endereco'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Bairro</span>
                            <span class="erp-nfe-historico-modal__value">{{ $destinatario['bairro'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Cidade / UF</span>
                            <span class="erp-nfe-historico-modal__value">{{ ($destinatario['cidade'] ?? '—') . ' / ' . ($destinatario['uf'] ?? '—') }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">CEP</span>
                            <span class="erp-nfe-historico-modal__value">{{ $destinatario['cep'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Telefone</span>
                            <span class="erp-nfe-historico-modal__value">{{ $destinatario['fone'] ?? '—' }}</span>
                        </div>
                    </div>
                </section>

                <section class="erp-nfe-historico-modal__card">
                    <div class="erp-nfe-historico-modal__card-head">
                        <h3 class="erp-nfe-historico-modal__section-title">Produtos</h3>
                        <span class="erp-nfe-historico-modal__count">{{ count($itens) }} {{ count($itens) === 1 ? 'item' : 'itens' }}</span>
                    </div>

                    <div class="erp-nfe-historico-modal__table-wrap">
                        <table class="erp-nfe-historico-modal__table">
                            <thead>
                                <tr>
                                    <th class="is-num">#</th>
                                    <th>Código</th>
                                    <th class="is-desc">Descrição</th>
                                    <th>NCM</th>
                                    <th>CST</th>
                                    <th>CFOP</th>
                                    <th>UN</th>
                                    <th class="is-right">Qtde</th>
                                    <th class="is-right">Vlr. unit.</th>
                                    <th class="is-right">Desc.</th>
                                    <th class="is-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($itens as $item)
                                    <tr>
                                        <td class="is-num">{{ $item['item'] ?: '—' }}</td>
                                        <td>{{ $item['codigo'] }}</td>
                                        <td class="is-desc" title="{{ $item['descricao'] }}">{{ $item['descricao'] }}</td>
                                        <td>{{ $item['ncm'] }}</td>
                                        <td>{{ $item['cst'] }}</td>
                                        <td>{{ $item['cfop'] }}</td>
                                        <td>{{ $item['un'] }}</td>
                                        <td class="is-right">{{ $item['quant'] }}</td>
                                        <td class="is-right">{{ $item['valor_unit'] }}</td>
                                        <td class="is-right">{{ $item['desconto'] }}</td>
                                        <td class="is-right is-total">{{ $item['valor_total'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="erp-nfe-historico-modal__empty-cell">Nenhum produto nesta NF-e.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="erp-nfe-historico-modal__card">
                    <h3 class="erp-nfe-historico-modal__section-title">Totais</h3>
                    <div class="erp-nfe-historico-modal__totais">
                        <div class="erp-nfe-historico-modal__total-item">
                            <span>Produtos</span>
                            <strong>R$ {{ $totais['produtos'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-nfe-historico-modal__total-item">
                            <span>Desconto</span>
                            <strong>R$ {{ $totais['desconto'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-nfe-historico-modal__total-item">
                            <span>Frete</span>
                            <strong>R$ {{ $totais['frete'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-nfe-historico-modal__total-item">
                            <span>Seguro</span>
                            <strong>R$ {{ $totais['seguro'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-nfe-historico-modal__total-item">
                            <span>Outras</span>
                            <strong>R$ {{ $totais['outras'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-nfe-historico-modal__total-item">
                            <span>Base ICMS</span>
                            <strong>R$ {{ $totais['base_icms'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-nfe-historico-modal__total-item">
                            <span>ICMS</span>
                            <strong>R$ {{ $totais['icms'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-nfe-historico-modal__total-item">
                            <span>IPI</span>
                            <strong>R$ {{ $totais['ipi'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-nfe-historico-modal__total-item">
                            <span>PIS</span>
                            <strong>R$ {{ $totais['pis'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-nfe-historico-modal__total-item">
                            <span>COFINS</span>
                            <strong>R$ {{ $totais['cofins'] ?? '0,00' }}</strong>
                        </div>
                        <div class="erp-nfe-historico-modal__total-item erp-nfe-historico-modal__total-item--nota">
                            <span>Total da nota</span>
                            <strong>R$ {{ $totais['nota'] ?? ($cabecalho['total'] ?? '0,00') }}</strong>
                        </div>
                    </div>
                </section>

                <section class="erp-nfe-historico-modal__card">
                    <h3 class="erp-nfe-historico-modal__section-title">Transporte</h3>
                    <div class="erp-nfe-historico-modal__resumo erp-nfe-historico-modal__resumo--transp">
                        <div class="erp-nfe-historico-modal__field erp-nfe-historico-modal__field--wide">
                            <span class="erp-nfe-historico-modal__label">Transportadora</span>
                            <span class="erp-nfe-historico-modal__value">{{ $transporte['transportadora'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Tipo frete</span>
                            <span class="erp-nfe-historico-modal__value">{{ $transporte['tipo_frete'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Placa / UF</span>
                            <span class="erp-nfe-historico-modal__value">{{ ($transporte['placa'] ?? '—') . ' / ' . ($transporte['uf_placa'] ?? '—') }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">RNTC</span>
                            <span class="erp-nfe-historico-modal__value">{{ $transporte['rntc'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Espécie</span>
                            <span class="erp-nfe-historico-modal__value">{{ $transporte['especie'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Volumes</span>
                            <span class="erp-nfe-historico-modal__value">{{ $transporte['volumes'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Peso bruto</span>
                            <span class="erp-nfe-historico-modal__value">{{ $transporte['peso_bruto'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Peso líquido</span>
                            <span class="erp-nfe-historico-modal__value">{{ $transporte['peso_liquido'] ?? '—' }}</span>
                        </div>
                    </div>
                </section>

                @if ($faturas !== [])
                    <section class="erp-nfe-historico-modal__card">
                        <h3 class="erp-nfe-historico-modal__section-title">Duplicatas</h3>
                        <div class="erp-nfe-historico-modal__table-wrap">
                            <table class="erp-nfe-historico-modal__table erp-nfe-historico-modal__table--compact">
                                <thead>
                                    <tr>
                                        <th>Parcela</th>
                                        <th>Vencimento</th>
                                        <th class="is-right">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($faturas as $fatura)
                                        <tr>
                                            <td>{{ $fatura['numero'] }}</td>
                                            <td>{{ $fatura['vencimento'] }}</td>
                                            <td class="is-right is-total">R$ {{ $fatura['valor'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                @if (filled($observacoes['fisco'] ?? null) || filled($observacoes['contribuinte'] ?? null))
                    <section class="erp-nfe-historico-modal__card">
                        <h3 class="erp-nfe-historico-modal__section-title">Observações</h3>
                        @if (filled($observacoes['fisco'] ?? null))
                            <div class="erp-nfe-historico-modal__obs">
                                <span class="erp-nfe-historico-modal__label">Fisco</span>
                                <p>{{ $observacoes['fisco'] }}</p>
                            </div>
                        @endif
                        @if (filled($observacoes['contribuinte'] ?? null))
                            <div class="erp-nfe-historico-modal__obs">
                                <span class="erp-nfe-historico-modal__label">Contribuinte</span>
                                <p>{{ $observacoes['contribuinte'] }}</p>
                            </div>
                        @endif
                    </section>
                @endif

                <section class="erp-nfe-historico-modal__card">
                    <h3 class="erp-nfe-historico-modal__section-title">Linha do tempo</h3>

                    <div class="erp-nfe-historico-modal__timeline">
                        @forelse ($eventos as $evento)
                            <article @class([
                                'erp-nfe-historico-modal__event',
                                'erp-nfe-historico-modal__event--' . ($evento['cor'] ?? 'cinza'),
                            ])>
                                <div class="erp-nfe-historico-modal__event-marker" aria-hidden="true"></div>
                                <div class="erp-nfe-historico-modal__event-card">
                                    <div class="erp-nfe-historico-modal__event-head">
                                        <span class="erp-nfe-historico-modal__event-badge">{{ $evento['tipo_label'] ?? 'Evento' }}</span>
                                        <time class="erp-nfe-historico-modal__event-time">{{ $evento['data_hora'] ?? '—' }}</time>
                                    </div>
                                    <h4 class="erp-nfe-historico-modal__event-title">{{ $evento['titulo'] ?? '—' }}</h4>
                                    @if (filled($evento['descricao'] ?? null))
                                        <p class="erp-nfe-historico-modal__event-desc">{{ $evento['descricao'] }}</p>
                                    @endif
                                    @if (filled($evento['destinatario'] ?? null))
                                        <p class="erp-nfe-historico-modal__event-dest">
                                            Destinatário: <strong>{{ $evento['destinatario'] }}</strong>
                                        </p>
                                    @endif
                                    <p class="erp-nfe-historico-modal__event-user">Usuário: {{ $evento['usuario'] ?? '—' }}</p>
                                </div>
                            </article>
                        @empty
                            <p class="erp-nfe-historico-modal__empty">Nenhum evento registrado para esta NF-e.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
@endif
