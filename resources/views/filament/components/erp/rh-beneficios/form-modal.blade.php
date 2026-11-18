@if ($this->vinculoModalOpen)
    @php
        $funcionarios = $this->vinculoFuncionarioOptions();
        $beneficios = $this->vinculoBeneficioOptions();
        $titulo = $this->vinculoModalRecordId ? 'Alterar vínculo de benefício' : 'Novo vínculo de benefício';
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-beneficio-form-modal"
        wire:keydown.escape.window="closeVinculoModal"
        wire:keydown.f5.window.prevent="saveVinculo"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeVinculoModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $titulo }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeVinculoModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Vínculo</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-ben-func">Funcionário *</label>
                                <select id="rh-ben-func" wire:model="vinculoForm.funcionario_id" class="erp-pcad-form__select erp-pcad-form__input--grow" autofocus>
                                    <option value="">Selecione…</option>
                                    @foreach ($funcionarios as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('vinculoForm.funcionario_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-ben-item">Benefício *</label>
                                <select id="rh-ben-item" wire:model.live="vinculoForm.beneficio_id" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                    <option value="">Selecione…</option>
                                    @foreach ($beneficios as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="erp-pcad-form__btn" wire:click="openBeneficioCatalogCreate" title="Novo benefício">+</button>
                                @if (filled($this->vinculoForm['beneficio_id'] ?? null))
                                    <button type="button" class="erp-pcad-form__btn" wire:click="openBeneficioCatalogEdit({{ (int) $this->vinculoForm['beneficio_id'] }})" title="Alterar benefício">✎</button>
                                @endif
                                @error('vinculoForm.beneficio_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-ben-ini">Início *</label>
                                <input id="rh-ben-ini" type="date" wire:model="vinculoForm.inicio_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-ben-fim">Término</label>
                                <input id="rh-ben-fim" type="date" wire:model="vinculoForm.termino_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-ben-val">Valor *</label>
                                <input id="rh-ben-val" type="text" inputmode="decimal" wire:model="vinculoForm.valor" class="erp-pcad-form__input erp-pcad-form__input--date">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-ben-part">Part. funcionário</label>
                                <input id="rh-ben-part" type="text" inputmode="decimal" wire:model="vinculoForm.participacao_funcionario" class="erp-pcad-form__input erp-pcad-form__input--date">
                            </div>

                            @if ($this->vinculoModalRecordId)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-ben-motivo">Motivo alteração</label>
                                    <input id="rh-ben-motivo" type="text" wire:model="vinculoForm.motivo_alteracao" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255" placeholder="Recomendado ao mudar valor">
                                </div>
                            @endif

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-ben-obs">Observação</label>
                                <textarea id="rh-ben-obs" rows="2" maxlength="500" wire:model="vinculoForm.observacao" class="erp-pcad-form__textarea erp-pcad-form__input--grow"></textarea>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveVinculo" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeVinculoModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
    @include('filament.components.erp.form-scripts')
@endif
