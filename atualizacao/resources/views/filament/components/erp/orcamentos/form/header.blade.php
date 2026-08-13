@php
    $readOnly = $readOnly ?? $this->orcamentoReadOnly();
    $avulso = $this->clienteCamposEditaveis();
    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
@endphp

<section class="erp-fv-tv__panel erp-orc-header-panel">
    <div class="erp-fv-tv__box">
        <span class="erp-fv-tv__box-legend">Cliente</span>

        <div class="erp-fv-tv__row erp-fv-tv__row--primary">
            <label class="erp-fv-tv__field erp-fv-tv__field--cod">
                <span>Número</span>
                <input
                    id="orc-numero"
                    class="erp-nfe__input"
                    type="text"
                    readonly
                    value="{{ $this->orcamentoNumeroDisplay() }}"
                    tabindex="-1"
                >
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--grow erp-fv-tv__field--suggest">
                <span>{{ $this->clienteAvulsoMode ? 'Nome no orçamento' : 'Razão social ou CNPJ' }}</span>
                <div class="erp-orc-cliente-field">
                    <input
                        id="orc-cliente"
                        class="erp-nfe__input"
                        type="text"
                        wire:model.live.debounce.250ms="clienteSearch"
                        wire:focus="openClienteLookup"
                        wire:keydown.arrow-up.prevent="moveClienteSelection(-1)"
                        wire:keydown.arrow-down.prevent="moveClienteSelection(1)"
                        wire:keydown.enter.prevent="handleClienteEnter"
                        wire:keydown.escape.prevent="closeClienteLookup"
                        wire:blur="confirmClienteSelectionOnBlur"
                        @disabled($readOnly)
                        @if (! $this->isEditingOrcamento() && blank($this->clienteSearch)) autofocus @endif
                        autocomplete="off"
                        placeholder="{{ $this->clienteAvulsoMode ? 'Nome do cliente neste orçamento' : 'Cliente cadastrado ou nome novo + Enter' }}"
                    >
                    @if ($this->clienteLookupOpen && $this->clienteResults !== [])
                        @include('filament.components.erp.orcamentos.form.cliente-lookup')
                    @elseif ($this->clienteLookupOpen && filled($this->clienteSearch) && ! $this->clienteAvulsoMode)
                        <div class="erp-orc-cliente-lookup erp-orc-cliente-lookup--empty">
                            Nenhum cliente encontrado.
                        </div>
                    @endif
                </div>
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--doc">
                <span>CPF/CNPJ</span>
                <input
                    id="orc-cpf"
                    class="erp-nfe__input"
                    type="text"
                    wire:model="clienteCpfCnpj"
                    wire:keydown.enter.prevent="focusNextClienteAvulsoField('orc-cpf')"
                    @readonly(! $avulso)
                    @disabled($readOnly)
                    tabindex="{{ $avulso ? 0 : -1 }}"
                >
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--fone">
                <span>Fone</span>
                <input
                    id="orc-fone"
                    class="erp-nfe__input"
                    type="text"
                    wire:model="clienteFone"
                    wire:keydown.enter.prevent="focusNextClienteAvulsoField('orc-fone')"
                    data-mask="phone"
                    @readonly(! $avulso)
                    @disabled($readOnly)
                    tabindex="{{ $avulso ? 0 : -1 }}"
                >
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--fone">
                <span>WhatsApp</span>
                <input
                    id="orc-whatsapp"
                    class="erp-nfe__input"
                    type="text"
                    wire:model="clienteWhatsapp"
                    wire:keydown.enter.prevent="focusNextClienteAvulsoField('orc-whatsapp')"
                    data-mask="phone"
                    @readonly(! $avulso)
                    @disabled($readOnly)
                    tabindex="{{ $avulso ? 0 : -1 }}"
                >
            </label>
        </div>

        <div class="erp-fv-tv__row erp-fv-tv__row--secondary">
            <label class="erp-fv-tv__field erp-fv-tv__field--end">
                <span>Endereço</span>
                <input
                    id="orc-endereco"
                    class="erp-nfe__input"
                    type="text"
                    wire:model="clienteEndereco"
                    wire:keydown.enter.prevent="focusNextClienteAvulsoField('orc-endereco')"
                    @readonly(! $avulso)
                    @disabled($readOnly)
                    tabindex="{{ $avulso ? 0 : -1 }}"
                >
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--num">
                <span>Nº</span>
                <input
                    id="orc-numero-end"
                    class="erp-nfe__input"
                    type="text"
                    wire:model="clienteNumero"
                    wire:keydown.enter.prevent="focusNextClienteAvulsoField('orc-numero-end')"
                    @readonly(! $avulso)
                    @disabled($readOnly)
                    tabindex="{{ $avulso ? 0 : -1 }}"
                >
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--bairro">
                <span>Bairro</span>
                <input
                    id="orc-bairro"
                    class="erp-nfe__input"
                    type="text"
                    wire:model="clienteBairro"
                    wire:keydown.enter.prevent="focusNextClienteAvulsoField('orc-bairro')"
                    @readonly(! $avulso)
                    @disabled($readOnly)
                    tabindex="{{ $avulso ? 0 : -1 }}"
                >
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--cep">
                <span>CEP</span>
                <div class="erp-orc-cep-wrap">
                    <input
                        id="orc-cep"
                        class="erp-nfe__input"
                        type="text"
                        wire:model="clienteCep"
                        wire:keydown.enter.prevent="handleCepEnter"
                        wire:blur="buscarCepOrcamento(true)"
                        data-mask="cep"
                        @readonly(! $avulso)
                        @disabled($readOnly)
                        tabindex="{{ $avulso ? 0 : -1 }}"
                    >
                    @if ($avulso)
                        <button
                            type="button"
                            class="erp-orc-cep-lupa"
                            wire:click="buscarCepOrcamento"
                            wire:loading.attr="disabled"
                            wire:target="buscarCepOrcamento,handleCepEnter"
                            title="Pesquisar CEP"
                            aria-label="Pesquisar CEP"
                        >
                            <span aria-hidden="true">🔍</span>
                        </button>
                    @endif
                </div>
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--cidade">
                <span>Cidade</span>
                @if ($avulso)
                    <div
                        class="erp-orc-cidade-wrap"
                        @if ($this->orcCidadeSugestoesOpen && $this->orcCidadeSugestoes !== []) data-lookup-open="1" @endif
                    >
                        <input
                            id="orc-cidade"
                            class="erp-nfe__input"
                            type="text"
                            wire:model.live.debounce.250ms="clienteCidade"
                            wire:keydown.enter.prevent="handleCidadeEnter"
                            wire:keydown.escape.prevent="fecharOrcCidadeSugestoes"
                            wire:keydown.arrow-up.prevent="moverOrcCidadeSugestao(-1)"
                            wire:keydown.arrow-down.prevent="moverOrcCidadeSugestao(1)"
                            autocomplete="off"
                            placeholder="Digite a cidade"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-expanded="{{ $this->orcCidadeSugestoesOpen && $this->orcCidadeSugestoes !== [] ? 'true' : 'false' }}"
                            aria-controls="orc-cidade-sugestoes"
                        >
                        @if ($this->orcCidadeSugestoesOpen && $this->orcCidadeSugestoes !== [])
                            <ul id="orc-cidade-sugestoes" class="erp-orc-cidade-suggest" role="listbox" aria-label="Cidades encontradas">
                                @foreach ($this->orcCidadeSugestoes as $index => $sug)
                                    <li wire:key="orc-cid-sug-{{ $sug['codigo'] }}-{{ $index }}" role="presentation">
                                        <button
                                            type="button"
                                            role="option"
                                            aria-selected="{{ (int) $this->orcCidadeSugestaoIndex === (int) $index ? 'true' : 'false' }}"
                                            wire:mousedown.prevent="selecionarOrcCidade('{{ $sug['codigo'] }}', @js($sug['nome']), '{{ $sug['uf'] }}')"
                                            @class(['is-selected' => (int) $this->orcCidadeSugestaoIndex === (int) $index])
                                        >
                                            <span class="erp-orc-cidade-suggest__code">{{ $sug['codigo'] }}</span>
                                            <span class="erp-orc-cidade-suggest__nome">{{ $sug['nome'] }}</span>
                                            <span class="erp-orc-cidade-suggest__uf">{{ $sug['uf'] }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @else
                    <input
                        id="orc-cidade"
                        class="erp-nfe__input"
                        type="text"
                        wire:model="clienteCidade"
                        readonly
                        tabindex="-1"
                    >
                @endif
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--uf">
                <span>UF</span>
                @if ($avulso)
                    <select
                        id="orc-uf"
                        class="erp-nfe__input erp-orc-header-panel__select"
                        wire:model.live="clienteUf"
                        wire:keydown.enter.prevent="focusNextClienteAvulsoField('orc-uf')"
                    >
                        @foreach ($ufs as $uf)
                            <option value="{{ $uf }}">{{ $uf }}</option>
                        @endforeach
                    </select>
                @else
                    <input id="orc-uf" class="erp-nfe__input" type="text" readonly value="{{ $this->clienteUf }}" tabindex="-1">
                @endif
            </label>
        </div>

        <div class="erp-fv-tv__row erp-fv-tv__row--secondary erp-orc-header-panel__meta">
            <label class="erp-fv-tv__field erp-fv-tv__field--vend">
                <span>Vendedor</span>
                <select
                    id="orc-vendedor"
                    class="erp-nfe__input erp-orc-header-panel__select"
                    wire:model="vendedorId"
                    @disabled($readOnly)
                >
                    <option value="">Selecione...</option>
                    @foreach ($this->vendedorOptions() as $vendedor)
                        <option value="{{ $vendedor['id'] }}">{{ $vendedor['nome'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-fv-tv__field erp-orc-header-panel__forma">
                <span>Forma de pagamento</span>
                <select
                    id="orc-forma"
                    class="erp-nfe__input erp-orc-header-panel__select"
                    wire:model="formaPagamento"
                    @disabled($readOnly)
                >
                    <option value="">Selecione...</option>
                    @foreach ($this->formaPagamentoOptions() as $forma)
                        <option value="{{ $forma['descricao'] }}">{{ $forma['descricao'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-fv-tv__field erp-orc-header-panel__validade">
                <span>Validade (dias)</span>
                <input
                    id="orc-validade"
                    class="erp-nfe__input"
                    type="number"
                    min="0"
                    wire:model="validadeDias"
                    @disabled($readOnly)
                >
            </label>
        </div>
    </div>
</section>
