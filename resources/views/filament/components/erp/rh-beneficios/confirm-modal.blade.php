@if ($this->cancelConfirmOpen)
    <div class="erp-lookup-modal erp-contador-delete-modal" wire:keydown.escape.window="closeCancelConfirm">
        <div class="erp-lookup-modal__backdrop" wire:click="closeCancelConfirm"></div>
        <div class="erp-lookup-modal__window erp-contador-delete-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Cancelar vínculo</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeCancelConfirm">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-delete-modal__body">
                <p class="erp-contador-delete-modal__message">Cancelar mantém o histórico (não apaga).</p>
                @if (filled($this->cancelNome))
                    <p class="erp-contador-delete-modal__detail">{{ $this->cancelNome }}</p>
                @endif
                <div class="erp-pcad-form__row" style="margin-top:0.5rem">
                    <label class="erp-pcad-form__label" for="rh-ben-cancel-motivo">Motivo</label>
                    <input id="rh-ben-cancel-motivo" type="text" wire:model="cancelMotivo" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255">
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-delete-modal__actions">
                <button type="button" wire:click="confirmCancelarVinculo" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label">Sim</span>
                </button>
                <button type="button" wire:click="closeCancelConfirm" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label">Não</span>
                </button>
            </div>
        </div>
    </div>
@endif

@if ($this->encerrarModalOpen)
    <div class="erp-lookup-modal erp-contador-form-modal" wire:keydown.escape.window="closeEncerrarModal">
        <div class="erp-lookup-modal__backdrop" wire:click="closeEncerrarModal"></div>
        <div class="erp-lookup-modal__window erp-contador-delete-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Encerrar vínculo</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeEncerrarModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-delete-modal__body">
                <p class="erp-contador-delete-modal__message">O vínculo ficará com situação encerrada e data de término informada.</p>
                <div class="erp-pcad-form__row" style="margin-top:0.5rem">
                    <label class="erp-pcad-form__label" for="rh-ben-enc-data">Data encerramento *</label>
                    <input id="rh-ben-enc-data" type="date" wire:model="encerrarData" class="erp-pcad-form__input erp-pcad-form__input--date">
                </div>
                <div class="erp-pcad-form__row">
                    <label class="erp-pcad-form__label" for="rh-ben-enc-motivo">Motivo</label>
                    <input id="rh-ben-enc-motivo" type="text" wire:model="encerrarMotivo" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255">
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-delete-modal__actions">
                <button type="button" wire:click="confirmEncerrarVinculo" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label">Confirmar</span>
                </button>
                <button type="button" wire:click="closeEncerrarModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label">Sair</span>
                </button>
            </div>
        </div>
    </div>
@endif
