@if ($this->contadorModalOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal"
        wire:keydown.escape.window="closeContadorModal"
        wire:keydown.f5.window.prevent="saveContador"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeContadorModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-contador-form-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-contador-form-title">Contadores</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeContadorModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form">
                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-codigo">Código</label>
                        <input
                            id="contador-codigo"
                            type="text"
                            wire:model="contadorForm.codigo"
                            class="erp-pcad-form__input erp-pcad-form__input--xs"
                            @if ($this->contadorModalRecordId) readonly @endif
                        >
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-nome">Nome</label>
                        <input
                            id="contador-nome"
                            type="text"
                            wire:model="contadorForm.nome"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                            autofocus
                        >
                        @error('contadorForm.nome')
                            <span class="erp-contador-form-modal__error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-cnpj">CNPJ/CPF</label>
                        <input
                            id="contador-cnpj"
                            type="text"
                            wire:model="contadorForm.cnpj_cpf"
                            data-mask="cpf-cnpj"
                            class="erp-pcad-form__input erp-pcad-form__input--doc"
                        >
                        @error('contadorForm.cnpj_cpf')
                            <span class="erp-contador-form-modal__error">{{ $message }}</span>
                        @enderror
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="contador-crc">CRC</label>
                        <input
                            id="contador-crc"
                            type="text"
                            wire:model="contadorForm.crc"
                            class="erp-pcad-form__input erp-pcad-form__input--md"
                        >
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-cep">CEP</label>
                        <input
                            id="contador-cep"
                            type="text"
                            wire:model="contadorForm.cep"
                            data-mask="cep"
                            class="erp-pcad-form__input erp-pcad-form__input--cep"
                        >
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-endereco">Endereço</label>
                        <input
                            id="contador-endereco"
                            type="text"
                            wire:model="contadorForm.endereco"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                        >
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="contador-numero">Número</label>
                        <input
                            id="contador-numero"
                            type="text"
                            wire:model="contadorForm.numero"
                            class="erp-pcad-form__input erp-pcad-form__input--xs"
                        >
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-bairro">Bairro</label>
                        <input
                            id="contador-bairro"
                            type="text"
                            wire:model="contadorForm.bairro"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                        >
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-cidade">Cidade</label>
                        <input
                            id="contador-cidade"
                            type="text"
                            wire:model="contadorForm.cidade"
                            class="erp-pcad-form__input erp-pcad-form__input--city"
                            list="contador-cidades"
                        >
                        <datalist id="contador-cidades">
                            <option value="BALNEÁRIO CAMBORIÚ">
                            <option value="CAMBORIÚ">
                            <option value="FLORIANÓPOLIS">
                            <option value="ITAJAÍ">
                        </datalist>
                        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="contador-uf">UF</label>
                        <select id="contador-uf" wire:model="contadorForm.uf" class="erp-pcad-form__select erp-pcad-form__select--uf">
                            @foreach ($this->contadorUfOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-email">Email</label>
                        <input
                            id="contador-email"
                            type="email"
                            wire:model="contadorForm.email"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                        >
                    </div>

                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="contador-fone">Fone</label>
                        <input
                            id="contador-fone"
                            type="text"
                            wire:model="contadorForm.fone"
                            data-mask="phone"
                            class="erp-pcad-form__input erp-pcad-form__input--phone"
                        >
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions">
                <button type="button" wire:click="saveContador" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeContadorModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
