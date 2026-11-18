@if ($this->beneficioCatalogModalOpen)
    @php
        $tipos = [
            \App\Models\RhBeneficio::TIPO_ALIMENTACAO => 'Alimentação',
            \App\Models\RhBeneficio::TIPO_TRANSPORTE => 'Transporte',
            \App\Models\RhBeneficio::TIPO_SAUDE => 'Saúde',
            \App\Models\RhBeneficio::TIPO_ODONTOLOGICO => 'Odontológico',
            \App\Models\RhBeneficio::TIPO_OUTROS => 'Outros',
        ];
        $periodicidades = [
            \App\Models\RhBeneficio::PER_MENSAL => 'Mensal',
            \App\Models\RhBeneficio::PER_QUINZENAL => 'Quinzenal',
            \App\Models\RhBeneficio::PER_ANUAL => 'Anual',
            \App\Models\RhBeneficio::PER_UNICO => 'Único',
        ];
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-beneficio-catalog-modal"
        wire:keydown.escape.window="closeBeneficioCatalogModal"
        wire:keydown.f5.window.prevent="saveBeneficioCatalog"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeBeneficioCatalogModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $this->beneficioCatalogRecordId ? 'Alterar benefício' : 'Novo benefício' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeBeneficioCatalogModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Catálogo de benefícios</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-ben-cat-cod">Código</label>
                                <input id="rh-ben-cat-cod" type="text" wire:model="beneficioCatalogForm.codigo" class="erp-pcad-form__input erp-pcad-form__input--xs" @if($this->beneficioCatalogRecordId) readonly @endif>
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-ben-cat-val">Valor padrão</label>
                                <input id="rh-ben-cat-val" type="text" inputmode="decimal" wire:model="beneficioCatalogForm.valor_padrao" class="erp-pcad-form__input erp-pcad-form__input--date">
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-ben-cat-desc">Descrição *</label>
                                <input id="rh-ben-cat-desc" type="text" wire:model.blur="beneficioCatalogForm.descricao" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="160" data-erp-uppercase autofocus>
                                @error('beneficioCatalogForm.descricao') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-ben-cat-tipo">Tipo *</label>
                                <select id="rh-ben-cat-tipo" wire:model="beneficioCatalogForm.tipo" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                    @foreach ($tipos as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-ben-cat-per">Periodicidade</label>
                                <select id="rh-ben-cat-per" wire:model="beneficioCatalogForm.periodicidade" class="erp-pcad-form__select erp-pcad-form__input--date">
                                    @foreach ($periodicidades as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if ($this->beneficioCatalogRecordId)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                    <label class="erp-pcad-form__label" for="rh-ben-cat-ativo">Ativo</label>
                                    <label class="erp-rh-beneficio-form-modal__check">
                                        <input id="rh-ben-cat-ativo" type="checkbox" wire:model="beneficioCatalogForm.ativo"> Benefício ativo
                                    </label>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveBeneficioCatalog" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeBeneficioCatalogModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
    @include('filament.components.erp.form-scripts')
@endif
