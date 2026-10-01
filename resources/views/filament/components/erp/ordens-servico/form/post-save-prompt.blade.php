@if ($this->postSavePromptOpen)
    @teleport('body')
        <div
            class="erp-lookup-modal erp-orc-post-save-modal"
            wire:keydown.escape="handlePostSavePromptEscape"
        >
            <div class="erp-lookup-modal__backdrop" wire:click="continuarOsAposGravar"></div>

            <div
                class="erp-orc-post-save-modal__window"
                role="dialog"
                aria-modal="true"
                aria-labelledby="erp-os-post-save-title"
                aria-describedby="erp-os-post-save-desc"
            >
                <button
                    type="button"
                    class="erp-orc-post-save-modal__close"
                    wire:click="continuarOsAposGravar"
                    title="Continuar editando"
                    aria-label="Continuar editando"
                >✕</button>

                <div class="erp-orc-post-save-modal__icon" aria-hidden="true">✓</div>

                <h2 id="erp-os-post-save-title" class="erp-orc-post-save-modal__title">
                    Ordem de serviço gravada
                </h2>

                <p id="erp-os-post-save-desc" class="erp-orc-post-save-modal__lead">
                    A OS foi salva. Escolha o próximo passo.
                </p>

                <div class="erp-orc-post-save-modal__card">
                    <span class="erp-orc-post-save-modal__code">Nº {{ $this->osNumeroDisplay() }}</span>
                    <p class="erp-orc-post-save-modal__total">Total {{ $this->totalGeral }}</p>
                </div>

                <div class="erp-orc-post-save-modal__actions">
                    <button
                        type="button"
                        wire:click="iniciarNovaOs"
                        class="erp-orc-post-save-modal__btn erp-orc-post-save-modal__btn--primary"
                    >
                        Nova OS
                    </button>
                    <button
                        type="button"
                        wire:click="sairAposGravarOs"
                        class="erp-orc-post-save-modal__btn erp-orc-post-save-modal__btn--ghost"
                        id="erp-os-post-save-sair"
                    >
                        Sair
                    </button>
                    <button
                        type="button"
                        wire:click="continuarOsAposGravar"
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
