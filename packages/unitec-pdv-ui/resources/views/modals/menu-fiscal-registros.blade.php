@if ($this->activeModal === 'menu_fiscal_registros')
    <x-pdvui::modal-shell
        title="Registros do PAF-NFC-e"
        title-id="erp-pdv-menu-fiscal-registros-title"
        eyebrow="Menu Fiscal"
        subtitle="Arquivo I"
        aria-label="Registros do PAF-NFC-e"
        close-action="closeMenuFiscalRegistros"
        window-class="erp-pdv-modal__window--menu-fiscal-registros"
    >
        <x-slot:icon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/>
                <path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>
            </svg>
        </x-slot:icon>

        <div class="erp-pdv-menu-fiscal-xml__fields">
            <label class="erp-pdv-modal__label" for="erp-pdv-menu-fiscal-registros-de">Data inicial</label>
            <input id="erp-pdv-menu-fiscal-registros-de" type="date" wire:model="menuFiscalRegistrosDe" class="erp-pdv-modal__input">

            <label class="erp-pdv-modal__label" for="erp-pdv-menu-fiscal-registros-ate">Data final</label>
            <input id="erp-pdv-menu-fiscal-registros-ate" type="date" wire:model="menuFiscalRegistrosAte" class="erp-pdv-modal__input">

            <span class="erp-pdv-modal__label">Estoque</span>
            <div class="erp-pdv-menu-fiscal-ui__choices">
                <label>
                    <input type="radio" wire:model.live="menuFiscalRegistrosEstoque" value="total">
                    Total
                </label>
                <label>
                    <input type="radio" wire:model.live="menuFiscalRegistrosEstoque" value="parcial">
                    Parcial
                </label>
            </div>

            @if ($this->menuFiscalRegistrosEstoque === 'parcial')
                <label class="erp-pdv-modal__label" for="erp-pdv-menu-fiscal-registros-busca">Código ou descrição</label>
                <input
                    id="erp-pdv-menu-fiscal-registros-busca"
                    type="text"
                    wire:model="menuFiscalRegistrosBusca"
                    class="erp-pdv-modal__input"
                    autocomplete="off"
                    placeholder="Busca por código ou descrição"
                >
                <div class="erp-pdv-menu-fiscal-ui__grade-wrap">
                    <table class="erp-pdv-menu-fiscal-ui__grade">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="2">Nenhum produto selecionado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($this->menuFiscalAviso)
            <p class="erp-pdv-menu-fiscal__aviso" role="status">{{ $this->menuFiscalAviso }}</p>
        @endif

        <x-slot:footer>
            <button type="button" wire:click="closeMenuFiscalRegistros" class="erp-pdv-caixa-modal__btn">
                Voltar
            </button>
            <button type="button" wire:click="avisarMenuFiscalPreparacao" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary">
                Gerar Arquivo I
            </button>
        </x-slot:footer>
    </x-pdvui::modal-shell>
@endif
