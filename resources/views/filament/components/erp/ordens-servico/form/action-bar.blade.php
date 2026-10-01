<div class="erp-pcad-actions erp-orc-actions erp-os-actions">
    @unless ($this->osReadOnly())
        <button type="button" wire:click="gravarOs" class="erp-pcad-actions__btn" data-erp-key="F2">
            <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
            <span class="erp-pcad-actions__label"><kbd>F2</kbd> | Gravar</span>
        </button>
        <button type="button" wire:click="finalizarOs" class="erp-pcad-actions__btn" data-erp-key="F3">
            <span class="erp-pcad-actions__icon">📄</span>
            <span class="erp-pcad-actions__label"><kbd>F3</kbd> | Finalizar</span>
        </button>
        <button type="button" class="erp-pcad-actions__btn" data-erp-key="F8" data-erp-os-cadastro="product">
            <span class="erp-pcad-actions__icon">📦</span>
            <span class="erp-pcad-actions__label"><kbd>F8</kbd> | Produtos</span>
        </button>
        <button type="button" class="erp-pcad-actions__btn" data-erp-key="F9" data-erp-os-cadastro="person">
            <span class="erp-pcad-actions__icon">👤</span>
            <span class="erp-pcad-actions__label"><kbd>F9</kbd> | Pessoas</span>
        </button>
    @endunless
    @if ($this->isEditingOs())
        <button type="button" wire:click="openPrintModal" class="erp-pcad-actions__btn" data-erp-key="F6">
            <span class="erp-pcad-actions__icon">🖨</span>
            <span class="erp-pcad-actions__label"><kbd>F6</kbd> | Imprimir</span>
        </button>
    @endif
    <button type="button" wire:click="handleOsFormEscape" class="erp-pcad-actions__btn" data-erp-key="Escape">
        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
    </button>
</div>
