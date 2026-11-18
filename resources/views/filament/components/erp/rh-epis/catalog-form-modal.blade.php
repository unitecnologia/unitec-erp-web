@if ($this->epiCatalogModalOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-epi-catalog-modal"
        wire:keydown.escape.window="closeEpiCatalogModal"
        wire:keydown.f5.window.prevent="saveEpiCatalog"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeEpiCatalogModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $this->epiCatalogRecordId ? 'Alterar equipamento' : 'Novo equipamento' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeEpiCatalogModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Cadastro de EPI</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-epi-cat-cod">Código</label>
                                <input id="rh-epi-cat-cod" type="text" wire:model="epiCatalogForm.codigo" class="erp-pcad-form__input erp-pcad-form__input--xs" @if($this->epiCatalogRecordId) readonly @endif>
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-epi-cat-ca">CA</label>
                                <input id="rh-epi-cat-ca" type="text" wire:model="epiCatalogForm.ca_numero" class="erp-pcad-form__input erp-pcad-form__input--date" maxlength="40">
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-epi-cat-desc">Descrição *</label>
                                <input id="rh-epi-cat-desc" type="text" wire:model.blur="epiCatalogForm.descricao" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="160" data-erp-uppercase autofocus>
                                @error('epiCatalogForm.descricao') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-epi-cat-caval">Val. CA</label>
                                <input id="rh-epi-cat-caval" type="date" wire:model="epiCatalogForm.ca_valido_ate" class="erp-pcad-form__input erp-pcad-form__input--date">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-epi-cat-veq">Val. equip.</label>
                                <input id="rh-epi-cat-veq" type="date" wire:model="epiCatalogForm.validade_equipamento" class="erp-pcad-form__input erp-pcad-form__input--date">
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-epi-cat-fab">Fabricante</label>
                                <input id="rh-epi-cat-fab" type="text" wire:model.blur="epiCatalogForm.fabricante" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="120" data-erp-uppercase>
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-epi-cat-int">Troca (dias)</label>
                                <input id="rh-epi-cat-int" type="number" min="1" max="3650" wire:model="epiCatalogForm.intervalo_troca_dias" class="erp-pcad-form__input erp-pcad-form__input--xs">
                            </div>
                            @if ($this->epiCatalogRecordId)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                    <label class="erp-pcad-form__label" for="rh-epi-cat-ativo">Ativo</label>
                                    <label class="erp-rh-epi-form-modal__check">
                                        <input id="rh-epi-cat-ativo" type="checkbox" wire:model="epiCatalogForm.ativo"> Equipamento ativo
                                    </label>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveEpiCatalog" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeEpiCatalogModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
    @include('filament.components.erp.form-scripts')
@endif
