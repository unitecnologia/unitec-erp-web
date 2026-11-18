@if ($this->documentoModalOpen)
    @php
        $funcionarios = $this->documentoFuncionarioOptions();
        $isEdit = filled($this->documentoModalRecordId);
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-documento-form-modal"
        wire:keydown.escape.window="closeDocumentoModal"
        wire:keydown.f5.window.prevent="saveDocumento"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeDocumentoModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-rh-documento-form-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-rh-documento-form-title">Documento</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeDocumentoModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Cadastro</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-doc-funcionario">Funcionário *</label>
                                <select
                                    id="rh-doc-funcionario"
                                    wire:model="documentoForm.funcionario_id"
                                    class="erp-pcad-form__select erp-pcad-form__input--grow"
                                    autofocus
                                >
                                    <option value="">Selecione…</option>
                                    @foreach ($funcionarios as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('documentoForm.funcionario_id')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-doc-titulo">Título *</label>
                                <input
                                    id="rh-doc-titulo"
                                    type="text"
                                    wire:model.blur="documentoForm.titulo"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                                    maxlength="160"
                                    data-erp-uppercase
                                >
                                @error('documentoForm.titulo')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-doc-emitido">Emitido em</label>
                                <input
                                    id="rh-doc-emitido"
                                    type="date"
                                    wire:model="documentoForm.emitido_em"
                                    class="erp-pcad-form__input erp-pcad-form__input--date"
                                >
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-doc-valido">Válido até</label>
                                <input
                                    id="rh-doc-valido"
                                    type="date"
                                    wire:model="documentoForm.valido_ate"
                                    class="erp-pcad-form__input erp-pcad-form__input--date"
                                >
                                @error('documentoForm.valido_ate')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-doc-arquivo">Arquivo {{ $isEdit ? '' : '*' }}</label>
                                <input
                                    id="rh-doc-arquivo"
                                    type="file"
                                    wire:model="documentoUpload"
                                    class="erp-pcad-form__input erp-pcad-form__input--file"
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,application/pdf,image/*"
                                >
                                @if ($isEdit && filled($this->documentoArquivoAtual))
                                    <span class="erp-rh-documento-form-modal__hint">Atual: {{ $this->documentoArquivoAtual }}</span>
                                @endif
                                <span wire:loading wire:target="documentoUpload" class="erp-rh-documento-form-modal__hint">Enviando…</span>
                                @error('documentoUpload')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-doc-obs">Observação</label>
                                <textarea
                                    id="rh-doc-obs"
                                    wire:model="documentoForm.observacao"
                                    class="erp-pcad-form__textarea erp-pcad-form__input--grow"
                                    rows="2"
                                    maxlength="500"
                                ></textarea>
                            </div>

                            @if ($isEdit)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                    <label class="erp-pcad-form__label" for="rh-doc-ativo">Ativo</label>
                                    <label class="erp-rh-documento-form-modal__check">
                                        <input id="rh-doc-ativo" type="checkbox" wire:model="documentoForm.ativo">
                                        Documento ativo
                                    </label>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveDocumento" wire:loading.attr="disabled" wire:target="saveDocumento" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="saveDocumento"><kbd>F5</kbd> | Gravar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="saveDocumento">Salvando…</span>
                </button>
                <button type="button" wire:click="closeDocumentoModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
