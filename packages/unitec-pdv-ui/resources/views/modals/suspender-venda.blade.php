@if ($this->activeModal === 'suspender_venda')
    <x-pdvui::modal-shell
        title="Suspender venda"
        title-id="erp-pdv-suspender-venda-title"
        eyebrow="Cupom"
        subtitle="Identificador opcional — facilita achar a venda na recuperação."
        aria-label="Suspender venda"
        close-action="cancelSuspenderVenda"
        window-class="erp-pdv-modal__window--form erp-pdv-suspender-venda__window"
        wire:keydown.escape="cancelSuspenderVenda"
    >
        <x-slot:icon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="6" y="5" width="4" height="14" rx="1"/>
                <rect x="14" y="5" width="4" height="14" rx="1"/>
            </svg>
        </x-slot:icon>

        <div class="erp-pdv-suspender-venda">
            <label class="erp-pdv-modal__label" for="erp-pdv-suspender-venda-identificador">Identificador <span class="erp-pdv-suspender-venda__opcional">(opcional)</span></label>
            <input
                id="erp-pdv-suspender-venda-identificador"
                type="text"
                wire:model.live.debounce.100ms="vendaEsperaIdentificador"
                wire:keydown.enter.prevent="confirmarSuspenderVenda"
                class="erp-pdv-modal__input"
                data-erp-uppercase
                autocomplete="off"
                maxlength="60"
                placeholder="Ex.: JOÃO, MARIA, BALCÃO 01"
            >
            <p class="erp-pdv-suspender-venda__hint">
                Sem nome, aparece como SEM NOME. Cada espera tem número próprio.
            </p>
        </div>

        <x-slot:footer>
            <button type="button" wire:click="cancelSuspenderVenda" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--ghost">
                <kbd>Esc</kbd> Cancelar
            </button>
            <button
                type="button"
                wire:click="confirmarSuspenderVenda"
                wire:loading.attr="disabled"
                wire:target="confirmarSuspenderVenda"
                class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary"
            >
                <span wire:loading.remove wire:target="confirmarSuspenderVenda"><kbd>Enter</kbd> Suspender</span>
                <span wire:loading wire:target="confirmarSuspenderVenda">Salvando…</span>
            </button>
        </x-slot:footer>
    </x-pdvui::modal-shell>
@endif
