@if ($this->activeModal === 'vendedor')
    <div class="erp-pdv-modal erp-pdv-vendedor-modal" role="dialog" aria-labelledby="erp-pdv-vendedor-title" aria-modal="true">
        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>

        <div class="erp-pdv-modal__window erp-pdv-vendedor-modal__window">
            <header class="erp-pdv-vendedor-modal__hero">
                <div class="erp-pdv-vendedor-modal__hero-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="3.6"/>
                        <path d="M5 20c0-3.6 3.1-5.8 7-5.8s7 2.2 7 5.8"/>
                    </svg>
                </div>
                <div class="erp-pdv-vendedor-modal__hero-text">
                    <p class="erp-pdv-vendedor-modal__eyebrow">F3 — Troca de operador</p>
                    <h2 id="erp-pdv-vendedor-title">Operador da venda</h2>
                    <p class="erp-pdv-vendedor-modal__subtitle">Busque pelo nome ou código e confirme.</p>
                </div>
                <button type="button" class="erp-pdv-vendedor-modal__x" wire:click="closePdvModal" title="Fechar" aria-label="Fechar">×</button>
            </header>

            <div class="erp-pdv-vendedor-modal__body">
                <div class="erp-pdv-vendedor-modal__search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="m20 20-3.2-3.2"/>
                    </svg>
                    <input
                        id="erp-pdv-vendedor-search"
                        type="text"
                        wire:model.live.debounce.150ms="vendedorSearch"
                        wire:keydown.enter.prevent="confirmVendedor"
                        placeholder="Nome ou código do operador"
                        autocomplete="off"
                        spellcheck="false"
                        data-lpignore="true"
                    >
                </div>

                <ul class="erp-pdv-vendedor-modal__list" role="listbox" aria-label="Operadores">
                    @forelse ($this->vendedorResults as $index => $row)
                        @php
                            $selecionado = $this->selectedVendedorIndex === $index;
                            $atual = $this->vendedorId === (int) ($row['vendedor_id'] ?? 0);
                        @endphp
                        <li>
                            <button
                                type="button"
                                id="erp-pdv-vendedor-row-{{ $index }}"
                                role="option"
                                aria-selected="{{ $selecionado ? 'true' : 'false' }}"
                                wire:click="selectVendedorResult({{ $index }})"
                                wire:dblclick="confirmVendedor"
                                @class([
                                    'erp-pdv-vendedor-modal__row',
                                    'erp-pdv-vendedor-row--selected' => $selecionado,
                                ])
                            >
                                <span class="erp-pdv-vendedor-modal__avatar" aria-hidden="true">{{ mb_substr((string) ($row['nome'] ?? '?'), 0, 1, 'UTF-8') }}</span>
                                <span class="erp-pdv-vendedor-modal__nome">{{ $row['nome'] ?? '' }}</span>
                                <span class="erp-pdv-vendedor-modal__codigo">{{ $row['codigo'] ?? '' }}</span>
                                @if ($atual)
                                    <span class="erp-pdv-vendedor-modal__badge">Atual</span>
                                @endif
                            </button>
                        </li>
                    @empty
                        <li class="erp-pdv-vendedor-modal__empty">
                            Nenhum operador liberado neste PDV.
                            <small>Libere em RH → Funcionários → aba Operador → PDVs liberados.</small>
                        </li>
                    @endforelse
                </ul>

                <p class="erp-pdv-vendedor-modal__hint">
                    <kbd>↑</kbd><kbd>↓</kbd> navegar · <kbd>Enter</kbd> confirmar · <kbd>Esc</kbd> cancelar
                </p>
            </div>

            <footer class="erp-pdv-vendedor-modal__footer">
                <button type="button" wire:click="closePdvModal" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--ghost">
                    <kbd>Esc</kbd> Cancelar
                </button>
                <button type="button" wire:click="confirmVendedor" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary">
                    <kbd>Enter</kbd> Confirmar
                </button>
            </footer>
        </div>
    </div>
@endif
