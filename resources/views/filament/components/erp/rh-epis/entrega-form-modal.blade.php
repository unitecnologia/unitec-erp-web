@if ($this->entregaModalOpen)
    @php
        $funcionarios = $this->entregaFuncionarioOptions();
        $epis = $this->entregaEpiOptions();
        $titulo = match ($this->entregaModalMode) {
            'edit' => 'Alterar entrega de EPI',
            'substituir' => 'Substituir EPI',
            default => 'Nova entrega de EPI',
        };
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-rh-epi-form-modal"
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
                                <label class="erp-pcad-form__label" for="rh-epi-func">Funcionário *</label>
                                <select id="rh-epi-func" wire:model="entregaForm.funcionario_id" class="erp-pcad-form__select erp-pcad-form__input--grow" autofocus @if($this->entregaModalMode === 'substituir') disabled @endif>
                                    <option value="">Selecione…</option>
                                    @foreach ($funcionarios as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('entregaForm.funcionario_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-epi-eq">EPI *</label>
                                <select id="rh-epi-eq" wire:model="entregaForm.epi_id" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                    <option value="">Selecione…</option>
                                    @foreach ($epis as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="erp-pcad-form__btn" wire:click="openEpiCatalogCreate" title="Novo equipamento">+</button>
                                @if (filled($this->entregaForm['epi_id'] ?? null))
                                    <button type="button" class="erp-pcad-form__btn" wire:click="openEpiCatalogEdit({{ (int) $this->entregaForm['epi_id'] }})" title="Alterar equipamento">✎</button>
                                @endif
                                @error('entregaForm.epi_id') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="rh-epi-qtd">Qtd *</label>
                                <input id="rh-epi-qtd" type="number" min="1" max="999" wire:model="entregaForm.quantidade" class="erp-pcad-form__input erp-pcad-form__input--xs">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-epi-data">Entrega *</label>
                                <input id="rh-epi-data" type="date" wire:model="entregaForm.entregue_em" class="erp-pcad-form__input erp-pcad-form__input--date">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="rh-epi-troca">Próx. troca</label>
                                <input id="rh-epi-troca" type="date" wire:model="entregaForm.previsao_troca" class="erp-pcad-form__input erp-pcad-form__input--date">
                            </div>

                            @if ($this->entregaModalMode === 'substituir')
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-epi-motivo-sub">Motivo *</label>
                                    <input id="rh-epi-motivo-sub" type="text" wire:model="entregaForm.motivo_substituicao" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255" data-erp-uppercase>
                                    @error('entregaForm.motivo_substituicao') <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            @if ($this->entregaModalMode === 'edit')
                                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                    <label class="erp-pcad-form__label" for="rh-epi-motivo-corr">Motivo corr.</label>
                                    <input id="rh-epi-motivo-corr" type="text" wire:model="entregaForm.motivo_correcao" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255" placeholder="Obrigatório para auditoria (recomendado)">
                                </div>
                            @endif

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="rh-epi-obs">Observação</label>
                                <textarea id="rh-epi-obs" rows="2" maxlength="500" wire:model="entregaForm.observacao" class="erp-pcad-form__textarea erp-pcad-form__input--grow"></textarea>
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
