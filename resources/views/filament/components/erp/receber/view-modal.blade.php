@if ($this->viewModalOpen)
    @php
        $conta = $this->viewModalData['conta'] ?? [];
        $cliente = $this->viewModalData['cliente'] ?? [];
        $itens = $this->viewModalData['itens'] ?? [];
        $parcelas = $this->viewModalData['parcelas'] ?? [];
        $totais = $this->viewModalData['totais'] ?? [];
    @endphp

    <div
        class="erp-lookup-modal erp-receber-view-modal"
        wire:keydown.escape.window="closeContaReceberView"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeContaReceberView"></div>

        <div
            class="erp-lookup-modal__window erp-receber-view-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-receber-view-modal-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-receber-view-modal-title">{{ $this->viewModalData['titulo'] ?? 'Conta a receber' }}</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeContaReceberView"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-receber-view-modal__body">
                <div class="erp-receber-view-modal__section">
                    <h3 class="erp-receber-view-modal__section-title">Origem da venda</h3>
                    <div class="erp-receber-view-modal__fields">
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Tipo</span>
                            <span class="erp-receber-view-modal__value">{{ $this->viewModalData['origem'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Referência</span>
                            <span class="erp-receber-view-modal__value">{{ $this->viewModalData['origem_detalhe'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Vendedor</span>
                            <span class="erp-receber-view-modal__value">{{ $this->viewModalData['vendedor'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Forma pagto.</span>
                            <span class="erp-receber-view-modal__value">{{ $this->viewModalData['forma_pagamento'] ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <div class="erp-receber-view-modal__section">
                    <h3 class="erp-receber-view-modal__section-title">Cliente</h3>
                    <div class="erp-receber-view-modal__fields">
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Nome</span>
                            <span class="erp-receber-view-modal__value">{{ $cliente['nome'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">CPF/CNPJ</span>
                            <span class="erp-receber-view-modal__value">{{ $cliente['cpf_cnpj'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Telefone</span>
                            <span class="erp-receber-view-modal__value">{{ $cliente['fone'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Cidade/UF</span>
                            <span class="erp-receber-view-modal__value">{{ ($cliente['cidade'] ?? '—') . ' / ' . ($cliente['uf'] ?? '—') }}</span>
                        </div>
                    </div>
                </div>

                <div class="erp-receber-view-modal__section erp-receber-view-modal__section--titulo">
                    <h3 class="erp-receber-view-modal__section-title">Título selecionado</h3>
                    <div class="erp-receber-view-modal__fields">
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Número</span>
                            <span class="erp-receber-view-modal__value">{{ $conta['numero'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Documento</span>
                            <span class="erp-receber-view-modal__value">{{ $conta['documento'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field erp-receber-view-modal__field--wide">
                            <span class="erp-receber-view-modal__label">Histórico</span>
                            <span class="erp-receber-view-modal__value">{{ $conta['historico'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Emissão</span>
                            <span class="erp-receber-view-modal__value">{{ $conta['emissao'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Vencimento</span>
                            <span class="erp-receber-view-modal__value">{{ $conta['vencimento'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Forma</span>
                            <span class="erp-receber-view-modal__value">{{ $conta['forma'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Valor</span>
                            <span class="erp-receber-view-modal__value">{{ $conta['valor'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Desconto</span>
                            <span class="erp-receber-view-modal__value">{{ $conta['desconto'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Juros</span>
                            <span class="erp-receber-view-modal__value">{{ $conta['juros'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Recebido</span>
                            <span class="erp-receber-view-modal__value">{{ $conta['valor_recebido'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Recebido em</span>
                            <span class="erp-receber-view-modal__value">{{ $conta['recebido_em'] ?? '—' }}</span>
                        </div>
                        <div class="erp-receber-view-modal__field">
                            <span class="erp-receber-view-modal__label">Saldo</span>
                            <span class="erp-receber-view-modal__value erp-receber-view-modal__value--saldo">{{ $conta['saldo'] ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <div class="erp-receber-view-modal__section">
                    <h3 class="erp-receber-view-modal__section-title">Produtos</h3>
                    <div class="erp-lookup-modal__grid-wrap erp-receber-view-modal__grid-wrap">
                        <table class="erp-lookup-modal__grid erp-receber-view-modal__grid">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Cód.</th>
                                    <th>Produto</th>
                                    <th class="erp-receber-view-modal__num">Qtd</th>
                                    <th>Un.</th>
                                    <th class="erp-receber-view-modal__num">Preço</th>
                                    <th class="erp-receber-view-modal__num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($itens as $item)
                                    <tr>
                                        <td class="erp-receber-view-modal__center">{{ $item['item'] ?? '' }}</td>
                                        <td class="erp-receber-view-modal__center">{{ $item['codigo'] ?? '—' }}</td>
                                        <td>{{ $item['descricao'] ?? '—' }}</td>
                                        <td class="erp-receber-view-modal__num">{{ $item['quantidade'] ?? '—' }}</td>
                                        <td class="erp-receber-view-modal__center">{{ $item['unidade'] ?? '—' }}</td>
                                        <td class="erp-receber-view-modal__num">{{ $item['preco'] ?? '—' }}</td>
                                        <td class="erp-receber-view-modal__num">{{ $item['total'] ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="erp-lookup-modal__empty">
                                            Nenhum produto vinculado a este título.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="erp-receber-view-modal__totais">
                        <span>Subtotal: <strong>{{ $totais['subtotal'] ?? '—' }}</strong></span>
                        <span>Desconto: <strong>{{ $totais['desconto'] ?? '—' }}</strong></span>
                        <span>Total: <strong>{{ $totais['total'] ?? '—' }}</strong></span>
                    </div>
                </div>

                <div class="erp-receber-view-modal__section">
                    <h3 class="erp-receber-view-modal__section-title">Parcelas</h3>
                    <div class="erp-lookup-modal__grid-wrap erp-receber-view-modal__grid-wrap">
                        <table class="erp-lookup-modal__grid erp-receber-view-modal__grid">
                            <thead>
                                <tr>
                                    <th>Nº</th>
                                    <th>Documento</th>
                                    <th>Vencimento</th>
                                    <th class="erp-receber-view-modal__num">Valor</th>
                                    <th class="erp-receber-view-modal__num">Saldo</th>
                                    <th>Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($parcelas as $parcela)
                                    <tr @class(['erp-receber-view-modal__row--atual' => ($parcela['atual'] ?? '0') === '1'])>
                                        <td class="erp-receber-view-modal__center">{{ $parcela['numero'] ?? '—' }}</td>
                                        <td>{{ $parcela['documento'] ?? '—' }}</td>
                                        <td class="erp-receber-view-modal__center">{{ $parcela['vencimento'] ?? '—' }}</td>
                                        <td class="erp-receber-view-modal__num">{{ $parcela['valor'] ?? '—' }}</td>
                                        <td class="erp-receber-view-modal__num">{{ $parcela['saldo'] ?? '—' }}</td>
                                        <td>{{ $parcela['situacao'] ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="erp-lookup-modal__empty">Nenhuma parcela encontrada.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
