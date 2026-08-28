@if ($this->postSavePromptOpen)
    @teleport('body')
        <div
            class="erp-lookup-modal erp-orc-post-save-modal"
            wire:keydown.escape="handlePostSavePromptEscape"
        >
            <div class="erp-lookup-modal__backdrop" wire:click="continuarOrcamentoAposGravar"></div>

            <div
                class="erp-orc-post-save-modal__window"
                role="dialog"
                aria-modal="true"
                aria-labelledby="erp-orc-post-save-title"
                aria-describedby="erp-orc-post-save-desc"
            >
                <button
                    type="button"
                    class="erp-orc-post-save-modal__close"
                    wire:click="continuarOrcamentoAposGravar"
                    title="Continuar editando"
                    aria-label="Continuar editando"
                >✕</button>

                <div class="erp-orc-post-save-modal__icon" aria-hidden="true">✓</div>

                <h2 id="erp-orc-post-save-title" class="erp-orc-post-save-modal__title">
                    Orçamento gravado
                </h2>

                <p id="erp-orc-post-save-desc" class="erp-orc-post-save-modal__lead">
                    O orçamento foi salvo. Escolha o próximo passo.
                </p>

                <div class="erp-orc-post-save-modal__card">
                    <span class="erp-orc-post-save-modal__code">Nº {{ $this->orcamentoNumeroDisplay() }}</span>
                    <p class="erp-orc-post-save-modal__total">Total {{ $this->totalDisplay }}</p>
                </div>

                <div class="erp-orc-post-save-modal__actions">
                    <button
                        type="button"
                        wire:click="iniciarNovoOrcamento"
                        class="erp-orc-post-save-modal__btn erp-orc-post-save-modal__btn--primary"
                    >
                        Novo orçamento
                    </button>
                    <button
                        type="button"
                        wire:click="sairAposGravarOrcamento"
                        class="erp-orc-post-save-modal__btn erp-orc-post-save-modal__btn--ghost"
                        id="erp-orc-post-save-sair"
                    >
                        Sair
                    </button>
                    <button
                        type="button"
                        wire:click="continuarOrcamentoAposGravar"
                        class="erp-orc-post-save-modal__btn erp-orc-post-save-modal__btn--ghost"
                    >
                        Continuar
                    </button>
                </div>

                <p class="erp-orc-post-save-modal__hint">Esc sai da tela</p>
            </div>
        </div>
    @endteleport
@endif
