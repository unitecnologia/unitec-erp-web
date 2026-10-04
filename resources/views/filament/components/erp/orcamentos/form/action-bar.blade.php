<div class="erp-pcad-actions erp-orc-actions">
    @unless ($this->orcamentoReadOnly())
        <button type="button" wire:click="gravarOrcamento" wire:loading.attr="disabled" wire:target="gravarOrcamento,finalizarOrcamento" @disabled($this->orcamentoPersistindo) class="erp-pcad-actions__btn" data-erp-key="F2">
            <span class="erp-fv-tv-btn__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 13l4 4L19 7"/>
                </svg>
            </span>
            <span class="erp-pcad-actions__label"><kbd>F2</kbd> | Gravar</span>
        </button>
        <button type="button" wire:click="finalizarOrcamento" wire:loading.attr="disabled" wire:target="gravarOrcamento,finalizarOrcamento" @disabled($this->orcamentoPersistindo) class="erp-pcad-actions__btn" data-erp-key="F3">
            <span class="erp-fv-tv-btn__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <path d="M14 2v6h6"/>
                    <path d="M9 15l2 2 4-4"/>
                </svg>
            </span>
            <span class="erp-pcad-actions__label"><kbd>F3</kbd> | Finalizar</span>
        </button>
        <button type="button" wire:click="openProdutosCadastro" class="erp-pcad-actions__btn" data-erp-key="F8">
            <span class="erp-fv-tv-btn__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    <path d="M3.27 6.96L12 12.01l8.73-5.05"/>
                    <path d="M12 22.08V12"/>
                </svg>
            </span>
            <span class="erp-pcad-actions__label"><kbd>F8</kbd> | Produtos</span>
        </button>
        <button type="button" wire:click="openPessoasCadastro" class="erp-pcad-actions__btn" data-erp-key="F9">
            <span class="erp-fv-tv-btn__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </span>
            <span class="erp-pcad-actions__label"><kbd>F9</kbd> | Pessoas</span>
        </button>
    @endunless
    <button type="button" wire:click="abrirObservacaoOrcamento" class="erp-pcad-actions__btn erp-orc-actions__obs" title="Observação do orçamento">
        <span class="erp-fv-tv-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/>
                <path d="M14 3v6h6"/>
                <path d="M8 13h8"/>
                <path d="M8 17h5"/>
            </svg>
        </span>
        <span class="erp-pcad-actions__label">Observação do orçamento</span>
    </button>
    <button type="button" wire:click="handleOrcamentoFormEscape" class="erp-pcad-actions__btn" data-erp-key="Escape">
        <span class="erp-fv-tv-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <path d="M16 17l5-5-5-5"/>
                <path d="M21 12H9"/>
            </svg>
        </span>
        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
    </button>
</div>
