@if ($this->postSavePromptOpen)
    <div
        class="erp-lookup-modal erp-orc-post-save-modal"
        wire:keydown.escape="handlePostSavePromptEscape"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="continuarOrcamentoAposGravar"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-orc-post-save-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-orc-post-save-title">Orçamento gravado</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="continuarOrcamentoAposGravar"
                    title="Continuar editando"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-orc-post-save-modal__body">
                <p class="erp-orc-post-save-modal__message">
                    Orçamento gravado com sucesso. Deseja sair da tela ou iniciar um novo orçamento?
                </p>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions">
                <button type="button" wire:click="iniciarNovoOrcamento" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">+</span>
                    <span class="erp-pcad-actions__label">Novo orçamento</span>
                </button>
                <button type="button" wire:click="sairAposGravarOrcamento" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
                <button type="button" wire:click="continuarOrcamentoAposGravar" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">↩</span>
                    <span class="erp-pcad-actions__label">Continuar editando</span>
                </button>
            </div>
        </div>
    </div>
@endif
