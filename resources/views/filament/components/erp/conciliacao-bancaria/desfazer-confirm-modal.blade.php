@if ($this->desfazerConfirmOpen)
    <div class="erp-lookup-modal erp-contador-delete-modal" wire:keydown.escape.window="closeDesfazerConfirm">
        <div class="erp-lookup-modal__backdrop" wire:click="closeDesfazerConfirm"></div>
        <div class="erp-lookup-modal__window erp-contador-delete-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Desfazer conciliação</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeDesfazerConfirm">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-delete-modal__body">
                <p class="erp-contador-delete-modal__message">
                    Remove o vínculo ativo e devolve o extrato a Pendente.
                    Não estorna pagamentos, recebimentos nem movimentos do Livro Caixa.
                </p>
                @if (filled($this->desfazerResumo))
                    <p class="erp-contador-delete-modal__detail">{{ $this->desfazerResumo }}</p>
                @endif
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-delete-modal__actions">
                <button type="button" wire:click="confirmDesfazerConciliacao" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label">Sim</span>
                </button>
                <button type="button" wire:click="closeDesfazerConfirm" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label">Não</span>
                </button>
            </div>
        </div>
    </div>
@endif
