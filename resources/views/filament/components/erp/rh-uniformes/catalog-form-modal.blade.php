@if ($this->uniformeCatalogModalOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-uniforme-catalog-modal"
        wire:keydown.escape.window="closeUniformeCatalogModal"
        wire:keydown.f5.window.prevent="saveUniformeCatalog"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeUniformeCatalogModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $this->uniformeCatalogRecordId ? 'Alterar uniforme' : 'Novo uniforme' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeUniformeCatalogModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Cadastro de uniforme</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-unif-cat-cod">Código</label>
                                <input id="rh-unif-cat-cod" type="text" wire:model="uniformeCatalogForm.codigo" class="erp-pcad-form__input erp-pcad-form__input--xs" @if($this->uniformeCatalogRecordId) readonly @endif>
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-unif-cat-tipo">Tipo</label>
                                <select id="rh-unif-cat-tipo" wire:model="uniformeCatalogForm.tipo" class="erp-pcad-form__select erp-pcad-form__input--date">
                                    <option value="">—</option>
                                    <option value="camisa">Camisa</option>
                                    <option value="calça">Calça</option>
                                    <option value="jaleco">Jaleco</option>
                                    <option value="calçado">Calçado</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-unif-cat-desc">Descrição *</label>
                                <input id="rh-unif-cat-desc" type="text" wire:model.blur="uniformeCatalogForm.descricao" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="160" data-erp-uppercase autofocus>
                                @error('uniformeCatalogForm.descricao') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-unif-cat-tam">Tamanho</label>
                                <input id="rh-unif-cat-tam" type="text" wire:model="uniformeCatalogForm.tamanho" class="erp-pcad-form__input erp-pcad-form__input--xs" maxlength="20" placeholder="P/M/G/GG/nº" list="rh-unif-tamanhos">
                                <datalist id="rh-unif-tamanhos">
                                    <option value="P"></option>
                                    <option value="M"></option>
                                    <option value="G"></option>
                                    <option value="GG"></option>
                                    <option value="XG"></option>
                                </datalist>
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-unif-cat-cor">Cor</label>
                                <input id="rh-unif-cat-cor" type="text" wire:model.blur="uniformeCatalogForm.cor" class="erp-pcad-form__input erp-pcad-form__input--date" maxlength="40" data-erp-uppercase>
                            </div>
                            @if ($this->uniformeCatalogRecordId)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                    <label class="erp-pcad-form__label" for="rh-unif-cat-ativo">Ativo</label>
                                    <label class="erp-rh-uniforme-form-modal__check">
                                        <input id="rh-unif-cat-ativo" type="checkbox" wire:model="uniformeCatalogForm.ativo"> Uniforme ativo
                                    </label>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveUniformeCatalog" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeUniformeCatalogModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
    @include('filament.components.erp.form-scripts')
@endif
