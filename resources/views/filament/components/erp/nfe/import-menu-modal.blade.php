@if ($this->nfeImportMenuOpen)
    <div
        class="erp-lookup-modal erp-nfe-import-menu-modal"
        wire:keydown.escape="closeNfeImportMenu"
        wire:keydown.f2.prevent="openNfeImportListFromHotkey('F2')"
        wire:keydown.f3.prevent="openNfeImportListFromHotkey('F3')"
        wire:keydown.f4.prevent="openNfeImportListFromHotkey('F4')"
        wire:keydown.f5.prevent="openNfeImportListFromHotkey('F5')"
        wire:keydown.f6.prevent="openNfeImportListFromHotkey('F6')"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeImportMenu"></div>

        <div class="erp-lookup-modal__window erp-nfe-import-menu-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-import-menu-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-import-menu-title">Importar documento</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeImportMenu" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-import-menu-modal__body">
                <section class="erp-nfe-import-menu-modal__section">
                    <h3 class="erp-nfe-import-menu-modal__section-title">Filtro</h3>
                    <div class="erp-nfe-import-menu-modal__numero">
                        <label class="erp-nfe-import-menu-modal__label" for="erp-nfe-import-menu-numero">Número</label>
                        <input
                            id="erp-nfe-import-menu-numero"
                            type="text"
                            wire:model="nfeImportMenuNumero"
                            class="erp-nfe-import-menu-modal__input"
                            autocomplete="off"
                            inputmode="numeric"
                        >
                    </div>
                </section>

                <section class="erp-nfe-import-menu-modal__section">
                    <h3 class="erp-nfe-import-menu-modal__section-title">Origem</h3>
                    <ul class="erp-nfe-import-menu-modal__list">
                        @foreach (\App\Support\Erp\Nfe\NfeImportacaoTipo::menuItens() as $item)
                            <li wire:key="nfe-import-menu-{{ $item['tipo'] }}">
                                <button
                                    type="button"
                                    wire:click="openNfeImportList('{{ $item['tipo'] }}')"
                                    class="erp-nfe-import-menu-modal__item @unless($item['implemented']) erp-nfe-import-menu-modal__item--pending @endunless"
                                >
                                    <span class="erp-nfe-import-menu-modal__icon" aria-hidden="true">▦</span>
                                    <span class="erp-nfe-import-menu-modal__text">
                                        <kbd>{{ $item['hotkey'] }}</kbd> | {{ $item['label'] }}
                                    </span>
                                    @unless ($item['implemented'])
                                        <span class="erp-nfe-import-menu-modal__badge">Em breve</span>
                                    @endunless
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-import-menu-modal__actions">
                <button type="button" wire:click="closeNfeImportMenu" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
