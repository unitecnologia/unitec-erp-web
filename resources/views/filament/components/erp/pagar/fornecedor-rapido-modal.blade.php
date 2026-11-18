@if ($this->fornecedorRapidoModalOpen)
    @php
        $isPj = $this->fornecedorRapidoTipo === \App\Models\Person::PESSOA_JURIDICA;
        $busy = $this->fornecedorRapidoSalvando || $this->fornecedorRapidoConsultando;
    @endphp
    <div
        class="erp-pagar-forn-rapido"
        wire:key="pagar-fornecedor-rapido"
        x-data="{ saving: false }"
        x-on:keydown.window="
            if (! $wire.fornecedorRapidoModalOpen) return;
            if ($event.key === 'F5') {
                $event.preventDefault();
                if ($event.repeat || saving || $wire.fornecedorRapidoSalvando || $wire.fornecedorRapidoConsultando) return;
                saving = true;
                Promise.resolve($wire.salvarFornecedorRapido()).finally(() => { saving = false });
            }
        "
        x-on:erp-pagar-fornecedor-rapido-focus.window="$nextTick(() => {
            const el = document.getElementById('erp-pagar-forn-rapido-doc');
            if (el) { el.focus(); el.select && el.select(); }
        })"
    >
        <div class="erp-pagar-forn-rapido__backdrop" wire:click="closeFornecedorRapidoModal"></div>

        <div class="erp-pagar-forn-rapido__dialog" role="dialog" aria-modal="true" aria-labelledby="erp-pagar-forn-rapido-title" wire:click.stop>
            <header class="erp-pagar-forn-rapido__titlebar">
                <span id="erp-pagar-forn-rapido-title">Cadastro rápido de fornecedor</span>
                <button
                    type="button"
                    class="erp-pagar-forn-rapido__close"
                    wire:click="closeFornecedorRapidoModal"
                    title="ESC | Cancelar"
                    aria-label="Fechar"
                    @disabled($busy)
                >&times;</button>
            </header>

            <div class="erp-pagar-forn-rapido__scroll">
                @if ($this->fornecedorRapidoDuplicadoId)
                    <div class="erp-pagar-forn-rapido__dup">
                        <div class="erp-pagar-forn-rapido__dup-text">
                            Já existe cadastro com este documento:
                            <strong>{{ $this->fornecedorRapidoDuplicadoLabel }}</strong>
                            @if (! $this->fornecedorRapidoDuplicadoEhFornecedor)
                                <span class="erp-pagar-forn-rapido__dup-hint">(ainda não marcado como fornecedor)</span>
                            @endif
                        </div>
                        <button
                            type="button"
                            class="erp-pagar-form-modal__btn erp-pagar-form-modal__btn--save-new"
                            wire:click="usarFornecedorRapidoDuplicado"
                            wire:loading.attr="disabled"
                            wire:target="usarFornecedorRapidoDuplicado"
                        >
                            Usar cadastro existente
                        </button>
                    </div>
                @endif

                <section class="erp-pagar-forn-rapido__section">
                    <h3 class="erp-pagar-forn-rapido__section-title">Identificação</h3>
                    <div class="erp-pagar-forn-rapido__grid">
                        <label class="erp-pagar-forn-rapido__field">
                            <span>Tipo</span>
                            <select class="erp-pagar-form-modal__input" wire:model.live="fornecedorRapidoTipo" @disabled($busy)>
                                <option value="{{ \App\Models\Person::PESSOA_JURIDICA }}">Jurídica</option>
                                <option value="{{ \App\Models\Person::PESSOA_FISICA }}">Física</option>
                            </select>
                            @error('fornecedorRapidoTipo') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>

                        <div class="erp-pagar-forn-rapido__field">
                            <span>{{ $isPj ? 'CNPJ' : 'CPF' }}</span>
                            <div class="erp-pagar-forn-rapido__doc-row">
                                <input
                                    id="erp-pagar-forn-rapido-doc"
                                    type="text"
                                    class="erp-pagar-form-modal__input"
                                    wire:model="fornecedorRapidoCpfCnpj"
                                    maxlength="20"
                                    data-mask="cpf-cnpj"
                                    autocomplete="off"
                                    @disabled($busy)
                                >
                                @if ($isPj)
                                    <button
                                        type="button"
                                        class="erp-pagar-forn-rapido__btn-lookup"
                                        wire:click="consultarCnpjFornecedorRapido"
                                        wire:loading.attr="disabled"
                                        wire:target="consultarCnpjFornecedorRapido,buscarCepFornecedorRapido"
                                        title="Consultar CNPJ"
                                        @disabled($busy)
                                    >Consultar</button>
                                @endif
                            </div>
                            @error('fornecedorRapidoCpfCnpj') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </div>

                        <label class="erp-pagar-forn-rapido__field erp-pagar-forn-rapido__field--wide">
                            <span>{{ $isPj ? 'Razão Social' : 'Nome' }}</span>
                            <input
                                type="text"
                                class="erp-pagar-form-modal__input"
                                wire:model="fornecedorRapidoNome"
                                maxlength="120"
                                data-erp-uppercase
                                autocomplete="off"
                                @disabled($busy)
                            >
                            @error('fornecedorRapidoNome') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>

                        <label class="erp-pagar-forn-rapido__field erp-pagar-forn-rapido__field--wide">
                            <span>{{ $isPj ? 'Fantasia' : 'Apelido' }}</span>
                            <input
                                type="text"
                                class="erp-pagar-form-modal__input"
                                wire:model="fornecedorRapidoFantasia"
                                maxlength="120"
                                data-erp-uppercase
                                autocomplete="off"
                                @disabled($busy)
                            >
                            @error('fornecedorRapidoFantasia') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>

                        @if ($isPj)
                            <label class="erp-pagar-forn-rapido__field">
                                <span>IE</span>
                                <input
                                    type="text"
                                    class="erp-pagar-form-modal__input"
                                    wire:model.blur="fornecedorRapidoIe"
                                    maxlength="30"
                                    data-erp-uppercase
                                    autocomplete="off"
                                    @disabled($busy)
                                >
                                @error('fornecedorRapidoIe') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                            </label>

                            <label class="erp-pagar-forn-rapido__field">
                                <span>Contribuinte</span>
                                <select class="erp-pagar-form-modal__input" wire:model="fornecedorRapidoTipoContribuinte" @disabled($busy)>
                                    @foreach (\App\Models\Person::tiposContribuinte() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('fornecedorRapidoTipoContribuinte') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                            </label>
                        @endif
                    </div>
                </section>

                <section class="erp-pagar-forn-rapido__section">
                    <h3 class="erp-pagar-forn-rapido__section-title">Contato</h3>
                    <div class="erp-pagar-forn-rapido__grid">
                        <label class="erp-pagar-forn-rapido__field">
                            <span>Telefone</span>
                            <input
                                type="text"
                                class="erp-pagar-form-modal__input"
                                wire:model="fornecedorRapidoFone"
                                maxlength="20"
                                data-mask="phone"
                                autocomplete="off"
                                @disabled($busy)
                            >
                            @error('fornecedorRapidoFone') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>

                        <label class="erp-pagar-forn-rapido__field">
                            <span>E-mail</span>
                            <input
                                type="email"
                                class="erp-pagar-form-modal__input"
                                wire:model="fornecedorRapidoEmail"
                                maxlength="120"
                                autocomplete="off"
                                @disabled($busy)
                            >
                            @error('fornecedorRapidoEmail') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>
                    </div>
                </section>

                <section class="erp-pagar-forn-rapido__section">
                    <h3 class="erp-pagar-forn-rapido__section-title">Endereço</h3>
                    <div class="erp-pagar-forn-rapido__grid">
                        <div class="erp-pagar-forn-rapido__field">
                            <span>CEP</span>
                            <div class="erp-pagar-forn-rapido__doc-row">
                                <input
                                    type="text"
                                    class="erp-pagar-form-modal__input"
                                    wire:model="fornecedorRapidoCep"
                                    maxlength="10"
                                    data-mask="cep"
                                    autocomplete="off"
                                    wire:blur="buscarCepFornecedorRapido(true)"
                                    @disabled($busy)
                                >
                                <button
                                    type="button"
                                    class="erp-pagar-forn-rapido__btn-lookup"
                                    wire:click="buscarCepFornecedorRapido"
                                    wire:loading.attr="disabled"
                                    wire:target="consultarCnpjFornecedorRapido,buscarCepFornecedorRapido"
                                    title="Pesquisar CEP"
                                    @disabled($busy)
                                >CEP</button>
                            </div>
                            @error('fornecedorRapidoCep') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </div>

                        <label class="erp-pagar-forn-rapido__field">
                            <span>Número</span>
                            <input
                                type="text"
                                class="erp-pagar-form-modal__input"
                                wire:model="fornecedorRapidoNumero"
                                maxlength="20"
                                autocomplete="off"
                                @disabled($busy)
                            >
                            @error('fornecedorRapidoNumero') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>

                        <label class="erp-pagar-forn-rapido__field erp-pagar-forn-rapido__field--wide">
                            <span>Logradouro</span>
                            <input
                                type="text"
                                class="erp-pagar-form-modal__input"
                                wire:model="fornecedorRapidoEndereco"
                                maxlength="120"
                                data-erp-uppercase
                                autocomplete="off"
                                @disabled($busy)
                            >
                            @error('fornecedorRapidoEndereco') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>

                        <label class="erp-pagar-forn-rapido__field">
                            <span>Bairro</span>
                            <input
                                type="text"
                                class="erp-pagar-form-modal__input"
                                wire:model="fornecedorRapidoBairro"
                                maxlength="80"
                                data-erp-uppercase
                                autocomplete="off"
                                @disabled($busy)
                            >
                            @error('fornecedorRapidoBairro') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>

                        <label class="erp-pagar-forn-rapido__field">
                            <span>Complemento</span>
                            <input
                                type="text"
                                class="erp-pagar-form-modal__input"
                                wire:model="fornecedorRapidoComplemento"
                                maxlength="60"
                                data-erp-uppercase
                                autocomplete="off"
                                @disabled($busy)
                            >
                            @error('fornecedorRapidoComplemento') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>

                        <label class="erp-pagar-forn-rapido__field">
                            <span>Município</span>
                            <input
                                type="text"
                                class="erp-pagar-form-modal__input"
                                wire:model="fornecedorRapidoCidadeNome"
                                maxlength="80"
                                data-erp-uppercase
                                autocomplete="off"
                                @disabled($busy)
                            >
                            @error('fornecedorRapidoCidadeNome') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>

                        <label class="erp-pagar-forn-rapido__field">
                            <span>UF</span>
                            <select class="erp-pagar-form-modal__input" wire:model="fornecedorRapidoUf" @disabled($busy)>
                                <option value="">—</option>
                                @foreach (\App\Models\Person::ufs() as $uf => $label)
                                    <option value="{{ $uf }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('fornecedorRapidoUf') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>

                        <label class="erp-pagar-forn-rapido__field erp-pagar-forn-rapido__field--wide">
                            <span>IBGE</span>
                            <input
                                type="text"
                                class="erp-pagar-form-modal__input erp-pagar-form-modal__input--ro"
                                wire:model="fornecedorRapidoCidadeCodigo"
                                maxlength="10"
                                readonly
                                tabindex="-1"
                                title="Código IBGE da cidade (preenchido pela consulta de CEP/CNPJ)"
                            >
                            @error('fornecedorRapidoCidadeCodigo') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        </label>
                    </div>
                </section>
            </div>

            <footer class="erp-pagar-forn-rapido__footer">
                <button
                    type="button"
                    class="erp-pagar-form-modal__btn erp-pagar-form-modal__btn--save"
                    x-bind:disabled="saving"
                    wire:loading.attr="disabled"
                    wire:target="salvarFornecedorRapido,consultarCnpjFornecedorRapido,buscarCepFornecedorRapido"
                    x-on:click="
                        if (saving || $wire.fornecedorRapidoSalvando || $wire.fornecedorRapidoConsultando) return;
                        saving = true;
                        Promise.resolve($wire.salvarFornecedorRapido()).finally(() => { saving = false });
                    "
                >
                    <kbd>F5</kbd> | Salvar
                </button>
                <button
                    type="button"
                    class="erp-pagar-form-modal__btn erp-pagar-form-modal__btn--exit"
                    wire:click="closeFornecedorRapidoModal"
                    x-bind:disabled="saving"
                    wire:loading.attr="disabled"
                    wire:target="salvarFornecedorRapido"
                >
                    <kbd>ESC</kbd> | Cancelar
                </button>
            </footer>
        </div>
    </div>
@endif
