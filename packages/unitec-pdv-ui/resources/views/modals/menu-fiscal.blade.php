@if ($this->activeModal === 'menu_fiscal')
    <div class="erp-pdv-modal erp-pdv-modal--menu" role="dialog" aria-label="Menu Fiscal PAF-NFC-e">
        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>
        <div class="erp-pdv-options" id="erp-pdv-menu-fiscal-panel">
            <header class="erp-pdv-options__header">
                <span class="erp-pdv-options__header-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
                        <path d="M14 2v6h6"/>
                        <path d="M8 13h8"/>
                        <path d="M8 17h5"/>
                    </svg>
                </span>
                <div class="erp-pdv-options__header-text">
                    <strong>Menu Fiscal</strong>
                    <small>PAF-NFC-e</small>
                </div>
                <button type="button" class="erp-pdv-options__close" wire:click="closePdvModal" title="Fechar" aria-label="Fechar">×</button>
            </header>

            @if ($this->menuFiscalAviso)
                <p class="erp-pdv-menu-fiscal__aviso" role="status">{{ $this->menuFiscalAviso }}</p>
            @endif

            <ul class="erp-pdv-options__list" role="menu">
                <li>
                    <button type="button" role="menuitem" wire:click="selectMenuFiscalOption('identificacao')">
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.2"/><path d="M5 19a7 7 0 0 1 14 0"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Identificação do PAF-NFC-e</span>
                        <kbd></kbd>
                    </button>
                </li>
                <li>
                    <button type="button" role="menuitem" wire:click="selectMenuFiscalOption('registros')">
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Registros do PAF-NFC-e</span>
                        <kbd></kbd>
                    </button>
                </li>
                <li>
                    <button type="button" role="menuitem" wire:click="selectMenuFiscalOption('dav')">
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Controle dos DAV</span>
                        <kbd></kbd>
                    </button>
                </li>
                <li>
                    <button type="button" role="menuitem" wire:click="selectMenuFiscalOption('exportacao_xml')">
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Exportação de arquivos XML</span>
                        <kbd></kbd>
                    </button>
                </li>
                <li>
                    <button type="button" role="menuitem" wire:click="selectMenuFiscalOption('sair')">
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Sair</span>
                        <kbd>ESC</kbd>
                    </button>
                </li>
            </ul>
        </div>
    </div>
@endif
