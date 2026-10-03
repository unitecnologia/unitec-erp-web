@if ($this->osVeiculoModalOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-veiculo-form-modal"
        wire:keydown.escape.window="closeOsVeiculoModal"
        wire:keydown.f5.window.prevent="saveOsVeiculo"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeOsVeiculoModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-os-veiculo-form-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-os-veiculo-form-title">Cadastro de Veículo</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeOsVeiculoModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-veiculo-form-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-veiculo-form-modal__form">
                    <section class="erp-veiculo-form__section">
                        <h3 class="erp-veiculo-form__section-title">Dados do veículo</h3>
                        @if ($errors->any())
                            <span class="erp-contador-form-modal__error">{{ $errors->first() }}</span>
                        @endif
                        <div class="erp-veiculo-form__section-grid">
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--duo">
                                <label class="erp-pcad-form__label" for="os-veiculo-placa">Placa</label>
                                <input id="os-veiculo-placa" type="text" wire:model="osVeiculoForm.placa" class="erp-pcad-form__input" data-erp-uppercase maxlength="10" autofocus>
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="os-veiculo-placa-alt">Placa alt.</label>
                                <input id="os-veiculo-placa-alt" type="text" wire:model="osVeiculoForm.placa_alternativa" class="erp-pcad-form__input" data-erp-uppercase maxlength="10">
                            </div>
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--single">
                                <label class="erp-pcad-form__label" for="os-veiculo-descricao">Descrição</label>
                                <input id="os-veiculo-descricao" type="text" wire:model="osVeiculoForm.descricao" class="erp-pcad-form__input erp-pcad-form__input--grow" data-erp-uppercase maxlength="160">
                            </div>
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--duo">
                                <label class="erp-pcad-form__label" for="os-veiculo-marca">Marca</label>
                                <input id="os-veiculo-marca" type="text" wire:model="osVeiculoForm.marca" class="erp-pcad-form__input" data-erp-uppercase maxlength="80">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="os-veiculo-modelo">Modelo</label>
                                <input id="os-veiculo-modelo" type="text" wire:model="osVeiculoForm.modelo" class="erp-pcad-form__input" data-erp-uppercase maxlength="80">
                            </div>
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--duo">
                                <label class="erp-pcad-form__label" for="os-veiculo-submodelo">Submodelo</label>
                                <input id="os-veiculo-submodelo" type="text" wire:model="osVeiculoForm.submodelo" class="erp-pcad-form__input" data-erp-uppercase maxlength="80">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="os-veiculo-versao">Versão</label>
                                <input id="os-veiculo-versao" type="text" wire:model="osVeiculoForm.versao" class="erp-pcad-form__input" data-erp-uppercase maxlength="80">
                            </div>
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--duo">
                                <label class="erp-pcad-form__label" for="os-veiculo-ano-fab">Ano fab.</label>
                                <input id="os-veiculo-ano-fab" type="text" wire:model="osVeiculoForm.ano_fabricacao" class="erp-pcad-form__input" maxlength="4" inputmode="numeric">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="os-veiculo-ano-mod">Ano modelo</label>
                                <input id="os-veiculo-ano-mod" type="text" wire:model="osVeiculoForm.ano_modelo" class="erp-pcad-form__input" maxlength="4" inputmode="numeric">
                            </div>
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--duo">
                                <label class="erp-pcad-form__label" for="os-veiculo-cor">Cor</label>
                                <input id="os-veiculo-cor" type="text" wire:model="osVeiculoForm.cor" class="erp-pcad-form__input" data-erp-uppercase maxlength="40">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="os-veiculo-combustivel">Combustível</label>
                                <input id="os-veiculo-combustivel" type="text" wire:model="osVeiculoForm.combustivel" class="erp-pcad-form__input" data-erp-uppercase maxlength="40">
                            </div>
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--duo">
                                <label class="erp-pcad-form__label" for="os-veiculo-cidade">Cidade</label>
                                <input id="os-veiculo-cidade" type="text" wire:model="osVeiculoForm.cidade" class="erp-pcad-form__input" data-erp-uppercase maxlength="80">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="os-veiculo-uf">UF</label>
                                <input id="os-veiculo-uf" type="text" wire:model="osVeiculoForm.uf" class="erp-pcad-form__input" data-erp-uppercase maxlength="2">
                            </div>
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--duo">
                                <label class="erp-pcad-form__label" for="os-veiculo-renavam">RENAVAM</label>
                                <input id="os-veiculo-renavam" type="text" wire:model="osVeiculoForm.renavam" class="erp-pcad-form__input" data-erp-uppercase maxlength="20">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="os-veiculo-chassi">Chassi</label>
                                <input id="os-veiculo-chassi" type="text" wire:model="osVeiculoForm.chassi" class="erp-pcad-form__input" data-erp-uppercase maxlength="30">
                            </div>
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--duo">
                                <label class="erp-pcad-form__label" for="os-veiculo-tipo">Tipo</label>
                                <input id="os-veiculo-tipo" type="text" wire:model="osVeiculoForm.tipo" class="erp-pcad-form__input" data-erp-uppercase maxlength="60">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="os-veiculo-especie">Espécie</label>
                                <input id="os-veiculo-especie" type="text" wire:model="osVeiculoForm.especie" class="erp-pcad-form__input" data-erp-uppercase maxlength="60">
                            </div>
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--duo">
                                <label class="erp-pcad-form__label" for="os-veiculo-carroceria">Carroceria</label>
                                <input id="os-veiculo-carroceria" type="text" wire:model="osVeiculoForm.carroceria" class="erp-pcad-form__input" data-erp-uppercase maxlength="60">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="os-veiculo-origem">Origem</label>
                                <input id="os-veiculo-origem" type="text" wire:model="osVeiculoForm.origem" class="erp-pcad-form__input" data-erp-uppercase maxlength="60">
                            </div>
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--duo">
                                <label class="erp-pcad-form__label" for="os-veiculo-nacionalidade">Nacionalidade</label>
                                <input id="os-veiculo-nacionalidade" type="text" wire:model="osVeiculoForm.nacionalidade" class="erp-pcad-form__input" data-erp-uppercase maxlength="60">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="os-veiculo-segmento">Segmento</label>
                                <input id="os-veiculo-segmento" type="text" wire:model="osVeiculoForm.segmento" class="erp-pcad-form__input" data-erp-uppercase maxlength="60">
                            </div>
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--single">
                                <label class="erp-pcad-form__label" for="os-veiculo-subsegmento">Subsegmento</label>
                                <input id="os-veiculo-subsegmento" type="text" wire:model="osVeiculoForm.subsegmento" class="erp-pcad-form__input erp-pcad-form__input--grow" data-erp-uppercase maxlength="60">
                            </div>
                            @if (filled($this->osVeiculoForm['consultado_em'] ?? ''))
                                <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--single">
                                    <label class="erp-pcad-form__label" for="os-veiculo-consultado">Última consulta</label>
                                    <input id="os-veiculo-consultado" type="text" value="{{ $this->osVeiculoForm['consultado_em'] }}" class="erp-pcad-form__input" readonly tabindex="-1">
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-veiculo-form-modal__actions">
                <button type="button" wire:click="saveOsVeiculo" wire:loading.attr="disabled" wire:target="saveOsVeiculo" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="saveOsVeiculo"><kbd>F5</kbd> | Gravar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="saveOsVeiculo">Salvando…</span>
                </button>
                <button type="button" wire:click="closeOsVeiculoModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
