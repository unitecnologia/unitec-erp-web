@if ($this->treinamentoCatalogModalOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-treinamento-catalog-modal"
        wire:keydown.escape.window="closeTreinamentoCatalogModal"
        wire:keydown.f5.window.prevent="saveTreinamentoCatalog"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeTreinamentoCatalogModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $this->treinamentoCatalogRecordId ? 'Alterar treinamento' : 'Novo treinamento' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeTreinamentoCatalogModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Cadastro de treinamento</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-trein-cat-cod">Código</label>
                                <input id="rh-trein-cat-cod" type="text" wire:model="treinamentoCatalogForm.codigo" class="erp-pcad-form__input erp-pcad-form__input--xs" @if($this->treinamentoCatalogRecordId) readonly @endif>
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-trein-cat-tipo">Tipo</label>
                                <input id="rh-trein-cat-tipo" type="text" wire:model.blur="treinamentoCatalogForm.tipo" class="erp-pcad-form__input erp-pcad-form__input--date" maxlength="60" data-erp-uppercase>
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-trein-cat-nome">Nome *</label>
                                <input id="rh-trein-cat-nome" type="text" wire:model.blur="treinamentoCatalogForm.nome" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="160" data-erp-uppercase autofocus>
                                @error('treinamentoCatalogForm.nome') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-trein-cat-desc">Descrição</label>
                                <textarea id="rh-trein-cat-desc" rows="2" maxlength="2000" wire:model="treinamentoCatalogForm.descricao" class="erp-pcad-form__textarea erp-pcad-form__input--grow"></textarea>
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-trein-cat-carga">Carga h.</label>
                                <input id="rh-trein-cat-carga" type="number" min="0" max="9999.99" step="0.25" wire:model="treinamentoCatalogForm.carga_horaria" class="erp-pcad-form__input erp-pcad-form__input--xs">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-trein-cat-per">Periodicidade</label>
                                <input id="rh-trein-cat-per" type="number" min="1" max="3650" wire:model="treinamentoCatalogForm.periodicidade_dias" class="erp-pcad-form__input erp-pcad-form__input--xs" title="Dias até o vencimento (opcional)">
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-trein-cat-inst">Instrutor</label>
                                <input id="rh-trein-cat-inst" type="text" wire:model.blur="treinamentoCatalogForm.instrutor" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="120" data-erp-uppercase>
                            </div>
                            @if ($this->treinamentoCatalogRecordId)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                    <label class="erp-pcad-form__label" for="rh-trein-cat-ativo">Ativo</label>
                                    <label class="erp-rh-treinamento-form-modal__check">
                                        <input id="rh-trein-cat-ativo" type="checkbox" wire:model="treinamentoCatalogForm.ativo"> Treinamento ativo
                                    </label>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveTreinamentoCatalog" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeTreinamentoCatalogModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
    @include('filament.components.erp.form-scripts')
@endif
