@if ($this->transportadoraModalOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-transportadora-form-modal"
        wire:keydown.escape.window="closeTransportadoraModal"
        wire:keydown.f5.window.prevent="saveTransportadora"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeTransportadoraModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window erp-contador-form-modal--transportadora"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-transportadora-form-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-transportadora-form-title">Cadastro de Transportadora / Motorista</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeTransportadoraModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-transportadora-form-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-transportadora-form-modal__form">
                    <section class="erp-transportadora-form__section">
                        <h3 class="erp-transportadora-form__section-title">Identificação</h3>
                        <div class="erp-transportadora-form__section-grid">
                            <div class="erp-pcad-form__row erp-transportadora-form__row erp-transportadora-form__row--duo">
                                <label class="erp-pcad-form__label" for="transportadora-codigo">Código</label>
                                <input
                                    id="transportadora-codigo"
                                    type="text"
                                    wire:model="transportadoraForm.codigo"
                                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                                    @if ($this->transportadoraModalRecordId) readonly @endif
                                >
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="transportadora-tipo-pessoa">Pessoa</label>
                                <select
                                    id="transportadora-tipo-pessoa"
                                    wire:model.live="transportadoraForm.tipo_pessoa"
                                    class="erp-pcad-form__select erp-pcad-form__select--sm"
                                >
                                    @foreach ($this->transportadoraTipoPessoaOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="erp-pcad-form__row erp-transportadora-form__row erp-transportadora-form__row--doc">
                                <label class="erp-pcad-form__label" for="transportadora-cnpj-cpf">CNPJ/CPF</label>
                                <input
                                    id="transportadora-cnpj-cpf"
                                    type="text"
                                    wire:model="transportadoraForm.cnpj_cpf"
                                    data-mask="cpf-cnpj"
                                    class="erp-pcad-form__input erp-pcad-form__input--doc"
                                >
                                <button
                                    type="button"
                                    wire:click="searchTransportadoraCnpj"
                                    wire:loading.attr="disabled"
                                    wire:target="searchTransportadoraCnpj"
                                    class="erp-pcad-form__btn erp-transportadora-form__btn-search"
                                    title="Pesquisar CNPJ e preencher automaticamente"
                                >
                                    <span class="erp-pcad-form__btn-icon">🔍</span>
                                    <span wire:loading.remove wire:target="searchTransportadoraCnpj">Pesquisar CNPJ</span>
                                    <span wire:loading wire:target="searchTransportadoraCnpj">Consultando...</span>
                                </button>
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="transportadora-rg-ie">RG/IE</label>
                                <input
                                    id="transportadora-rg-ie"
                                    type="text"
                                    wire:model="transportadoraForm.rg_ie"
                                    class="erp-pcad-form__input erp-pcad-form__input--md"
                                >
                                @error('transportadoraForm.cnpj_cpf')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-transportadora-form__row erp-transportadora-form__row--single">
                                <label class="erp-pcad-form__label" for="transportadora-proprietario">Proprietário</label>
                                <input
                                    id="transportadora-proprietario"
                                    type="text"
                                    wire:model="transportadoraForm.proprietario"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                                    autofocus
                                >
                                @error('transportadoraForm.proprietario')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-transportadora-form__row erp-transportadora-form__row--single">
                                <label class="erp-pcad-form__label" for="transportadora-apelido">Apelido</label>
                                <input
                                    id="transportadora-apelido"
                                    type="text"
                                    wire:model="transportadoraForm.apelido"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                                >
                            </div>

                            <div class="erp-pcad-form__row erp-transportadora-form__row">
                                <label class="erp-pcad-form__label" for="transportadora-whatsapp">WhatsApp</label>
                                <input
                                    id="transportadora-whatsapp"
                                    type="text"
                                    wire:model="transportadoraForm.whatsapp"
                                    data-mask="phone"
                                    class="erp-pcad-form__input erp-pcad-form__input--phone"
                                    placeholder="(00) 00000-0000"
                                >
                                @error('transportadoraForm.whatsapp')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </section>

                    <section class="erp-transportadora-form__section">
                        <h3 class="erp-transportadora-form__section-title">Endereço</h3>
                        <div class="erp-transportadora-form__section-grid">
                            <div class="erp-pcad-form__row erp-transportadora-form__row">
                                <label class="erp-pcad-form__label" for="transportadora-cep">CEP</label>
                                <input
                                    id="transportadora-cep"
                                    type="text"
                                    wire:model="transportadoraForm.cep"
                                    data-mask="cep"
                                    class="erp-pcad-form__input erp-pcad-form__input--cep"
                                >
                            </div>

                            <div class="erp-pcad-form__row erp-transportadora-form__row erp-transportadora-form__row--full">
                                <label class="erp-pcad-form__label" for="transportadora-endereco">Endereço</label>
                                <input
                                    id="transportadora-endereco"
                                    type="text"
                                    wire:model="transportadoraForm.endereco"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                                >
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="transportadora-numero">Número</label>
                                <input
                                    id="transportadora-numero"
                                    type="text"
                                    wire:model="transportadoraForm.numero"
                                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                                >
                            </div>

                            <div class="erp-pcad-form__row erp-transportadora-form__row erp-transportadora-form__row--single">
                                <label class="erp-pcad-form__label" for="transportadora-bairro">Bairro</label>
                                <input
                                    id="transportadora-bairro"
                                    type="text"
                                    wire:model="transportadoraForm.bairro"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                                >
                            </div>

                            <div class="erp-pcad-form__row erp-transportadora-form__row">
                                <label class="erp-pcad-form__label" for="transportadora-codigo-municipio">Cód. IBGE</label>
                                <input
                                    id="transportadora-codigo-municipio"
                                    type="text"
                                    wire:model="transportadoraForm.codigo_municipio"
                                    class="erp-pcad-form__input erp-pcad-form__input--city-code"
                                    inputmode="numeric"
                                >
                            </div>

                            <div class="erp-pcad-form__row erp-transportadora-form__row erp-transportadora-form__row--full">
                                <label class="erp-pcad-form__label" for="transportadora-cidade">Cidade</label>
                                <input
                                    id="transportadora-cidade"
                                    type="text"
                                    wire:model="transportadoraForm.cidade"
                                    class="erp-pcad-form__input erp-pcad-form__input--city"
                                    list="transportadora-cidades"
                                >
                                <datalist id="transportadora-cidades">
                                    <option value="BALNEÁRIO CAMBORIÚ">
                                    <option value="CAMBORIÚ">
                                    <option value="FLORIANÓPOLIS">
                                    <option value="ITAJAÍ">
                                </datalist>
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="transportadora-uf">UF</label>
                                <select id="transportadora-uf" wire:model="transportadoraForm.uf" class="erp-pcad-form__select erp-pcad-form__select--uf">
                                    @foreach ($this->transportadoraUfOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="erp-transportadora-form__section erp-transportadora-form__section--motoristas">
                        <div class="erp-transportadora-motoristas">
                            <div class="erp-transportadora-motoristas__header">
                                <span class="erp-transportadora-motoristas__title">Motoristas</span>
                                <button type="button" wire:click="addMotoristaRow" class="erp-transportadora-motoristas__btn">
                                    + Incluir
                                </button>
                            </div>

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
                                                        class="erp-pcad-form__input erp-transportadora-motoristas__input"
                                                        placeholder="Nome do motorista"
                                                    >
                                                    @error('transportadoraForm.motoristas.'.$index.'.nome')
                                                        <span class="erp-contador-form-modal__error">{{ $message }}</span>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input
                                                        type="text"
                                                        wire:model.blur="transportadoraForm.motoristas.{{ $index }}.cpf"
                                                        data-mask="cpf"
                                                        class="erp-pcad-form__input erp-transportadora-motoristas__input erp-transportadora-motoristas__input--cpf"
                                                        placeholder="000.000.000-00"
                                                    >
                                                    @error('transportadoraForm.motoristas.'.$index.'.cpf')
                                                        <span class="erp-contador-form-modal__error">{{ $message }}</span>
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
                                                    Nenhum motorista cadastrado.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-transportadora-form-modal__actions">
                <button type="button" wire:click="saveTransportadora" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeTransportadoraModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
