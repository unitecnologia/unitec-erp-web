@if ($this->veiculoModalOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-veiculo-form-modal"
        wire:keydown.escape.window="closeVeiculoModal"
        wire:keydown.f5.window.prevent="saveVeiculo"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeVeiculoModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-veiculo-form-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-veiculo-form-title">Cadastro de Veículo</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeVeiculoModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-veiculo-form-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-veiculo-form-modal__form">
                    <section class="erp-veiculo-form__section">
                        <h3 class="erp-veiculo-form__section-title">Dados do veículo</h3>
                        <div class="erp-veiculo-form__section-grid">
                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--duo">
                                <label class="erp-pcad-form__label" for="veiculo-placa">Placa</label>
                                <input
                                    id="veiculo-placa"
                                    type="text"
                                    wire:model="veiculoForm.placa"
                                    class="erp-pcad-form__input erp-pcad-form__input--md"
                                    data-erp-uppercase
                                    maxlength="10"
                                    autofocus
                                >
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="veiculo-renavam">Renavam</label>
                                <input
                                    id="veiculo-renavam"
                                    type="text"
                                    wire:model="veiculoForm.renavam"
                                    class="erp-pcad-form__input erp-pcad-form__input--md"
                                    maxlength="20"
                                >
                                @error('veiculoForm.placa')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--single">
                                <label class="erp-pcad-form__label" for="veiculo-descricao">Descrição</label>
                                <input
                                    id="veiculo-descricao"
                                    type="text"
                                    wire:model="veiculoForm.descricao"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                                    data-erp-uppercase
                                    maxlength="120"
                                >
                                @error('veiculoForm.descricao')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-veiculo-form__row">
                                <label class="erp-pcad-form__label" for="veiculo-rntc">RNTC</label>
                                <input
                                    id="veiculo-rntc"
                                    type="text"
                                    wire:model="veiculoForm.rntc"
                                    class="erp-pcad-form__input erp-pcad-form__input--md"
                                    data-erp-uppercase
                                    maxlength="20"
                                >
                                @error('veiculoForm.rntc')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-veiculo-form__row erp-veiculo-form__row--check">
                                <span class="erp-pcad-form__label" aria-hidden="true"></span>
                                <label class="erp-pcad-form__check" for="veiculo-ativo">
                                    <input id="veiculo-ativo" type="checkbox" wire:model="veiculoForm.ativo"> Ativo
                                </label>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-veiculo-form-modal__actions">
                <button type="button" wire:click="saveVeiculo" wire:loading.attr="disabled" wire:target="saveVeiculo" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="saveVeiculo"><kbd>F5</kbd> | Gravar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="saveVeiculo">Salvando…</span>
                </button>
                <button type="button" wire:click="closeVeiculoModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
