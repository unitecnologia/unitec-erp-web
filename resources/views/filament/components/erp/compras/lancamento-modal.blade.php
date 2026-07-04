@if ($this->lancamentoModalOpen)
    <div
        class="erp-lookup-modal erp-compras-lancamento-modal"
        wire:keydown.escape.window="closeCompraLancamento"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeCompraLancamento"></div>

        <div
            class="erp-lookup-modal__window erp-compras-lancamento-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-compras-lancamento-title"
        >
            <div class="erp-lookup-modal__titlebar erp-compras-lancamento-modal__titlebar">
                <span id="erp-compras-lancamento-title">Lançamento de Compras</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeCompraLancamento"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-compras-lancamento-modal__body">
                <div class="erp-compras-lancamento-modal__toolbar">
                    <div class="erp-compras-lancamento-modal__toolbar-actions">
                        @if ($this->lancamentoModalCompraId)
                            <button
                                type="button"
                                wire:click.stop="printCompraDanfe"
                                data-erp-print-nota
                                class="erp-compras-lancamento-modal__tool-btn"
                                title="Imprimir Nota"
                            >
                                <span class="erp-compras-lancamento-modal__tool-icon">🖨</span>
                                <span class="erp-compras-lancamento-modal__tool-label"><kbd>F5</kbd> | Imprimir Nota</span>
                            </button>
                        @else
                            <button
                                type="button"
                                class="erp-compras-lancamento-modal__tool-btn"
                                disabled
                                title="Imprimir Nota"
                            >
                                <span class="erp-compras-lancamento-modal__tool-icon">🖨</span>
                                <span class="erp-compras-lancamento-modal__tool-label"><kbd>F5</kbd> | Imprimir Nota</span>
                            </button>
                        @endif
                        <button
                            type="button"
                            class="erp-compras-lancamento-modal__tool-btn erp-compras-lancamento-modal__tool-btn--exit"
                            wire:click="closeCompraLancamento"
                            title="Sair"
                        >
                            <span class="erp-compras-lancamento-modal__tool-icon erp-compras-lancamento-modal__tool-icon--exit">✕</span>
                            <span class="erp-compras-lancamento-modal__tool-label"><kbd>ESC</kbd> | Sair</span>
                        </button>
                    </div>

                    <div class="erp-compras-lancamento-modal__status-box">
                        {{ $this->lancamentoModalStatus }}
                    </div>
                </div>

                <div class="erp-compras-lancamento-modal__header">
                    <div class="erp-compras-lancamento-modal__form-row">
                        <div class="erp-compras-lancamento-modal__form-group">
                            <label class="erp-compras-lancamento-modal__form-label">Número</label>
                            <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--xs">{{ $this->lancamentoModalHeader['numero'] ?? '—' }}</span>
                        </div>

                        <div class="erp-compras-lancamento-modal__form-group">
                            <label class="erp-compras-lancamento-modal__form-label">Empresa</label>
                            <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--empresa">{{ $this->lancamentoModalHeader['empresa'] ?? '—' }}</span>
                        </div>

                        <div class="erp-compras-lancamento-modal__form-group erp-compras-lancamento-modal__form-group--grow">
                            <label class="erp-compras-lancamento-modal__form-label">Fornecedor</label>
                            <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--fornecedor">{{ $this->lancamentoModalHeader['fornecedor'] ?? '—' }}</span>
                        </div>

                        <div class="erp-compras-lancamento-modal__form-group">
                            <label class="erp-compras-lancamento-modal__form-label">UF</label>
                            <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--uf">{{ $this->lancamentoModalHeader['uf'] ?? '—' }}</span>
                        </div>

                        <div class="erp-compras-lancamento-modal__form-group">
                            <label class="erp-compras-lancamento-modal__form-label">CNPJ</label>
                            <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--doc">{{ $this->lancamentoModalHeader['cnpj'] ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="erp-compras-lancamento-modal__form-row">
                        <div class="erp-compras-lancamento-modal__form-group erp-compras-lancamento-modal__form-group--grow">
                            <label class="erp-compras-lancamento-modal__form-label">Chave</label>
                            <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--chave">{{ $this->lancamentoModalHeader['chave'] ?? '—' }}</span>
                        </div>

                        <div class="erp-compras-lancamento-modal__form-group">
                            <label class="erp-compras-lancamento-modal__form-label">Nota</label>
                            <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--sm">{{ $this->lancamentoModalHeader['nota'] ?? '—' }}</span>
                        </div>

                        <div class="erp-compras-lancamento-modal__form-group">
                            <label class="erp-compras-lancamento-modal__form-label">Modelo</label>
                            <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--xs">{{ $this->lancamentoModalHeader['modelo'] ?? '—' }}</span>
                        </div>

                        <div class="erp-compras-lancamento-modal__form-group">
                            <label class="erp-compras-lancamento-modal__form-label">Série</label>
                            <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--xs">{{ $this->lancamentoModalHeader['serie'] ?? '—' }}</span>
                        </div>

                        <div class="erp-compras-lancamento-modal__form-group">
                            <label class="erp-compras-lancamento-modal__form-label">Dt. Emissão</label>
                            <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--date">{{ $this->lancamentoModalHeader['data_emissao'] ?? '—' }}</span>
                        </div>

                        <div class="erp-compras-lancamento-modal__form-group">
                            <label class="erp-compras-lancamento-modal__form-label">Dt. Entrada</label>
                            <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--date">{{ $this->lancamentoModalHeader['data_entrada'] ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <p class="erp-compras-lancamento-modal__grid-hint">
                    Clique nas teclas <strong>CTRL + Delete</strong> para excluir ITEM
                </p>

                <div class="erp-lookup-modal__grid-wrap erp-compras-lancamento-modal__grid-wrap">
                    <table class="erp-lookup-modal__grid erp-compras-lancamento-modal__grid">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Código</th>
                                <th>Referência</th>
                                <th>Produto</th>
                                <th class="erp-compras-lancamento-modal__num">Qtd.Compra</th>
                                <th class="erp-compras-lancamento-modal__num">Preço</th>
                                <th class="erp-compras-lancamento-modal__num">Total</th>
                                <th class="erp-compras-lancamento-modal__num">Pr.Venda</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->lancamentoModalRows as $row)
                                <tr @class(['erp-lookup-modal__row--selected' => $loop->first])>
                                    <td class="erp-compras-lancamento-modal__center">{{ $row['item'] }}</td>
                                    <td class="erp-compras-lancamento-modal__center">{{ $row['codigo'] }}</td>
                                    <td class="erp-compras-lancamento-modal__center">{{ $row['referencia'] }}</td>
                                    <td>{{ $row['produto'] }}</td>
                                    <td class="erp-compras-lancamento-modal__num">{{ $row['qtd'] }}</td>
                                    <td class="erp-compras-lancamento-modal__num">{{ $row['preco'] }}</td>
                                    <td class="erp-compras-lancamento-modal__num">{{ $row['total'] }}</td>
                                    <td class="erp-compras-lancamento-modal__num">{{ $row['preco_venda'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="erp-lookup-modal__empty">Nenhum item encontrado para esta compra.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="erp-compras-lancamento-modal__footer">
                    <div class="erp-compras-lancamento-modal__footer-top">
                        <fieldset class="erp-compras-lancamento-modal__fieldset erp-compras-lancamento-modal__fieldset--aplicar">
                            <legend class="erp-compras-lancamento-modal__legend">Aplicar</legend>
                            <label class="erp-compras-lancamento-modal__radio">
                                <input type="radio" name="lancamento-aplicar" checked disabled>
                                <span>Item Selecionado</span>
                            </label>
                            <label class="erp-compras-lancamento-modal__radio">
                                <input type="radio" name="lancamento-aplicar" disabled>
                                <span>Todos</span>
                            </label>
                        </fieldset>

                        <fieldset class="erp-compras-lancamento-modal__fieldset erp-compras-lancamento-modal__fieldset--margem">
                            <legend class="erp-compras-lancamento-modal__legend">Margem de Lucro</legend>
                            <div class="erp-compras-lancamento-modal__margem-row">
                                <span class="erp-compras-lancamento-modal__form-input erp-compras-lancamento-modal__form-input--margem" aria-readonly="true"></span>
                                <span class="erp-compras-lancamento-modal__percent">%</span>
                                <button type="button" class="erp-compras-lancamento-modal__apply-btn" disabled>Aplicar Margem</button>
                            </div>
                        </fieldset>

                        <p class="erp-compras-lancamento-modal__formula">
                            Valor Compra <strong>{{ $this->lancamentoModalValorCompra }}</strong>
                            + Valor Margem <strong>{{ $this->lancamentoModalValorMargem }}</strong>
                            = Valor Venda <strong>{{ $this->lancamentoModalValorVenda }}</strong>
                        </p>
                    </div>

                    <div class="erp-compras-lancamento-modal__footer-bottom">
                        <fieldset class="erp-compras-lancamento-modal__fieldset erp-compras-lancamento-modal__fieldset--parametros">
                            <legend class="erp-compras-lancamento-modal__legend">Parâmetros</legend>
                            <label class="erp-compras-lancamento-modal__check">
                                <input type="checkbox" checked disabled>
                                <span>Ajusta Preço de Venda</span>
                            </label>
                            <label class="erp-compras-lancamento-modal__check">
                                <input type="checkbox" disabled>
                                <span>Gerar Financeiro</span>
                            </label>
                            <label class="erp-compras-lancamento-modal__check erp-compras-lancamento-modal__check--disabled">
                                <input type="checkbox" checked disabled>
                                <span>Gera Estoque</span>
                            </label>
                        </fieldset>

                        <fieldset class="erp-compras-lancamento-modal__fieldset erp-compras-lancamento-modal__fieldset--totais">
                            <legend class="erp-compras-lancamento-modal__legend">Totais</legend>
                            @php
                                $totais = $this->lancamentoModalTotais;
                                $totaisLabels = [
                                    ['key' => 'subtotal', 'label' => 'SubTotal'],
                                    ['key' => 'base_icms', 'label' => 'Base de ICMS'],
                                    ['key' => 'valor_icms', 'label' => 'Valor de ICMS'],
                                    ['key' => 'base_ipi', 'label' => 'Base de IPI'],
                                    ['key' => 'valor_ipi', 'label' => 'Valor de IPI'],
                                    ['key' => 'base_cofins', 'label' => 'Base Cofins'],
                                    ['key' => 'valor_cofins', 'label' => 'Valor Cofins'],
                                    ['key' => 'base_pis', 'label' => 'Base PIS'],
                                    ['key' => 'valor_pis', 'label' => 'Valor PIS'],
                                    ['key' => 'base_st', 'label' => 'Base ST'],
                                    ['key' => 'valor_st', 'label' => 'Valor ST'],
                                    ['key' => 'desconto', 'label' => 'Desconto'],
                                    ['key' => 'frete', 'label' => 'Frete'],
                                    ['key' => 'seguro', 'label' => 'Seguro'],
                                    ['key' => 'outras', 'label' => 'Outras'],
                                    ['key' => 'total', 'label' => 'Total'],
                                ];
                            @endphp
                            <div class="erp-compras-lancamento-modal__totais-grid">
                                @foreach ($totaisLabels as $item)
                                    <div class="erp-compras-lancamento-modal__total-field">
                                        <span class="erp-compras-lancamento-modal__total-label">{{ $item['label'] }}</span>
                                        <span @class([
                                            'erp-compras-lancamento-modal__total-value',
                                            'erp-compras-lancamento-modal__total-value--strong' => $item['key'] === 'total',
                                        ])>{{ $totais[$item['key']] ?? '0,00' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
