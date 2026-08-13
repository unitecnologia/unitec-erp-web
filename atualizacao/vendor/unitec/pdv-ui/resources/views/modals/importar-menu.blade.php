@if ($this->activeModal === 'importar_menu')
    <div class="erp-pdv-modal erp-pdv-modal--centered" role="dialog" aria-labelledby="erp-pdv-importar-menu-title">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelImportarMenu"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--importar-menu">
            <header class="erp-pdv-modal__header erp-pdv-modal__header--with-close">
                <h2 id="erp-pdv-importar-menu-title">Escolha o que importar:</h2>
                <button
                    type="button"
                    class="erp-pdv-modal__close"
                    wire:click="cancelImportarMenu"
                    title="Fechar"
                >✕</button>
            </header>
            <div class="erp-pdv-importar-menu" id="erp-pdv-importar-menu-panel">
                @foreach ($this->importarMenuOptions as $index => $option)
                    <button
                        type="button"
                        id="erp-pdv-importar-menu-row-{{ $index }}"
                        wire:click="selectImportarTipo('{{ $option['key'] }}')"
                        wire:keydown.enter.prevent="selectImportarTipo('{{ $option['key'] }}')"
                        @class([
                            'erp-pdv-importar-menu__btn',
                            'erp-pdv-importar-menu__btn--selected' => $this->selectedImportarMenuIndex === $index,
                        ])
                    >
                        <span class="erp-pdv-importar-menu__fn"><kbd>{{ $option['fn'] }}</kbd></span>
                        <span class="erp-pdv-importar-menu__label">{{ $option['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
@endif
