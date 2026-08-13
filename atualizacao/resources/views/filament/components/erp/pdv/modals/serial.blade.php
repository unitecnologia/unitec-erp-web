@if ($this->activeModal === 'serial')
    <div class="erp-pdv-modal" role="dialog" aria-label="Número de série">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelPdvSerial"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2>IMEI / Número de Série</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <label class="erp-pdv-modal__label" for="erp-pdv-serial-search">Pesquisar série</label>
                <input
                    id="erp-pdv-serial-search"
                    type="text"
                    wire:model.live.debounce.150ms="pdvSerialSearch"
                    wire:keydown.enter.prevent="confirmPdvSerial"
                    class="erp-pdv-modal__input"
                    data-erp-uppercase
                    autocomplete="off"
                >
                <ul class="erp-pdv-options__list erp-pdv-serial-list">
                    @forelse ($this->pdvSerialResults as $index => $row)
                        <li>
                            <button
                                type="button"
                                wire:click="selectPdvSerialRow({{ $index }})"
                                wire:dblclick="confirmPdvSerial"
                                id="erp-pdv-serial-row-{{ $index }}"
                                @class(['erp-pdv-vendedor-row', 'erp-pdv-vendedor-row--selected' => $this->selectedPdvSerialIndex === $index])
                            >
                                {{ $row['numero_serie'] ?? '' }}
                            </button>
                        </li>
                    @empty
                        <li class="erp-pdv-modal__hint">Nenhuma série disponível.</li>
                    @endforelse
                </ul>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmPdvSerial" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Confirmar</button>
                <button type="button" wire:click="cancelPdvSerial" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
