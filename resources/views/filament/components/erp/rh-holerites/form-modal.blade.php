@if ($this->holeriteModalOpen)
    @php
        $funcionarios = $this->holeriteFuncionarioOptions();
        $meses = \App\Models\RhHolerite::meses();
        $isEdit = filled($this->holeriteModalRecordId);
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-holerite-form-modal"
        wire:keydown.escape.window="closeHoleriteModal"
        wire:keydown.f5.window.prevent="saveHolerite"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeHoleriteModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $isEdit ? 'Alterar holerite' : 'Novo holerite' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeHoleriteModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Competência e PDF</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-hol-func">Funcionário *</label>
                                <select id="rh-hol-func" wire:model="holeriteForm.funcionario_id" class="erp-pcad-form__select erp-pcad-form__input--grow" autofocus>
                                    <option value="">Selecione…</option>
                                    @foreach ($funcionarios as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('holeriteForm.funcionario_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-hol-ano-form">Ano *</label>
                                <input id="rh-hol-ano-form" type="number" min="2000" max="2100" wire:model="holeriteForm.competencia_ano" class="erp-pcad-form__input erp-pcad-form__input--date">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-hol-mes-form">Mês *</label>
                                <select id="rh-hol-mes-form" wire:model="holeriteForm.competencia_mes" class="erp-pcad-form__select erp-pcad-form__input--date">
                                    @foreach ($meses as $num => $nome)
                                        <option value="{{ $num }}">{{ sprintf('%02d', $num) }} — {{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-hol-pago">Pago em</label>
                                <input id="rh-hol-pago" type="date" wire:model="holeriteForm.pago_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-hol-pdf">PDF holerite{{ $isEdit ? '' : ' *' }}</label>
                                <input id="rh-hol-pdf" type="file" wire:model="holeriteUpload" class="erp-pcad-form__input erp-pcad-form__input--file" accept=".pdf,application/pdf,image/*">
                                @if ($isEdit && filled($this->holeriteArquivoAtual))
                                    <span class="erp-rh-holerite-form-modal__hint">Atual: {{ $this->holeriteArquivoAtual }}</span>
                                @endif
                                <span wire:loading wire:target="holeriteUpload" class="erp-rh-holerite-form-modal__hint">Enviando…</span>
                                @error('holeriteUpload') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>
                            <p class="erp-rh-holerite-form-modal__hint">Armazenamento privado — somente PDF/imagem do demonstrativo (sem cálculo de folha no ERP).</p>

                            @if (! $isEdit && $this->holeriteDuplicataAviso)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-rh-holerite-form-modal__check">
                                        <input type="checkbox" wire:model="holeriteForm.substituir_duplicata"> Substituir holerite existente nesta competência
                                    </label>
                                </div>
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-hol-motivo">Motivo substituição *</label>
                                    <input id="rh-hol-motivo" type="text" maxlength="255" wire:model="holeriteForm.substituir_motivo" class="erp-pcad-form__input erp-pcad-form__input--grow" placeholder="Obrigatório ao substituir">
                                    @error('holeriteForm.substituir_motivo') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-hol-obs">Observação</label>
                                <textarea id="rh-hol-obs" rows="2" maxlength="500" wire:model="holeriteForm.observacao" class="erp-pcad-form__textarea erp-pcad-form__input--grow"></textarea>
                            </div>

                            @if ($isEdit)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                    <label class="erp-pcad-form__label" for="rh-hol-ativo">Ativo</label>
                                    <label class="erp-rh-holerite-form-modal__check">
                                        <input id="rh-hol-ativo" type="checkbox" wire:model="holeriteForm.ativo"> Registro ativo
                                    </label>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveHolerite" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeHoleriteModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
    @include('filament.components.erp.form-scripts')
@endif
