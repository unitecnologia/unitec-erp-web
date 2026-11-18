@if ($this->entregaModalOpen)
    @php
        $funcionarios = $this->entregaFuncionarioOptions();
        $uniformes = $this->entregaUniformeOptions();
        $titulo = match ($this->entregaModalMode) {
            'edit' => 'Alterar entrega de uniforme',
            'substituir' => 'Substituir uniforme',
            'trocar' => 'Trocar tamanho / peça',
            default => 'Nova entrega de uniforme',
        };
        $isCreate = $this->entregaModalMode === 'create';
        $isMov = in_array($this->entregaModalMode, ['substituir', 'trocar'], true);
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-uniforme-form-modal"
        wire:keydown.escape.window="closeEntregaModal"
        wire:keydown.f5.window.prevent="saveEntrega"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeEntregaModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>{{ $titulo }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeEntregaModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Entrega</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-unif-func">Funcionário *</label>
                                <select id="rh-unif-func" wire:model="entregaForm.funcionario_id" class="erp-pcad-form__select erp-pcad-form__input--grow" autofocus @if($isMov) disabled @endif>
                                    <option value="">Selecione…</option>
                                    @foreach ($funcionarios as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('entregaForm.funcionario_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            @if ($isCreate)
                                @foreach (($this->entregaForm['itens'] ?? []) as $idx => $item)
                                    <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-rh-uniforme-form-modal__item" wire:key="unif-item-{{ $idx }}">
                                        <label class="erp-pcad-form__label" for="rh-unif-item-{{ $idx }}">Peça {{ $idx + 1 }} *</label>
                                        <select id="rh-unif-item-{{ $idx }}" wire:model="entregaForm.itens.{{ $idx }}.uniforme_id" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                            <option value="">Selecione…</option>
                                            @foreach ($uniformes as $opt)
                                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-unif-qtd-{{ $idx }}">Qtd</label>
                                        <input id="rh-unif-qtd-{{ $idx }}" type="number" min="1" max="999" wire:model="entregaForm.itens.{{ $idx }}.quantidade" class="erp-pcad-form__input erp-pcad-form__input--xs">
                                        @if ($idx === 0)
                                            <button type="button" class="erp-pcad-form__btn" wire:click="openUniformeCatalogCreate" title="Novo uniforme">+</button>
                                        @endif
                                        @if (count($this->entregaForm['itens'] ?? []) > 1)
                                            <button type="button" class="erp-pcad-form__btn" wire:click="removeEntregaItemLine({{ $idx }})" title="Remover peça">✕</button>
                                        @endif
                                        @error('entregaForm.itens.'.$idx.'.uniforme_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                                    </div>
                                @endforeach
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                    <button type="button" class="erp-pcad-form__btn" wire:click="addEntregaItemLine">+ peça</button>
                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-unif-data">Entrega *</label>
                                    <input id="rh-unif-data" type="date" wire:model="entregaForm.entregue_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                                </div>
                            @else
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-unif-eq">Uniforme *</label>
                                    <select id="rh-unif-eq" wire:model="entregaForm.uniforme_id" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                        <option value="">Selecione…</option>
                                        @foreach ($uniformes as $opt)
                                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="erp-pcad-form__btn" wire:click="openUniformeCatalogCreate" title="Novo uniforme">+</button>
                                    @if (filled($this->entregaForm['uniforme_id'] ?? null))
                                        <button type="button" class="erp-pcad-form__btn" wire:click="openUniformeCatalogEdit({{ (int) $this->entregaForm['uniforme_id'] }})" title="Alterar uniforme">✎</button>
                                    @endif
                                    @error('entregaForm.uniforme_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                                </div>

                                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                    <label class="erp-pcad-form__label" for="rh-unif-qtd">Qtd *</label>
                                    <input id="rh-unif-qtd" type="number" min="1" max="999" wire:model="entregaForm.quantidade" class="erp-pcad-form__input erp-pcad-form__input--xs">
                                    <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-unif-data2">Entrega *</label>
                                    <input id="rh-unif-data2" type="date" wire:model="entregaForm.entregue_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                                </div>
                            @endif

                            @if ($isMov)
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-unif-motivo">Motivo @if($this->entregaModalMode === 'substituir')*@endif</label>
                                    <input id="rh-unif-motivo" type="text" wire:model="entregaForm.motivo_movimentacao" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255" data-erp-uppercase @if($this->entregaModalMode === 'trocar') placeholder="Opcional (troca de tamanho)" @endif>
                                    @error('entregaForm.motivo_movimentacao') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            @if ($this->entregaModalMode === 'edit')
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-unif-motivo-corr">Motivo corr.</label>
                                    <input id="rh-unif-motivo-corr" type="text" wire:model="entregaForm.motivo_correcao" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255" placeholder="Obrigatório para auditoria (recomendado)">
                                </div>
                            @endif

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-unif-obs">Observação</label>
                                <textarea id="rh-unif-obs" rows="2" maxlength="500" wire:model="entregaForm.observacao" class="erp-pcad-form__textarea erp-pcad-form__input--grow"></textarea>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveEntrega" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeEntregaModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
    @include('filament.components.erp.form-scripts')
@endif
