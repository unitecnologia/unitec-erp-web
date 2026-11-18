@if ($this->ocorrenciaModalOpen)
    @php
        $funcionarios = $this->ocorrenciaFuncionarioOptions();
        $tipos = \App\Models\RhOcorrencia::tipos();
        $isEdit = filled($this->ocorrenciaModalRecordId);
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-ocorrencia-form-modal"
        wire:keydown.escape.window="closeOcorrenciaModal"
        wire:keydown.f5.window.prevent="saveOcorrencia"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeOcorrenciaModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $isEdit ? 'Alterar ocorrência' : 'Nova ocorrência' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeOcorrenciaModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Registro</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-oc-func">Funcionário *</label>
                                <select id="rh-oc-func" wire:model="ocorrenciaForm.funcionario_id" class="erp-pcad-form__select erp-pcad-form__input--grow" autofocus>
                                    <option value="">Selecione…</option>
                                    @foreach ($funcionarios as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('ocorrenciaForm.funcionario_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-oc-tipo">Tipo *</label>
                                <select id="rh-oc-tipo" wire:model="ocorrenciaForm.tipo" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                    @foreach ($tipos as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-oc-data">Data da ocorrência *</label>
                                <input id="rh-oc-data" type="date" wire:model="ocorrenciaForm.ocorrido_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-oc-resp">Responsável</label>
                                <input id="rh-oc-resp" type="text" wire:model.blur="ocorrenciaForm.responsavel" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="120" data-erp-uppercase>
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-oc-desc">Descrição *</label>
                                <textarea id="rh-oc-desc" rows="3" maxlength="1000" wire:model="ocorrenciaForm.descricao" class="erp-pcad-form__textarea erp-pcad-form__input--grow" placeholder="Descreva o fato (evite diagnóstico clínico; use acesso restrito se necessário)"></textarea>
                                @error('ocorrenciaForm.descricao') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-oc-restrito">Acesso restrito</label>
                                <label class="erp-rh-ocorrencia-form-modal__check">
                                    <input id="rh-oc-restrito" type="checkbox" wire:model="ocorrenciaForm.acesso_restrito"> Conteúdo sensível (exige permissão de acesso)
                                </label>
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-oc-anexo">Anexo (opcional)</label>
                                <input id="rh-oc-anexo" type="file" wire:model="ocorrenciaUpload" class="erp-pcad-form__input erp-pcad-form__input--file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,application/pdf,image/*">
                                @if ($isEdit && filled($this->ocorrenciaArquivoAtual))
                                    <span class="erp-rh-ocorrencia-form-modal__hint">Atual: {{ $this->ocorrenciaArquivoAtual }}</span>
                                @endif
                                <span wire:loading wire:target="ocorrenciaUpload" class="erp-rh-ocorrencia-form-modal__hint">Enviando…</span>
                                @error('ocorrenciaUpload') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </section>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveOcorrencia" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeOcorrenciaModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>Esc</kbd> | Cancelar</span>
                </button>
            </div>
        </div>
    </div>
@endif
