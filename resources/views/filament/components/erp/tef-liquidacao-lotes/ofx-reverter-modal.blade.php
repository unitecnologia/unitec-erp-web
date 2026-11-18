@if ($this->ofxReverterConfirmOpen)
    <div class="erp-lookup-modal erp-confirm-delete-modal" wire:keydown.escape.window="cancelReverterOfx">
        <div class="erp-lookup-modal__backdrop" wire:click="cancelReverterOfx"></div>
        <div class="erp-lookup-modal__window erp-confirm-delete-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Desfazer vínculo OFX #{{ $this->ofxReverterVinculoId }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="cancelReverterOfx">✕</button>
            </div>
            <div class="erp-lookup-modal__body">
                <p>Preserva auditoria e libera o valor. Não estorna TEF nem altera Livro Caixa.</p>
                <label class="erp-pcad-form__label" for="ofx-rev-motivo">Motivo</label>
                <input id="ofx-rev-motivo" type="text" wire:model="ofxReverterMotivo" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255">
            </div>
            <div class="erp-lookup-modal__footer erp-contador-form-modal__footer">
                <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="cancelReverterOfx">Voltar</button>
                <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--danger" wire:click="confirmReverterOfx">Desfazer</button>
            </div>
        </div>
    </div>
@endif
