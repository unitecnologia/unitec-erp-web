<div class="erp-produtos-pcad__footer erp-terminais-pcad__footer">
    <div class="erp-pcad-actions erp-terminais-pcad__actions">
        @if ($this->activeTerminalTab === 'aparelhos')
            <button type="button" wire:click="autorizarAparelhoSelecionado" class="erp-pcad-actions__btn" data-erp-key="F2">
                <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✔</span>
                <span class="erp-pcad-actions__label"><kbd>F2</kbd> | Autorizar</span>
            </button>
            <button type="button" wire:click="revogarAparelhoSelecionado" class="erp-pcad-actions__btn" data-erp-key="F4">
                <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">⛔</span>
                <span class="erp-pcad-actions__label"><kbd>F4</kbd> | Revogar</span>
            </button>
            <button type="button" wire:click="$refresh" class="erp-pcad-actions__btn" data-erp-key="F5">
                <span class="erp-pcad-actions__icon">↻</span>
                <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Atualizar</span>
            </button>
        @else
            <button type="button" wire:click="deleteTerminal" class="erp-pcad-actions__btn" data-erp-key="F4">
                <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                <span class="erp-pcad-actions__label"><kbd>F4</kbd> | Excluir Terminal</span>
            </button>
            <button type="button" wire:click="saveTerminalForm" class="erp-pcad-actions__btn" data-erp-key="F10">
                <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                <span class="erp-pcad-actions__label"><kbd>F10</kbd> | Salvar</span>
            </button>
        @endif
        <button type="button" wire:click="closeScreen" class="erp-pcad-actions__btn" data-erp-key="Escape">
            <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
            <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
        </button>
    </div>
</div>
