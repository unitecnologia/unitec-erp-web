@if ($this->erpCnModalOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal"
        wire:keydown.escape.window="closeErpCnModal"
        wire:keydown.f5.window.prevent="saveErpCn"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeErpCnModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-codigo-nome-modal-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-codigo-nome-modal-title">{{ $modalTitle }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeErpCnModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Cadastro</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="erp-cn-codigo">Código</label>
                                <input
                                    id="erp-cn-codigo"
                                    type="text"
                                    wire:model="erpCnForm.codigo"
                                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                                    @if ($this->erpCnModalRecordId) readonly @endif
                                >
                                @error('erpCnForm.codigo')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="erp-cn-nome">{{ $nomeLabel }}</label>
                                <input
                                    id="erp-cn-nome"
                                    type="text"
                                    wire:model="erpCnForm.nome"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                                    data-erp-uppercase
                                    autofocus
                                >
                                @error('erpCnForm.nome')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveErpCn" wire:loading.attr="disabled" wire:target="saveErpCn" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="saveErpCn"><kbd>F5</kbd> | Gravar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="saveErpCn">Salvando…</span>
                </button>
                <button type="button" wire:click="closeErpCnModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
