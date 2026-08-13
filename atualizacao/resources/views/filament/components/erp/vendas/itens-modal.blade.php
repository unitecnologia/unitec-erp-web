@if ($this->itensModalOpen)
    <div
        class="erp-lookup-modal erp-vendas-itens-modal"
        wire:keydown.escape.window="closeVendaItens"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeVendaItens"></div>

        <div class="erp-lookup-modal__window erp-vendas-itens-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-vendas-itens-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-vendas-itens-title">{{ $this->itensModalTitulo }}</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeVendaItens"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-vendas-itens-modal__body">
                <div class="erp-lookup-modal__grid-wrap erp-vendas-itens-modal__grid-wrap">
                    <table class="erp-lookup-modal__grid erp-vendas-itens-modal__grid">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Código</th>
                                <th>Produto</th>
                                <th class="erp-vendas-itens-modal__num">Qtd</th>
                                <th class="erp-vendas-itens-modal__num">Preço</th>
                                <th class="erp-vendas-itens-modal__num">Valor Item</th>
                                <th class="erp-vendas-itens-modal__num">Desconto</th>
                                <th class="erp-vendas-itens-modal__num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->itensModalRows as $row)
                                <tr>
                                    <td class="erp-vendas-itens-modal__center">{{ $row['item'] }}</td>
                                    <td class="erp-vendas-itens-modal__center">{{ $row['codigo'] }}</td>
                                    <td>{{ $row['produto'] }}</td>
                                    <td class="erp-vendas-itens-modal__num">{{ $row['qtd'] }}</td>
                                    <td class="erp-vendas-itens-modal__num">{{ $row['preco'] }}</td>
                                    <td class="erp-vendas-itens-modal__num">{{ $row['valor_item'] }}</td>
                                    <td class="erp-vendas-itens-modal__num">{{ $row['desconto'] }}</td>
                                    <td class="erp-vendas-itens-modal__num">{{ $row['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="erp-lookup-modal__empty">Nenhum item encontrado para esta venda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="erp-vendas-itens-modal__footer">
                    <div class="erp-vendas-itens-modal__total">
                        <span class="erp-vendas-itens-modal__total-label">TOTAL</span>
                        <span class="erp-vendas-itens-modal__total-value">{{ $this->itensModalTotalFormatted }}</span>
                    </div>

                    @if ($this->itensModalPdvVendaId)
                        <button
                            type="button"
                            class="erp-vendas-itens-modal__print-btn"
                            wire:click="reimprimirVendaItens"
                            wire:loading.attr="disabled"
                            wire:target="reimprimirVendaItens"
                        >
                            Imprimir
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
