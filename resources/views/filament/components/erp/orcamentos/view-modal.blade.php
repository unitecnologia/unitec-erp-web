@if ($this->viewModalOpen)
    <div
        class="erp-lookup-modal erp-orc-view-modal"
        wire:keydown.escape.window="closeOrcamentoView"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeOrcamentoView"></div>

        <div
            class="erp-lookup-modal__window erp-orc-view-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-orc-view-modal-title"
        >
            <div class="erp-orcamentos-window erp-orc-view-modal__panel">
                <header class="erp-orcamentos-window__titlebar erp-lookup-modal__titlebar">
                    <span id="erp-orc-view-modal-title" class="erp-orcamentos-window__title">Visualização de Orçamento</span>
                    <button
                        type="button"
                        class="erp-orcamentos-window__close erp-lookup-modal__close"
                        wire:click="closeOrcamentoView"
                        aria-label="Fechar"
                        title="ESC | Sair"
                    >&times;</button>
                </header>

                <div class="erp-orcamentos-window__body erp-orc-view-modal__body">
                    <div class="erp-orc-pcad">
                        <div class="erp-orc-header">
                            <div class="erp-pcad-form erp-orc-form">
                                <div class="erp-pcad-form__row erp-orc-form__row--cliente">
                                    <label class="erp-pcad-form__label">Número</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--xs erp-pcad-form__input--readonly">{{ $this->viewModalHeader['numero'] ?? '—' }}</span>

                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">Razão Social ou CNPJ</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--cliente-search erp-pcad-form__input--readonly">{{ $this->viewModalHeader['cliente'] ?? '—' }}</span>

                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">CPF/CNPJ</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--doc erp-pcad-form__input--readonly">{{ $this->viewModalHeader['cpf_cnpj'] ?? '—' }}</span>
                                </div>

                                <div class="erp-pcad-form__row">
                                    <label class="erp-pcad-form__label">Endereço</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--grow erp-pcad-form__input--readonly">{{ $this->viewModalHeader['endereco'] ?? '—' }}</span>

                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">Número</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--xs erp-pcad-form__input--readonly">{{ $this->viewModalHeader['numero_end'] ?? '—' }}</span>

                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">Bairro</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--sm erp-pcad-form__input--readonly">{{ $this->viewModalHeader['bairro'] ?? '—' }}</span>
                                </div>

                                <div class="erp-pcad-form__row">
                                    <label class="erp-pcad-form__label">CEP</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--cep erp-pcad-form__input--readonly">{{ $this->viewModalHeader['cep'] ?? '—' }}</span>

                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">Cidade</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--city erp-pcad-form__input--readonly">{{ $this->viewModalHeader['cidade'] ?? '—' }}</span>

                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">UF</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--xs erp-pcad-form__input--readonly">{{ $this->viewModalHeader['uf'] ?? '—' }}</span>

                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">Fone Fixo</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--phone erp-pcad-form__input--readonly">{{ $this->viewModalHeader['fone'] ?? '—' }}</span>

                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">WhatsApp</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--phone erp-pcad-form__input--readonly">{{ $this->viewModalHeader['whatsapp'] ?? '—' }}</span>

                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">Vendedor</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--readonly">{{ $this->viewModalHeader['vendedor'] ?? '—' }}</span>
                                </div>

                                <div class="erp-pcad-form__row">
                                    <label class="erp-pcad-form__label">Forma de Pagamento</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--grow erp-pcad-form__input--readonly">{{ $this->viewModalHeader['forma_pagamento'] ?? '—' }}</span>

                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">Validade</label>
                                    <span class="erp-pcad-form__input erp-pcad-form__input--xs erp-pcad-form__input--readonly">{{ $this->viewModalHeader['validade_dias'] ?? '0' }}</span>
                                    <span class="erp-orc-header__suffix">dias</span>
                                </div>
                            </div>
                        </div>

                        <div class="erp-pcad__tabs">
                            <button
                                type="button"
                                wire:click="setViewModalTab('itens')"
                                @class(['erp-pcad__tab', 'erp-pcad__tab--active' => $this->viewModalActiveTab === 'itens'])
                            >Itens</button>
                            <button
                                type="button"
                                wire:click="setViewModalTab('observacoes')"
                                @class(['erp-pcad__tab', 'erp-pcad__tab--active' => $this->viewModalActiveTab === 'observacoes'])
                            >Observações</button>
                        </div>

                        <div class="erp-pcad__workspace">
                            <div class="erp-pcad__content">
                                @if ($this->viewModalActiveTab === 'itens')
                                    <div class="erp-orc-itens erp-orc-view-modal__itens">
                                        <div class="erp-orc-itens__grid-wrap">
                                            <table class="erp-orc-itens__grid">
                                                <thead>
                                                    <tr>
                                                        <th class="erp-orc-itens__col-item">Item</th>
                                                        <th>Cód.</th>
                                                        <th>Pesquisar por Código ou Descrição</th>
                                                        <th>Quant.</th>
                                                        <th>Un.</th>
                                                        <th>Preço</th>
                                                        <th>Total</th>
                                                        <th>Grade</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($this->viewModalItens as $item)
                                                        <tr class="erp-orc-itens__row">
                                                            <td class="erp-orc-itens__col-item">{{ $item['numero'] ?? '' }}</td>
                                                            <td>{{ $item['codigo'] }}</td>
                                                            <td>{{ $item['descricao'] }}</td>
                                                            <td class="erp-orc-itens__cell-input--num">{{ $item['quantidade'] }}</td>
                                                            <td>{{ $item['unidade'] }}</td>
                                                            <td class="erp-orc-itens__cell-input--num">{{ $item['preco'] }}</td>
                                                            <td class="erp-orc-itens__cell-input--num">{{ $item['total'] }}</td>
                                                            <td>{{ $item['grade'] ?: '—' }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="8" class="erp-orc-itens__empty">Nenhum item informado.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @else
                                    <div class="erp-orc-obs">
                                        <label class="erp-pcad-form__label">Observações</label>
                                        <div class="erp-orc-obs__textarea erp-orc-view-modal__obs">{{ $this->viewModalObservacoes ?: '—' }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="erp-orc-totals erp-orc-view-modal__totals">
                        <span class="erp-orc-totals__label">SUBTOTAL |</span>
                        <span class="erp-orc-totals__value">{{ $this->viewModalTotais['subtotal'] ?? '0,00' }}</span>

                        <span class="erp-orc-totals__label">DESCONTO %</span>
                        <span class="erp-orc-totals__value">{{ $this->viewModalTotais['desconto_pct'] ?? '0,00' }}</span>

                        <span class="erp-orc-totals__label">R$</span>
                        <span class="erp-orc-totals__value">{{ $this->viewModalTotais['desconto_valor'] ?? '0,00' }}</span>

                        <span class="erp-orc-totals__label erp-orc-totals__label--total">TOTAL |</span>
                        <span class="erp-orc-totals__value erp-orc-totals__value--total">{{ $this->viewModalTotais['total'] ?? '0,00' }}</span>
                    </div>

                    <div class="erp-pcad-actions erp-orc-actions erp-orc-view-modal__actions">
                        <button type="button" wire:click="closeOrcamentoView" class="erp-pcad-actions__btn" data-erp-key="Escape">
                            <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                            <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
