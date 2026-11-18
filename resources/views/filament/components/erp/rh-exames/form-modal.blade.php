@if ($this->exameModalOpen)
    @php
        $funcionarios = $this->exameFuncionarioOptions();
        $tipos = \App\Models\RhExame::tipos();
        $conclusoes = \App\Models\RhExame::conclusoes();
        $isEdit = filled($this->exameModalRecordId);
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-exame-form-modal"
        wire:keydown.escape.window="closeExameModal"
        wire:keydown.f5.window.prevent="saveExame"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeExameModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $isEdit ? 'Alterar exame / ASO' : 'Novo exame / ASO' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeExameModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Cadastro</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-ex-func">Funcionário *</label>
                                <select id="rh-ex-func" wire:model="exameForm.funcionario_id" class="erp-pcad-form__select erp-pcad-form__input--grow" autofocus>
                                    <option value="">Selecione…</option>
                                    @foreach ($funcionarios as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('exameForm.funcionario_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-ex-tipo">Tipo *</label>
                                <select id="rh-ex-tipo" wire:model="exameForm.tipo" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                    @foreach ($tipos as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-ex-data">Data exame *</label>
                                <input id="rh-ex-data" type="date" wire:model="exameForm.realizado_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-ex-prox">Próx. exame</label>
                                <input id="rh-ex-prox" type="date" wire:model="exameForm.proximo_em" class="erp-pcad-form__input erp-pcad-form__input--date" title="Informar conforme PCMSO — sem data não presume vencimento">
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-ex-med">Médico</label>
                                <input id="rh-ex-med" type="text" wire:model.blur="exameForm.medico_nome" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="120" data-erp-uppercase>
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-ex-crm">CRM</label>
                                <input id="rh-ex-crm" type="text" wire:model="exameForm.medico_crm" class="erp-pcad-form__input erp-pcad-form__input--date" maxlength="40">
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-ex-conc">Conclusão *</label>
                                <select id="rh-ex-conc" wire:model="exameForm.conclusao" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                    @foreach ($conclusoes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="erp-rh-exame-form-modal__hint">Conclusão conforme ASO emitido. O ERP não assina nem substitui o documento médico.</p>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-ex-aso">ASO (PDF)</label>
                                <input id="rh-ex-aso" type="file" wire:model="exameUpload" class="erp-pcad-form__input erp-pcad-form__input--file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
                                @if ($isEdit && filled($this->exameArquivoAtual))
                                    <span class="erp-rh-exame-form-modal__hint">Atual: {{ $this->exameArquivoAtual }}</span>
                                @endif
                                <span wire:loading wire:target="exameUpload" class="erp-rh-exame-form-modal__hint">Enviando…</span>
                                @error('exameUpload') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-ex-obs">Observação</label>
                                <textarea id="rh-ex-obs" rows="2" maxlength="500" wire:model="exameForm.observacao" class="erp-pcad-form__textarea erp-pcad-form__input--grow" placeholder="Somente observação administrativa (sem dados clínicos)"></textarea>
                            </div>

                            @if ($isEdit)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                    <label class="erp-pcad-form__label" for="rh-ex-ativo">Ativo</label>
                                    <label class="erp-rh-exame-form-modal__check">
                                        <input id="rh-ex-ativo" type="checkbox" wire:model="exameForm.ativo"> Registro ativo
                                    </label>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveExame" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeExameModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
    @include('filament.components.erp.form-scripts')
@endif
