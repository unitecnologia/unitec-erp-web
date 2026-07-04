<div class="erp-pcad-actions erp-orc-actions">
    @unless ($this->orcamentoReadOnly())
        <button type="button" wire:click="gravarOrcamento" class="erp-pcad-actions__btn" data-erp-key="F2">
            <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
            <span class="erp-pcad-actions__label"><kbd>F2</kbd> | Gravar</span>
        </button>
        <button type="button" wire:click="finalizarOrcamento" class="erp-pcad-actions__btn" data-erp-key="F3">
            <span class="erp-pcad-actions__icon">📄</span>
            <span class="erp-pcad-actions__label"><kbd>F3</kbd> | Finalizar</span>
        </button>
        <button type="button" wire:click="openProdutosCadastro" class="erp-pcad-actions__btn" data-erp-key="F8">
            <span class="erp-pcad-actions__icon">📦</span>
            <span class="erp-pcad-actions__label"><kbd>F8</kbd> | Produtos</span>
        </button>
        <button type="button" wire:click="openPessoasCadastro" class="erp-pcad-actions__btn" data-erp-key="F9">
            <span class="erp-pcad-actions__icon">👤</span>
            <span class="erp-pcad-actions__label"><kbd>F9</kbd> | Pessoas</span>
        </button>
    @endunless
    <button type="button" wire:click="handleOrcamentoFormEscape" class="erp-pcad-actions__btn" data-erp-key="Escape">
        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
    </button>
</div>
