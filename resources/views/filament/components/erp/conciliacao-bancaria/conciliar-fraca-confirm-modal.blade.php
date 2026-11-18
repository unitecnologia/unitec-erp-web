@if ($this->conciliarModalOpen && $this->conciliarFracaConfirmOpen)
    <div class="erp-lookup-modal erp-contador-delete-modal erp-conciliacao-c3-fraca-modal">
        <div class="erp-lookup-modal__backdrop" wire:click="closeConciliarFracaConfirm"></div>
        <div class="erp-lookup-modal__window erp-contador-delete-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Identificação não comprovada</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeConciliarFracaConfirm">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-delete-modal__body">
                <p class="erp-contador-delete-modal__message">
                    O OFX Ailos não traz txId/endToEndId comum. FITID não identifica o PIX.
                    O vínculo 1:1 será gravado com o seu usuário e a data/hora, sem gerar movimento financeiro.
                </p>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-delete-modal__actions">
                <button type="button" wire:click="confirmarConciliacaoFracaAssinada" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Confirmar</span>
                </button>
                <button type="button" wire:click="closeConciliarFracaConfirm" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Voltar</span>
                </button>
            </div>
        </div>
    </div>
@endif
