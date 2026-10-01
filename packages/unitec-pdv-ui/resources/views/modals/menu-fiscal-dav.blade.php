@if ($this->activeModal === 'menu_fiscal_dav')
    <x-pdvui::modal-shell
        title="Controle dos DAV"
        title-id="erp-pdv-menu-fiscal-dav-title"
        eyebrow="Menu Fiscal"
        subtitle="Arquivo III"
        aria-label="Controle dos DAV"
        close-action="closeMenuFiscalDav"
        window-class="erp-pdv-modal__window--menu-fiscal-dav"
    >
        <x-slot:icon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="3" width="16" height="18" rx="2"/>
                <path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/>
            </svg>
        </x-slot:icon>

        <div class="erp-pdv-menu-fiscal-xml__fields">
            <label class="erp-pdv-modal__label" for="erp-pdv-menu-fiscal-dav-de">Data inicial</label>
            <input id="erp-pdv-menu-fiscal-dav-de" type="date" wire:model="menuFiscalDavDe" class="erp-pdv-modal__input">

            <label class="erp-pdv-modal__label" for="erp-pdv-menu-fiscal-dav-ate">Data final</label>
            <input id="erp-pdv-menu-fiscal-dav-ate" type="date" wire:model="menuFiscalDavAte" class="erp-pdv-modal__input">

            <label class="erp-pdv-modal__label" for="erp-pdv-menu-fiscal-dav-situacao">Situação</label>
            <select id="erp-pdv-menu-fiscal-dav-situacao" wire:model="menuFiscalDavSituacao" class="erp-pdv-modal__input">
                <option value="todos">Todos</option>
                <option value="aberto">Em aberto</option>
                <option value="emitido_dfe">Emitido DF-e</option>
                <option value="baixado">Baixado</option>
            </select>

            <div class="erp-pdv-menu-fiscal-ui__grade-wrap">
                <table class="erp-pdv-menu-fiscal-ui__grade">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Cliente</th>
                            <th>CPF/CNPJ</th>
                            <th>Situação</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7">Nenhum DAV nesta etapa.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if ($this->menuFiscalAviso)
            <p class="erp-pdv-menu-fiscal__aviso" role="status">{{ $this->menuFiscalAviso }}</p>
        @endif

        <x-slot:footer>
            <button type="button" wire:click="closeMenuFiscalDav" class="erp-pdv-caixa-modal__btn">
                Voltar
            </button>
            <button type="button" wire:click="avisarMenuFiscalPreparacao" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary">
                Gerar Arquivo III
            </button>
        </x-slot:footer>
    </x-pdvui::modal-shell>
@endif
