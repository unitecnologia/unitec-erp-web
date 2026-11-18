@if ($this->feriasGozoModalOpen)
    @php
        $funcionarios = $this->feriasFuncionarioOptions();
        $periodos = $this->feriasPeriodoOptions();
        $isEdit = filled($this->feriasGozoModalRecordId);
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-ferias-form-modal"
        wire:keydown.escape.window="closeFeriasGozoModal"
        wire:keydown.f5.window.prevent="saveFeriasGozo"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeFeriasGozoModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $isEdit ? 'Alterar gozo de férias' : 'Programar gozo de férias' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeFeriasGozoModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Gozo</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            @if (! $isEdit)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-fer-func">Funcionário *</label>
                                    <select id="rh-fer-func" wire:model.live="feriasGozoForm.funcionario_id" class="erp-pcad-form__select erp-pcad-form__input--grow" autofocus>
                                        <option value="">Filtrar períodos…</option>
                                        @foreach ($funcionarios as $opt)
                                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-fer-per">Período aquisitivo *</label>
                                <select id="rh-fer-per" wire:model="feriasGozoForm.periodo_id" class="erp-pcad-form__select erp-pcad-form__input--grow" @disabled($isEdit)>
                                    <option value="">Selecione…</option>
                                    @foreach ($periodos as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('feriasGozoForm.periodo_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-fer-ini">Início *</label>
                                <input id="rh-fer-ini" type="date" wire:model="feriasGozoForm.gozo_inicio" class="erp-pcad-form__input erp-pcad-form__input--date">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-fer-fim">Fim *</label>
                                <input id="rh-fer-fim" type="date" wire:model="feriasGozoForm.gozo_fim" class="erp-pcad-form__input erp-pcad-form__input--date">
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-fer-aviso">Data aviso</label>
                                <input id="rh-fer-aviso" type="date" wire:model="feriasGozoForm.aviso_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                            </div>
                            <p class="erp-rh-ferias-form-modal__hint">Recomendado avisar com pelo menos 30 dias de antecedência (CLT). Validação de fracionamento: até 3 frações; uma com ≥14 dias; demais ≥5 dias.</p>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-fer-arq">Aviso (PDF)</label>
                                <input id="rh-fer-arq" type="file" wire:model="feriasAvisoUpload" class="erp-pcad-form__input erp-pcad-form__input--file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
                                @if ($isEdit && filled($this->feriasAvisoArquivoAtual))
                                    <span class="erp-rh-ferias-form-modal__hint">Atual: {{ $this->feriasAvisoArquivoAtual }}</span>
                                @endif
                                @error('feriasAvisoUpload') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-fer-obs">Observação</label>
                                <textarea id="rh-fer-obs" rows="2" maxlength="500" wire:model="feriasGozoForm.observacao" class="erp-pcad-form__textarea erp-pcad-form__input--grow"></textarea>
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-rh-ferias-form-modal__check">
                                    <input type="checkbox" wire:model="feriasGozoForm.ajuste_admin"> Ajuste administrativo (dispensa regras CLT)
                                </label>
                            </div>
                            @if (! empty($this->feriasGozoForm['ajuste_admin']))
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-fer-mot">Motivo ajuste *</label>
                                    <input id="rh-fer-mot" type="text" maxlength="255" wire:model="feriasGozoForm.motivo_ajuste" class="erp-pcad-form__input erp-pcad-form__input--grow">
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveFeriasGozo" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeFeriasGozoModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--cancel">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>Esc</kbd> | Cancelar</span>
                </button>
            </div>
        </div>
    </div>
@endif
