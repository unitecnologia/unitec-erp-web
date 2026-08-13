<div class="erp-os-actions">
    @unless ($this->osReadOnly())
        <button type="button" wire:click="gravarOs" class="erp-os-actions__btn erp-os-actions__btn--save" data-erp-key="F2">
            <span class="erp-os-actions__icon">✓</span>
            <span class="erp-os-actions__label"><kbd>F2</kbd> | Gravar</span>
        </button>
        <button type="button" wire:click="finalizarOs" class="erp-os-actions__btn" data-erp-key="F3">
            <span class="erp-os-actions__icon">📄</span>
            <span class="erp-os-actions__label"><kbd>F3</kbd> | Finalizar</span>
        </button>
        <button type="button" wire:click="openProdutosCadastro" class="erp-os-actions__btn" data-erp-key="F8">
            <span class="erp-os-actions__icon">📦</span>
            <span class="erp-os-actions__label"><kbd>F8</kbd> | Produtos</span>
        </button>
        <button type="button" wire:click="openPessoasCadastro" class="erp-os-actions__btn" data-erp-key="F9">
            <span class="erp-os-actions__icon">👤</span>
            <span class="erp-os-actions__label"><kbd>F9</kbd> | Pessoas</span>
        </button>
    @endunless
    <button type="button" wire:click="handleOsFormEscape" class="erp-os-actions__btn erp-os-actions__btn--exit" data-erp-key="Escape">
        <span class="erp-os-actions__icon">✕</span>
        <span class="erp-os-actions__label"><kbd>ESC</kbd> | Sair</span>
    </button>
</div>
