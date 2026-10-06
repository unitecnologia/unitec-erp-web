@if ($this->orcamentoMostraEquipamento() && $this->equipamentoModalOpen)
    <div
        class="erp-lookup-modal erp-orc-equip-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-orc-equip-title"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="fecharEquipamentoOrcamento(true)"></div>
        <div class="erp-lookup-modal__window erp-orc-equip-modal__window" role="document" aria-labelledby="erp-orc-equip-title">
            <header class="erp-lookup-modal__titlebar">
                <span id="erp-orc-equip-title">Equipamento</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="fecharEquipamentoOrcamento(true)" aria-label="Fechar">&times;</button>
            </header>
            <div class="erp-orc-equip-modal__body">
                @include('filament.components.erp.ordens-servico.form.tabs.equipamento', [
                    'readOnly' => $this->orcamentoReadOnly(),
                ])
            </div>
            <footer class="erp-orc-equip-modal__footer">
                <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="fecharEquipamentoOrcamento(true)">
                    Fechar
                </button>
            </footer>
        </div>
    </div>
@endif
