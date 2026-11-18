@if ($this->feriasPeriodoModalOpen)
    @php $funcionarios = $this->feriasFuncionarioOptions(); @endphp
    <div class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-ferias-periodo-modal" wire:keydown.escape.window="closeFeriasPeriodoModal">
        <div class="erp-lookup-modal__backdrop" wire:click="closeFeriasPeriodoModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Gerar período aquisitivo</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeFeriasPeriodoModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form" autocomplete="off">
                    <p class="erp-rh-ferias-form-modal__hint">Período aquisitivo de 12 meses e concessivo de 12 meses após o fim do aquisitivo, a partir da admissão ou do último período.</p>
                    <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                        <label class="erp-pcad-form__label" for="rh-ferp-func">Funcionário *</label>
                        <select id="rh-ferp-func" wire:model="feriasPeriodoForm.funcionario_id" class="erp-pcad-form__select erp-pcad-form__input--grow">
                            <option value="">Selecione…</option>
                            @foreach ($funcionarios as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        @error('feriasPeriodoForm.funcionario_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                    </div>
                    <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                        <label class="erp-pcad-form__label" for="rh-ferp-dias">Dias de direito</label>
                        <input id="rh-ferp-dias" type="number" min="1" max="365" wire:model="feriasPeriodoForm.dias_direito" class="erp-pcad-form__input">
                    </div>
                    <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                        <label class="erp-pcad-form__label" for="rh-ferp-obs">Observação</label>
                        <textarea id="rh-ferp-obs" rows="2" maxlength="500" wire:model="feriasPeriodoForm.observacao" class="erp-pcad-form__textarea erp-pcad-form__input--grow"></textarea>
                    </div>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions">
                <button type="button" wire:click="saveFeriasPeriodo" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label">Gerar</span>
                </button>
                <button type="button" wire:click="closeFeriasPeriodoModal" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--cancel">✕</span>
                    <span class="erp-pcad-actions__label">Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
