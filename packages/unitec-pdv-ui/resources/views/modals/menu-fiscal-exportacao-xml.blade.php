@if ($this->activeModal === 'menu_fiscal_exportacao_xml')
    <x-pdvui::modal-shell
        title="Exportação de arquivos XML"
        title-id="erp-pdv-menu-fiscal-xml-title"
        eyebrow="Menu Fiscal"
        aria-label="Exportação de arquivos XML"
        close-action="closeMenuFiscalExportacaoXml"
        window-class="erp-pdv-modal__window--small erp-pdv-modal__window--menu-fiscal-xml"
    >
        <x-slot:icon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
                <path d="M14 2v6h6"/>
                <path d="M12 18v-6"/>
                <path d="m9 15 3 3 3-3"/>
            </svg>
        </x-slot:icon>

        <div class="erp-pdv-menu-fiscal-xml__fields">
            <label class="erp-pdv-modal__label" for="erp-pdv-menu-fiscal-xml-de">Data inicial</label>
            <input id="erp-pdv-menu-fiscal-xml-de" type="date" wire:model="menuFiscalXmlDe" class="erp-pdv-modal__input">

            <label class="erp-pdv-modal__label" for="erp-pdv-menu-fiscal-xml-ate">Data final</label>
            <input id="erp-pdv-menu-fiscal-xml-ate" type="date" wire:model="menuFiscalXmlAte" class="erp-pdv-modal__input">

            <label class="erp-pdv-modal__label" for="erp-pdv-menu-fiscal-xml-tipo">Tipo de documento</label>
            <select id="erp-pdv-menu-fiscal-xml-tipo" wire:model="menuFiscalXmlTipo" class="erp-pdv-modal__input">
                <option value="nfce">NFC-e (modelo 65)</option>
                <option value="nfe">NF-e (modelo 55)</option>
            </select>

            <label class="erp-pdv-modal__label" for="erp-pdv-menu-fiscal-xml-destino">Pasta / destino</label>
            <input
                id="erp-pdv-menu-fiscal-xml-destino"
                type="text"
                wire:model="menuFiscalXmlDestino"
                class="erp-pdv-modal__input"
                autocomplete="off"
                placeholder="Pasta de destino"
            >
        </div>

        @if ($this->menuFiscalAviso)
            <p class="erp-pdv-menu-fiscal__aviso" role="status">{{ $this->menuFiscalAviso }}</p>
        @endif

        <x-slot:footer>
            <button type="button" wire:click="closeMenuFiscalExportacaoXml" class="erp-pdv-caixa-modal__btn">
                Voltar
            </button>
            <button type="button" wire:click="avisarMenuFiscalPreparacao" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary">
                Exportar XML
            </button>
        </x-slot:footer>
    </x-pdvui::modal-shell>
@endif
