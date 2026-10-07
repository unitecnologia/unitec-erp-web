@if ($this->osConfirmAcao !== null)
    <div
        class="erp-os-confirm-modal"
        wire:key="os-confirm-{{ $this->osConfirmAcao }}-{{ $this->osConfirmId }}"
        x-data
        x-init="$nextTick(() => $el.querySelector('.erp-os-confirm-modal__btn--yes')?.focus())"
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.fecharConfirmacaoOs(); }
            if ($event.key === 'Enter' && ! $event.target.closest('.erp-os-confirm-modal__btn--no')) { $event.preventDefault(); $wire.confirmarAcaoOs(); }
        "
    >
        <div class="erp-os-confirm-modal__backdrop" wire:click="fecharConfirmacaoOs"></div>

        <div class="erp-os-confirm-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="erp-os-confirm-title">
            <div class="erp-os-confirm-modal__titlebar">
                <span id="erp-os-confirm-title">Confirmação</span>
                <button type="button" class="erp-os-confirm-modal__close" wire:click="fecharConfirmacaoOs" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-os-confirm-modal__body">
                <div class="erp-os-confirm-modal__icon" aria-hidden="true">?</div>
                <p class="erp-os-confirm-modal__message">{{ $this->osConfirmMensagem }}</p>
            </div>

            <div class="erp-os-confirm-modal__actions">
                <button
                    type="button"
                    class="erp-os-confirm-modal__btn erp-os-confirm-modal__btn--yes"
                    wire:click="confirmarAcaoOs"
                    wire:loading.attr="disabled"
                    wire:target="confirmarAcaoOs"
                >
                    Sim
                </button>
                <button
                    type="button"
                    class="erp-os-confirm-modal__btn erp-os-confirm-modal__btn--no"
                    wire:click="fecharConfirmacaoOs"
                >
                    Não
                </button>
            </div>
        </div>
    </div>
@endif
