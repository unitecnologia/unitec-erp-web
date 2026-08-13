<section class="erp-nfe-cliente" @if ($this->nfeClienteSugestoesOpen && $this->nfeClienteSugestoes !== []) data-lookup-open="1" @endif>
    <div class="erp-nfe-cliente__box">
        <span class="erp-nfe-cliente__legend">Cliente</span>

        <div class="erp-nfe-cliente__row erp-nfe-cliente__row--primary">
            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--nota">
                <span>Nº Nota</span>
                <input
                    class="erp-nfe-cliente__input erp-nfe-cliente__input--info"
                    type="text"
                    value="{{ $this->nfeForm['numero'] ?? '' }}"
                    readonly
                    tabindex="-1"
                    aria-readonly="true"
                >
            </label>

            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--empresa">
                <span>Empresa</span>
                <input
                    class="erp-nfe-cliente__input erp-nfe-cliente__input--info"
                    type="text"
                    value="{{ $this->nfeForm['empresa'] ?? '—' }}"
                    readonly
                    tabindex="-1"
                    aria-readonly="true"
                >
            </label>

            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--grow erp-nfe-cliente__field--suggest">
                <span>Razão social ou CNPJ</span>
                <input
                    id="nfe-cliente-busca"
                    class="erp-nfe-cliente__input erp-nfe-cliente__input--editable"
                    type="text"
                    wire:model.live.debounce.250ms="nfeClienteBusca"
                    wire:keydown.enter.prevent="confirmarNfeClienteBusca"
                    wire:keydown.escape.prevent="fecharNfeSugestoesCliente"
                    wire:keydown.arrow-up.prevent="moverNfeSugestaoCliente(-1)"
                    wire:keydown.arrow-down.prevent="moverNfeSugestaoCliente(1)"
                    autocomplete="off"
                    placeholder="Código, nome ou CNPJ — Enter"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded="{{ $this->nfeClienteSugestoesOpen && $this->nfeClienteSugestoes !== [] ? 'true' : 'false' }}"
                    aria-controls="nfe-cliente-sugestoes"
                >
                @if ($this->nfeClienteSugestoesOpen && $this->nfeClienteSugestoes !== [])
                    <ul id="nfe-cliente-sugestoes" class="erp-nfe-cliente__suggest" role="listbox" aria-label="Clientes encontrados">
                        @foreach ($this->nfeClienteSugestoes as $index => $sug)
                            <li wire:key="nfe-cli-sug-{{ $sug['id'] }}" role="presentation">
                                <button
                                    type="button"
                                    id="nfe-cliente-sug-{{ $index }}"
                                    role="option"
                                    aria-selected="{{ (int) $this->nfeSelectedClienteSugestaoIndex === (int) $index ? 'true' : 'false' }}"
                                    wire:click="selecionarNfeCliente({{ $sug['id'] }})"
                                    @class(['is-selected' => (int) $this->nfeSelectedClienteSugestaoIndex === (int) $index])
                                >
                                    <span class="erp-nfe-cliente__suggest-code">{{ $sug['codigo'] ?: '—' }}</span>
                                    <span class="erp-nfe-cliente__suggest-nome">{{ $sug['nome'] }}</span>
                                    @if (filled($sug['cpf_cnpj'] ?? null))
                                        <span @class([
                                            'erp-nfe-cliente__suggest-doc',
                                            'is-cnpj' => ($sug['doc_tipo'] ?? '') === 'cnpj',
                                            'is-cpf' => ($sug['doc_tipo'] ?? '') === 'cpf',
                                        ])>{{ $sug['cpf_cnpj'] }}</span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </label>

            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--doc">
                <span>CPF/CNPJ</span>
                <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfeForm['cnpj'] ?? '' }}" readonly tabindex="-1" aria-readonly="true">
            </label>

            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--fone">
                <span>Fone</span>
                <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfeClienteFone }}" readonly tabindex="-1" aria-readonly="true">
            </label>

            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--fone">
                <span>WhatsApp</span>
                <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfeClienteWhatsapp }}" readonly tabindex="-1" aria-readonly="true">
            </label>
        </div>

        <div class="erp-nfe-cliente__row erp-nfe-cliente__row--secondary">
            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--end">
                <span>Endereço</span>
                <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfeClienteEndereco }}" readonly tabindex="-1" aria-readonly="true">
            </label>

            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--num">
                <span>Nº</span>
                <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfeClienteNumeroEnd }}" readonly tabindex="-1" aria-readonly="true">
            </label>

            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--bairro">
                <span>Bairro</span>
                <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfeClienteBairro }}" readonly tabindex="-1" aria-readonly="true">
            </label>

            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--cep">
                <span>CEP</span>
                <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfeClienteCep }}" readonly tabindex="-1" aria-readonly="true">
            </label>

            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--cidade">
                <span>Cidade</span>
                <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfeClienteCidade }}" readonly tabindex="-1" aria-readonly="true">
            </label>

            <label class="erp-nfe-cliente__field erp-nfe-cliente__field--uf">
                <span>UF</span>
                <input class="erp-nfe-cliente__input erp-nfe-cliente__input--info" type="text" value="{{ $this->nfeForm['uf'] ?? '' }}" readonly tabindex="-1" aria-readonly="true">
            </label>
        </div>
    </div>
</section>
