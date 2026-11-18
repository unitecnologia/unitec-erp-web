@if ($this->realizacaoModalOpen)
    @php
        $funcionarios = $this->realizacaoFuncionarioOptions();
        $treinamentos = $this->realizacaoTreinamentoOptions();
        $isEdit = $this->realizacaoModalMode === 'edit';
        $titulo = $isEdit ? 'Alterar participante' : 'Nova realização de treinamento';
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-treinamento-form-modal"
        wire:keydown.escape.window="closeRealizacaoModal"
        wire:keydown.f5.window.prevent="saveRealizacao"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeRealizacaoModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $titulo }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeRealizacaoModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">{{ $isEdit ? 'Participante' : 'Realização' }}</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            @if ($isEdit)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label">Treinamento</label>
                                    <input type="text" class="erp-pcad-form__input erp-pcad-form__input--grow" value="{{ $this->realizacaoForm['treinamento_nome'] ?? '' }}" readonly>
                                </div>
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-trein-func">Funcionário *</label>
                                    <select id="rh-trein-func" wire:model="realizacaoForm.funcionario_id" class="erp-pcad-form__select erp-pcad-form__input--grow" autofocus>
                                        <option value="">Selecione…</option>
                                        @foreach ($funcionarios as $opt)
                                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('realizacaoForm.funcionario_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                                </div>
                            @else
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-trein-cat">Treinamento *</label>
                                    <select id="rh-trein-cat" wire:model="realizacaoForm.treinamento_id" class="erp-pcad-form__select erp-pcad-form__input--grow" autofocus>
                                        <option value="">Selecione…</option>
                                        @foreach ($treinamentos as $opt)
                                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="erp-pcad-form__btn" wire:click="openTreinamentoCatalogCreate" title="Novo treinamento">+</button>
                                    @if (filled($this->realizacaoForm['treinamento_id'] ?? null))
                                        <button type="button" class="erp-pcad-form__btn" wire:click="openTreinamentoCatalogEdit({{ (int) $this->realizacaoForm['treinamento_id'] }})" title="Alterar treinamento">✎</button>
                                    @endif
                                    @error('realizacaoForm.treinamento_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                                </div>

                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-trein-funcs">Funcionários *</label>
                                    <select
                                        id="rh-trein-funcs"
                                        wire:model="realizacaoForm.funcionario_ids"
                                        class="erp-pcad-form__select erp-pcad-form__input--grow erp-rh-treinamento-form-modal__multi"
                                        multiple
                                        size="6"
                                    >
                                        @foreach ($funcionarios as $opt)
                                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('realizacaoForm.funcionario_ids') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                                    @error('realizacaoForm.funcionario_ids.*') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                                </div>

                                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                    <label class="erp-pcad-form__label" for="rh-trein-data">Data *</label>
                                    <input id="rh-trein-data" type="date" wire:model="realizacaoForm.realizado_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-trein-venc">Vencimento</label>
                                    <input id="rh-trein-venc" type="date" wire:model="realizacaoForm.vencimento_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                                </div>
                            @endif

                            @if ($isEdit)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                    <label class="erp-pcad-form__label" for="rh-trein-venc-edit">Vencimento</label>
                                    <input id="rh-trein-venc-edit" type="date" wire:model="realizacaoForm.vencimento_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                                </div>
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-trein-motivo-corr">Motivo corr.</label>
                                    <input id="rh-trein-motivo-corr" type="text" wire:model="realizacaoForm.motivo_correcao" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255" placeholder="Obrigatório para auditoria (recomendado)">
                                </div>
                            @endif

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-trein-obs">Observação</label>
                                <textarea id="rh-trein-obs" rows="2" maxlength="{{ $isEdit ? 255 : 500 }}" wire:model="realizacaoForm.observacao" class="erp-pcad-form__textarea erp-pcad-form__input--grow"></textarea>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveRealizacao" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeRealizacaoModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
    @include('filament.components.erp.form-scripts')
@endif
