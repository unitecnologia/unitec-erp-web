@if ($this->nfseMostraEquipamento() && $this->nfseEquipamentoModalOpen)
    <div
        class="erp-lookup-modal erp-orc-equip-modal erp-nfse-equip-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-nfse-equip-title"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="fecharNfseEquipamento(true)"></div>
        <div class="erp-lookup-modal__window erp-orc-equip-modal__window" role="document" aria-labelledby="erp-nfse-equip-title">
            <header class="erp-lookup-modal__titlebar">
                <span id="erp-nfse-equip-title">Equipamento</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="fecharNfseEquipamento(true)" aria-label="Fechar">&times;</button>
            </header>
            <div class="erp-orc-equip-modal__body">
                @include('filament.components.erp.ordens-servico.form.tabs.equipamento', [
                    'readOnly' => $this->nfseSomenteLeitura(),
                ])
            </div>
            <footer class="erp-orc-equip-modal__footer">
                <button type="button" class="erp-orc-equip-modal__ok" wire:click="fecharNfseEquipamento(true)">
                    Fechar
                </button>
            </footer>
        </div>
    </div>
@endif
