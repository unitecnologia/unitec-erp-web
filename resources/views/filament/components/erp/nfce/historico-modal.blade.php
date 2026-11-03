@if ($this->nfceHistoricoModalOpen)
    @php
        $cabecalho = $this->nfceHistoricoData['cabecalho'] ?? [];
        $destinatario = $this->nfceHistoricoData['destinatario'] ?? [];
        $itens = $this->nfceHistoricoData['itens'] ?? [];
        $totais = $this->nfceHistoricoData['totais'] ?? [];
        $pagamentos = $this->nfceHistoricoData['pagamentos'] ?? [];
        $observacoes = $this->nfceHistoricoData['observacoes'] ?? [];
    @endphp

    <div
        class="erp-lookup-modal erp-nfe-historico-modal erp-nfe-historico-modal--nfce"
        wire:keydown.escape.window="closeNfceHistorico"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfceHistorico"></div>

        <div
            class="erp-lookup-modal__window erp-nfe-historico-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-nfce-historico-modal-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfce-historico-modal-title">{{ $this->nfceHistoricoData['titulo'] ?? 'Detalhe da NFC-e' }}</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeNfceHistorico"
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

                    <div class="erp-nfe-historico-modal__resumo erp-nfe-historico-modal__resumo--nfce">
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
                            <span class="erp-nfe-historico-modal__label">Autorização</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['autorizada_em'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Ambiente</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['ambiente'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Empresa</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['empresa'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Caixa</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['caixa'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Vendedor</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['vendedor'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Pedido</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['pedido'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Nº DAV</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['dav'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Forma / Meio</span>
                            <span class="erp-nfe-historico-modal__value">{{ $cabecalho['forma_pgto'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Itens / Total</span>
                            <span class="erp-nfe-historico-modal__value erp-nfe-historico-modal__value--total">{{ $cabecalho['qtd_itens'] ?? '0' }} · R$ {{ $cabecalho['total'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field erp-nfe-historico-modal__field--chave">
                            <span class="erp-nfe-historico-modal__label">Chave</span>
                            <span class="erp-nfe-historico-modal__value erp-nfe-historico-modal__value--mono">{{ $cabecalho['chave'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field erp-nfe-historico-modal__field--proto">
                            <span class="erp-nfe-historico-modal__label">Protocolo</span>
                            <span class="erp-nfe-historico-modal__value erp-nfe-historico-modal__value--mono">{{ $cabecalho['protocolo'] ?? '—' }}</span>
                        </div>
                    </div>
                </section>

                <section class="erp-nfe-historico-modal__card erp-nfe-historico-modal__card--dest">
                    <div class="erp-nfe-historico-modal__card-head">
                        <h3 class="erp-nfe-historico-modal__section-title">Destinatário</h3>
                    </div>
                    <div class="erp-nfe-historico-modal__resumo erp-nfe-historico-modal__resumo--dest-nfce">
                        <div class="erp-nfe-historico-modal__field erp-nfe-historico-modal__field--nome">
                            <span class="erp-nfe-historico-modal__label">Nome</span>
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
                        <div class="erp-nfe-historico-modal__field">
                            <span class="erp-nfe-historico-modal__label">Fone</span>
                            <span class="erp-nfe-historico-modal__value">{{ $destinatario['fone'] ?? '—' }}</span>
                        </div>
                        <div class="erp-nfe-historico-modal__field erp-nfe-historico-modal__field--end">
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
                    </div>
                </section>

                <section class="erp-nfe-historico-modal__card erp-nfe-historico-modal__card--produtos">
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
                                        <td>{{ $item['un'] }}</td>
                                        <td class="is-right">{{ $item['quant'] }}</td>
                                        <td class="is-right">{{ $item['valor_unit'] }}</td>
                                        <td class="is-right">{{ $item['desconto'] }}</td>
                                        <td class="is-right is-total">{{ $item['valor_total'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="erp-nfe-historico-modal__empty-cell">Nenhum produto nesta NFC-e.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="erp-nfe-historico-modal__rodape">
                    <section class="erp-nfe-historico-modal__card erp-nfe-historico-modal__card--totais">
                        <h3 class="erp-nfe-historico-modal__section-title">Totais</h3>
                        <div class="erp-nfe-historico-modal__totais erp-nfe-historico-modal__totais--nfce">
                            <div class="erp-nfe-historico-modal__total-item">
                                <span>Produtos</span>
                                <strong>R$ {{ $totais['produtos'] ?? '0,00' }}</strong>
                            </div>
                            <div class="erp-nfe-historico-modal__total-item">
                                <span>Desconto</span>
                                <strong>R$ {{ $totais['desconto'] ?? '0,00' }}</strong>
                            </div>
                            <div class="erp-nfe-historico-modal__total-item">
                                <span>Acréscimo</span>
                                <strong>R$ {{ $totais['acrescimo'] ?? '0,00' }}</strong>
                            </div>
                            <div class="erp-nfe-historico-modal__total-item erp-nfe-historico-modal__total-item--nota">
                                <span>Total da nota</span>
                                <strong>R$ {{ $totais['nota'] ?? ($cabecalho['total'] ?? '0,00') }}</strong>
                            </div>
                        </div>
                    </section>

                    @if ($pagamentos !== [])
                        <section class="erp-nfe-historico-modal__card erp-nfe-historico-modal__card--pag">
                            <h3 class="erp-nfe-historico-modal__section-title">Pagamentos</h3>
                            <div class="erp-nfe-historico-modal__pag-list">
                                @foreach ($pagamentos as $pag)
                                    <div class="erp-nfe-historico-modal__pag-item">
                                        <span>{{ $pag['forma'] }}</span>
                                        <strong>R$ {{ $pag['valor'] }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>

                @if (filled($observacoes['venda'] ?? null) || filled($observacoes['rejeicao'] ?? null) || filled($observacoes['contingencia'] ?? null))
                    <section class="erp-nfe-historico-modal__card">
                        <h3 class="erp-nfe-historico-modal__section-title">Observações</h3>
                        @if (filled($observacoes['venda'] ?? null))
                            <div class="erp-nfe-historico-modal__obs">
                                <span class="erp-nfe-historico-modal__label">Venda</span>
                                <p>{{ $observacoes['venda'] }}</p>
                            </div>
                        @endif
                        @if (filled($observacoes['rejeicao'] ?? null))
                            <div class="erp-nfe-historico-modal__obs">
                                <span class="erp-nfe-historico-modal__label">Motivo SEFAZ</span>
                                <p>{{ $observacoes['rejeicao'] }}</p>
                            </div>
                        @endif
                        @if (filled($observacoes['contingencia'] ?? null))
                            <div class="erp-nfe-historico-modal__obs">
                                <span class="erp-nfe-historico-modal__label">Contingência</span>
                                <p>{{ $observacoes['contingencia'] }}</p>
                            </div>
                        @endif
                    </section>
                @endif
            </div>
        </div>
    </div>
@endif
