@if ($this->activeModal === 'vendedor')
    <div class="erp-pdv-modal" role="dialog" aria-label="Vendedor">
        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2>F3 — Vendedor</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <label class="erp-pdv-modal__label" for="erp-pdv-vendedor-search">Nome ou código</label>
                <input
                    id="erp-pdv-vendedor-search"
                    type="text"
                    wire:model.live.debounce.150ms="vendedorSearch"
                    wire:keydown.enter.prevent="confirmVendedor"
                    class="erp-pdv-modal__input"
                    autocomplete="off"
                >
                <ul class="erp-pdv-options__list erp-pdv-vendedor-list">
                    @forelse ($this->vendedorResults as $index => $row)
                        <li>
                            <button
                                type="button"
                                wire:click="selectVendedorResult({{ $index }})"
                                wire:dblclick="confirmVendedor"
                                @class(['erp-pdv-vendedor-row', 'erp-pdv-vendedor-row--selected' => $this->selectedVendedorIndex === $index])
                            >
                                {{ $row['codigo'] ?? '' }} — {{ $row['nome'] ?? '' }}
                            </button>
                        </li>
                    @empty
                        <li class="erp-pdv-modal__hint">Nenhum vendedor encontrado.</li>
                    @endforelse
                </ul>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmVendedor" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Confirmar</button>
                <button type="button" wire:click="closePdvModal" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
