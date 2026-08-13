@if ($this->transportadoraModalOpen)
    <div
        class="erp-lookup-modal erp-transportadora-form-modal"
        wire:keydown.escape.window="closeTransportadoraModal"
        wire:keydown.f5.window.prevent="saveTransportadora"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeTransportadoraModal"></div>

        <div
            class="erp-lookup-modal__window erp-transportadora-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-transportadora-form-title"
        >
            <div class="erp-lookup-modal__titlebar erp-transportadora-form-modal__titlebar">
                <div>
                    <span id="erp-transportadora-form-title">Cadastro de Transportadora / Motorista</span>
                    <p class="erp-transportadora-form-modal__subtitle">
                        {{ $this->transportadoraModalRecordId ? 'Alteração do cadastro' : 'Novo cadastro' }}
                    </p>
                </div>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeTransportadoraModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-transportadora-form-modal__body">
                <div class="erp-transportadora-form" data-erp-form autocomplete="off">
                    <section class="erp-transportadora-form__card">
                        <header class="erp-transportadora-form__card-head">
                            <strong>Identificação</strong>
                        </header>

                        <div class="erp-transportadora-form__grid">
                            <label class="erp-transportadora-form__field erp-transportadora-form__field--codigo">
                                <span>Código</span>
                                <input
                                    id="transportadora-codigo"
                                    type="text"
                                    wire:model="transportadoraForm.codigo"
                                    @if ($this->transportadoraModalRecordId) readonly @endif
                                >
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--pessoa">
                                <span>Pessoa</span>
                                <select id="transportadora-tipo-pessoa" wire:model.live="transportadoraForm.tipo_pessoa">
                                    @foreach ($this->transportadoraTipoPessoaOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--doc">
                                <span>CNPJ/CPF</span>
                                <div class="erp-transportadora-form__doc">
                                    <input
                                        id="transportadora-cnpj-cpf"
                                        type="text"
                                        wire:model="transportadoraForm.cnpj_cpf"
                                        data-mask="cpf-cnpj"
                                    >
                                    <button
                                        type="button"
                                        wire:click="searchTransportadoraCnpj"
                                        wire:loading.attr="disabled"
                                        wire:target="searchTransportadoraCnpj"
                                        class="erp-transportadora-form__search-btn"
                                        title="Pesquisar CNPJ e preencher automaticamente"
                                    >
                                        <span wire:loading.remove wire:target="searchTransportadoraCnpj">⌕ CNPJ</span>
                                        <span wire:loading wire:target="searchTransportadoraCnpj">…</span>
                                    </button>
                                </div>
                                @error('transportadoraForm.cnpj_cpf')
                                    <em class="erp-transportadora-form__error">{{ $message }}</em>
                                @enderror
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--rg">
                                <span>RG/IE</span>
                                <input id="transportadora-rg-ie" type="text" wire:model="transportadoraForm.rg_ie">
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--full">
                                <span>Proprietário</span>
                                <input
                                    id="transportadora-proprietario"
                                    type="text"
                                    wire:model="transportadoraForm.proprietario"
                                    autofocus
                                >
                                @error('transportadoraForm.proprietario')
                                    <em class="erp-transportadora-form__error">{{ $message }}</em>
                                @enderror
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--apelido">
                                <span>Apelido / Fantasia</span>
                                <input id="transportadora-apelido" type="text" wire:model="transportadoraForm.apelido">
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--whatsapp">
                                <span>WhatsApp</span>
                                <input
                                    id="transportadora-whatsapp"
                                    type="text"
                                    wire:model="transportadoraForm.whatsapp"
                                    data-mask="phone"
                                    placeholder="(00) 00000-0000"
                                >
                                @error('transportadoraForm.whatsapp')
                                    <em class="erp-transportadora-form__error">{{ $message }}</em>
                                @enderror
                            </label>
                        </div>
                    </section>

                    <section class="erp-transportadora-form__card">
                        <header class="erp-transportadora-form__card-head">
                            <strong>Endereço</strong>
                        </header>

                        <div class="erp-transportadora-form__grid">
                            <label class="erp-transportadora-form__field erp-transportadora-form__field--cep">
                                <span>CEP</span>
                                <input
                                    id="transportadora-cep"
                                    type="text"
                                    wire:model="transportadoraForm.cep"
                                    data-mask="cep"
                                >
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--endereco">
                                <span>Endereço</span>
                                <input id="transportadora-endereco" type="text" wire:model="transportadoraForm.endereco">
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--numero">
                                <span>Número</span>
                                <input id="transportadora-numero" type="text" wire:model="transportadoraForm.numero">
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--bairro">
                                <span>Bairro</span>
                                <input id="transportadora-bairro" type="text" wire:model="transportadoraForm.bairro">
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--ibge">
                                <span>Cód. IBGE</span>
                                <input
                                    id="transportadora-codigo-municipio"
                                    type="text"
                                    wire:model="transportadoraForm.codigo_municipio"
                                    inputmode="numeric"
                                >
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--cidade">
                                <span>Cidade</span>
                                <input
                                    id="transportadora-cidade"
                                    type="text"
                                    wire:model="transportadoraForm.cidade"
                                    list="transportadora-cidades"
                                >
                                <datalist id="transportadora-cidades">
                                    <option value="BALNEÁRIO CAMBORIÚ"></option>
                                    <option value="CAMBORIÚ"></option>
                                    <option value="FLORIANÓPOLIS"></option>
                                    <option value="ITAJAÍ"></option>
                                </datalist>
                            </label>

                            <label class="erp-transportadora-form__field erp-transportadora-form__field--uf">
                                <span>UF</span>
                                <select id="transportadora-uf" wire:model="transportadoraForm.uf">
                                    @foreach ($this->transportadoraUfOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </section>

                    <section class="erp-transportadora-form__card erp-transportadora-form__card--motoristas">
                        <header class="erp-transportadora-form__card-head erp-transportadora-form__card-head--row">
                            <div>
                                <strong>Motoristas</strong>
                                <p>Inclua os motoristas vinculados a esta transportadora.</p>
                            </div>
                            <button type="button" wire:click="addMotoristaRow" class="erp-transportadora-form__add-btn">
                                + Incluir
                            </button>
                        </header>

                        <div class="erp-transportadora-motoristas__wrap">
                            <table class="erp-transportadora-motoristas__table">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>CPF</th>
                                        <th class="erp-transportadora-motoristas__col-action" aria-hidden="true"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->transportadoraForm['motoristas'] ?? [] as $index => $motorista)
                                        <tr wire:key="transportadora-motorista-{{ $index }}-{{ $motorista['id'] ?? 'new' }}">
                                            <td>
                                                <input
                                                    type="text"
                                                    wire:model.blur="transportadoraForm.motoristas.{{ $index }}.nome"
                                                    class="erp-transportadora-motoristas__input"
                                                    placeholder="Nome do motorista"
                                                >
                                                @error('transportadoraForm.motoristas.'.$index.'.nome')
                                                    <em class="erp-transportadora-form__error">{{ $message }}</em>
                                                @enderror
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    wire:model.blur="transportadoraForm.motoristas.{{ $index }}.cpf"
                                                    data-mask="cpf"
                                                    class="erp-transportadora-motoristas__input erp-transportadora-motoristas__input--cpf"
                                                    placeholder="000.000.000-00"
                                                >
                                                @error('transportadoraForm.motoristas.'.$index.'.cpf')
                                                    <em class="erp-transportadora-form__error">{{ $message }}</em>
                                                @enderror
                                            </td>
                                            <td class="erp-transportadora-motoristas__col-action">
                                                <button
                                                    type="button"
                                                    wire:click="removeMotoristaRow({{ $index }})"
                                                    class="erp-transportadora-motoristas__remove"
                                                    title="Remover motorista"
                                                >✕</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="erp-transportadora-motoristas__empty">
                                                Nenhum motorista cadastrado. Use <strong>+ Incluir</strong> para adicionar.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>

            <footer class="erp-transportadora-form-modal__actions">
                <button type="button" wire:click="saveTransportadora" class="erp-transportadora-form-modal__btn erp-transportadora-form-modal__btn--save" data-erp-key="F5">
                    <span class="erp-transportadora-form-modal__btn-icon">✓</span>
                    <span><kbd>F5</kbd> Gravar</span>
                </button>
                <button type="button" wire:click="closeTransportadoraModal" class="erp-transportadora-form-modal__btn erp-transportadora-form-modal__btn--exit" data-erp-key="Escape">
                    <span class="erp-transportadora-form-modal__btn-icon">✕</span>
                    <span><kbd>ESC</kbd> Sair</span>
                </button>
            </footer>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
