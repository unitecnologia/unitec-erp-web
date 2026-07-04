<div class="erp-orc-header">
    <div class="erp-pcad-form erp-orc-form">
        {{-- Linha 1: Número | Razão Social (amplo) | CPF/CNPJ --}}
        <div class="erp-pcad-form__row erp-orc-form__row--cliente">
            <label class="erp-pcad-form__label" for="orc-numero">Número</label>
            <input
                id="orc-numero"
                type="text"
                readonly
                value="{{ $this->orcamentoNumeroDisplay() }}"
                class="erp-pcad-form__input erp-pcad-form__input--xs erp-pcad-form__input--readonly"
            >

            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="orc-cliente">Razão Social ou CNPJ</label>
            <div class="erp-orc-cliente-field erp-orc-cliente-field--wide">
                <input
                    id="orc-cliente"
                    type="text"
                    wire:model.live.debounce.250ms="clienteSearch"
                    wire:focus="openClienteLookup"
                    wire:keydown.arrow-up.prevent="moveClienteSelection(-1)"
                    wire:keydown.arrow-down.prevent="moveClienteSelection(1)"
                    wire:keydown.enter.prevent="handleClienteEnter"
                    wire:keydown.escape.prevent="closeClienteLookup"
                    wire:blur="confirmClienteSelectionOnBlur"
                    @disabled($readOnly)
                    class="erp-pcad-form__input erp-pcad-form__input--cliente-search"
                    autocomplete="off"
                    placeholder="Digite razão social, fantasia ou CNPJ"
                >
                @if ($this->clienteLookupOpen && filled($this->clienteSearch))
                    @if ($this->clienteResults !== [])
                        @include('filament.components.erp.orcamentos.form.cliente-lookup')
                    @else
                        <div class="erp-orc-cliente-lookup erp-orc-cliente-lookup--empty">
                            Nenhum cliente encontrado.
                        </div>
                    @endif
                @endif
            </div>

            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="orc-cpf">CPF/CNPJ</label>
            <input
                id="orc-cpf"
                type="text"
                readonly
                wire:model="clienteCpfCnpj"
                class="erp-pcad-form__input erp-pcad-form__input--doc erp-pcad-form__input--readonly"
            >
        </div>

        {{-- Linha 2: Endereço | Número | Bairro --}}
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="orc-endereco">Endereço</label>
            <input
                id="orc-endereco"
                type="text"
                readonly
                wire:model="clienteEndereco"
                class="erp-pcad-form__input erp-pcad-form__input--grow erp-pcad-form__input--readonly"
            >

            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="orc-numero-end">Número</label>
            <input
                id="orc-numero-end"
                type="text"
                readonly
                wire:model="clienteNumero"
                class="erp-pcad-form__input erp-pcad-form__input--xs erp-pcad-form__input--readonly"
            >

            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="orc-bairro">Bairro</label>
            <input
                id="orc-bairro"
                type="text"
                readonly
                wire:model="clienteBairro"
                class="erp-pcad-form__input erp-pcad-form__input--sm erp-pcad-form__input--readonly"
            >
        </div>

        {{-- Linha 3: CEP | Cidade | UF | Fone | WhatsApp | Vendedor --}}
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="orc-cep">CEP</label>
            <input
                id="orc-cep"
                type="text"
                readonly
                wire:model="clienteCep"
                data-mask="cep"
                class="erp-pcad-form__input erp-pcad-form__input--cep erp-pcad-form__input--readonly"
            >

            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="orc-cidade">Cidade</label>
            <input
                id="orc-cidade"
                type="text"
                readonly
                wire:model="clienteCidade"
                class="erp-pcad-form__input erp-pcad-form__input--city erp-pcad-form__input--readonly"
            >

            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="orc-uf">UF</label>
            <select id="orc-uf" disabled wire:model="clienteUf" class="erp-pcad-form__select erp-pcad-form__select--uf">
                @foreach ($ufs as $uf)
                    <option value="{{ $uf }}">{{ $uf }}</option>
                @endforeach
            </select>

            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="orc-fone">Fone Fixo</label>
            <input
                id="orc-fone"
                type="text"
                readonly
                wire:model="clienteFone"
                data-mask="phone"
                class="erp-pcad-form__input erp-pcad-form__input--phone erp-pcad-form__input--readonly"
            >

            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="orc-whatsapp">WhatsApp</label>
            <input
                id="orc-whatsapp"
                type="text"
                readonly
                wire:model="clienteWhatsapp"
                data-mask="phone"
                class="erp-pcad-form__input erp-pcad-form__input--phone erp-pcad-form__input--readonly"
            >

            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="orc-vendedor">Vendedor</label>
            <select id="orc-vendedor" wire:model="vendedorId" @disabled($readOnly) class="erp-pcad-form__select erp-pcad-form__select--vendedor">
                <option value="">Selecione...</option>
                @foreach ($this->vendedorOptions() as $vendedor)
                    <option value="{{ $vendedor['id'] }}">{{ $vendedor['nome'] }}</option>
                @endforeach
            </select>
        </div>

        {{-- Linha 4: Forma de Pagamento | Validade --}}
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="orc-forma">Forma de Pagamento</label>
            <input
                id="orc-forma"
                type="text"
                wire:model="formaPagamento"
                @disabled($readOnly)
                class="erp-pcad-form__input erp-pcad-form__input--grow"
            >

            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="orc-validade">Validade</label>
            <input
                id="orc-validade"
                type="number"
                min="0"
                wire:model="validadeDias"
                @disabled($readOnly)
                class="erp-pcad-form__input erp-pcad-form__input--xs"
            >
            <span class="erp-orc-header__suffix">dias</span>
        </div>
    </div>
</div>
