@if ($this->activeModal === 'vendedor_venda')
    <div
        id="erp-pdv-vendedor-venda"
        class="erp-pdv-modal erp-pdv-vendedor-modal"
        role="dialog"
        aria-labelledby="erp-pdv-vendedor-venda-title"
        aria-modal="true"
    >
        <div class="erp-pdv-modal__backdrop" wire:click="cancelarVendedorDaVenda"></div>

        <div class="erp-pdv-modal__window erp-pdv-vendedor-modal__window">
            <header class="erp-pdv-vendedor-modal__hero">
                <div class="erp-pdv-vendedor-modal__hero-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="3.6"/>
                        <path d="M5 20c0-3.6 3.1-5.8 7-5.8s7 2.2 7 5.8"/>
                    </svg>
                </div>
                <div class="erp-pdv-vendedor-modal__hero-text">
                    <p class="erp-pdv-vendedor-modal__eyebrow">Fechamento da venda</p>
                    <h2 id="erp-pdv-vendedor-venda-title">Selecione o vendedor</h2>
                    <p class="erp-pdv-vendedor-modal__subtitle">Obrigatório para esta venda. Busque pelo nome ou código.</p>
                </div>
                <button type="button" class="erp-pdv-vendedor-modal__x" wire:click="cancelarVendedorDaVenda" title="Fechar" aria-label="Fechar">×</button>
            </header>

            <div class="erp-pdv-vendedor-modal__body">
                <div class="erp-pdv-vendedor-modal__search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="m20 20-3.2-3.2"/>
                    </svg>
                    <input
                        id="erp-pdv-vendedor-venda-search"
                        type="text"
                        placeholder="Nome ou código do vendedor"
                        autocomplete="off"
                        spellcheck="false"
                        data-lpignore="true"
                    >
                </div>

                <ul id="erp-pdv-vendedor-venda-list" class="erp-pdv-vendedor-modal__list" role="listbox" aria-label="Vendedores">
                    @forelse ($this->vendedorVendaOpcoes as $index => $row)
                        <li>
                            <button
                                type="button"
                                id="erp-pdv-vendedor-venda-row-{{ $index }}"
                                data-vendedor-id="{{ (int) ($row['id'] ?? 0) }}"
                                data-busca="{{ $row['busca'] ?? '' }}"
                                role="option"
                                aria-selected="false"
                                class="erp-pdv-vendedor-modal__row"
                            >
                                <span class="erp-pdv-vendedor-modal__avatar" aria-hidden="true">{{ mb_substr((string) ($row['nome'] ?? '?'), 0, 1, 'UTF-8') }}</span>
                                <span class="erp-pdv-vendedor-modal__nome">{{ $row['nome'] ?? '' }}</span>
                                <span class="erp-pdv-vendedor-modal__codigo">{{ $row['codigo'] ?? '' }}</span>
                            </button>
                        </li>
                    @empty
                        <li class="erp-pdv-vendedor-modal__empty">
                            Nenhum vendedor ativo nesta empresa.
                            <small>Em RH, o vendedor precisa estar ativo, efetuar venda e pertencer à empresa.</small>
                        </li>
                    @endforelse
                </ul>

                <p class="erp-pdv-vendedor-modal__hint">
                    <kbd>↑</kbd><kbd>↓</kbd> navegar · <kbd>Enter</kbd> confirmar · <kbd>Esc</kbd> cancelar
                </p>
            </div>

            <footer class="erp-pdv-vendedor-modal__footer">
                <button type="button" wire:click="cancelarVendedorDaVenda" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--ghost">
                    <kbd>Esc</kbd> Cancelar
                </button>
                <button type="button" id="erp-pdv-vendedor-venda-confirm" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary" @disabled($this->vendedorVendaOpcoes === [])>
                    <kbd>Enter</kbd> Confirmar
                </button>
            </footer>
        </div>
    </div>
@endif
