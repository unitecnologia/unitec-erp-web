@if ($this->embedsInPdv)
    <div class="erp-pcad-actions">
        <button type="button" onclick="window.saveErpProdutosForm?.()" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F5">
            <span class="erp-pcad-actions__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            </span>
            <span class="erp-pcad-actions__label"><kbd>F5</kbd> Salvar</span>
        </button>
        <button type="button" wire:click="cancelForm" class="erp-pcad-actions__btn" data-erp-key="Escape">
            <span class="erp-pcad-actions__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </span>
            <span class="erp-pcad-actions__label"><kbd>ESC</kbd> Sair</span>
        </button>
    </div>
@else
    <div class="erp-produtos-pcad__footer">
        <div class="erp-pcad-actions">
            <button type="button" onclick="window.saveErpProdutosForm?.()" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F5">
                <span class="erp-pcad-actions__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                </span>
                <span class="erp-pcad-actions__label"><kbd>F5</kbd> Salvar</span>
            </button>
            @if ($this->isEditingProduct())
                <button type="button" wire:click="openProductCardex" class="erp-pcad-actions__btn" data-erp-key="F7">
                    <span class="erp-pcad-actions__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>
                    </span>
                    <span class="erp-pcad-actions__label"><kbd>F7</kbd> Histórico</span>
                </button>
            @endif
            <button type="button" wire:click="cancelForm" class="erp-pcad-actions__btn erp-pcad-actions__btn--danger" data-erp-key="Escape">
                <span class="erp-pcad-actions__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </span>
                <span class="erp-pcad-actions__label"><kbd>ESC</kbd> Sair</span>
            </button>
        </div>

        <div class="erp-produtos-pcad__hints">
            @unless ($this->isEditingProduct())
                <p>Grade e Composição disponíveis após gravar.</p>
            @endunless
        </div>
    </div>
@endif
