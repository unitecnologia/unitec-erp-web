@if ($this->vinculoModalOpen)
    @php
        $funcionarios = $this->vinculoFuncionarioOptions();
        $escalas = $this->escalasOpcoes();
        $isEdit = filled($this->vinculoModalRecordId);
    @endphp
    <div class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-escala-vinculo-modal" wire:keydown.escape.window="closeVinculoModal" wire:keydown.f5.window.prevent="saveVinculo">
        <div class="erp-lookup-modal__backdrop" wire:click="closeVinculoModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $isEdit ? 'Alterar vínculo' : 'Novo vínculo de escala' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeVinculoModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form" data-erp-form autocomplete="off">
                    <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                        <label class="erp-pcad-form__label" for="rh-esc-v-func">Funcionário *</label>
                        <select id="rh-esc-v-func" wire:model="vinculoForm.funcionario_id" class="erp-pcad-form__select erp-pcad-form__input--grow" autofocus>
                            <option value="">Selecione…</option>
                            @foreach ($funcionarios as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        @error('vinculoForm.funcionario_id') <span class="erp-contador-form-modal__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                        <label class="erp-pcad-form__label" for="rh-esc-v-esc">Escala *</label>
                        <div class="erp-rh-escalas__escala-row">
                            <select id="rh-esc-v-esc" wire:model="vinculoForm.escala_id" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                <option value="">Selecione…</option>
                                @foreach ($escalas as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="erp-rh-escalas__mini-btn" wire:click="openEscalaCatalogEdit" title="Alterar modelo de escala">✎</button>
                        </div>
                        @error('vinculoForm.escala_id') <span class="erp-contador-form-modal__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                        <label class="erp-pcad-form__label" for="rh-esc-v-ini">Início *</label>
                        <input id="rh-esc-v-ini" type="date" wire:model="vinculoForm.inicio_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-esc-v-fim">Término</label>
                        <input id="rh-esc-v-fim" type="date" wire:model="vinculoForm.termino_em" class="erp-pcad-form__input erp-pcad-form__input--date" title="Opcional — deixe vazio para vigência aberta">
                    </div>
                    <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                        <label class="erp-pcad-form__label" for="rh-esc-v-obs">Observação</label>
                        <textarea id="rh-esc-v-obs" rows="2" maxlength="500" wire:model="vinculoForm.observacao" class="erp-pcad-form__textarea erp-pcad-form__input--grow"></textarea>
                    </div>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions">
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
