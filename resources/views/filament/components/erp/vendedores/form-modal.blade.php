@if ($this->vendedorModalOpen)
    <div
        class="erp-lookup-modal erp-vendedor-form-modal"
        wire:keydown.escape.window="closeVendedorModal"
        wire:keydown.f5.window.prevent="saveVendedor"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeVendedorModal"></div>

        <div
            class="erp-lookup-modal__window erp-vendedor-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-vendedor-form-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-vendedor-form-title">{{ $this->vendedorModalRecordId ? 'Alterar Colaborador' : 'Novo Colaborador' }}</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeVendedorModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-vendedor-form-modal__body">
                <div class="erp-pcad-form erp-vendedor-form-modal__form">

                    {{-- Identificação --}}
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend">Identificação</legend>
                        <div class="erp-vform__grid">
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-codigo">Código</label>
                                <input id="vendedor-codigo" type="text" wire:model="vendedorForm.codigo"
                                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                                    @if ($this->vendedorModalRecordId) readonly @endif>
                                @error('vendedorForm.codigo') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-ativo">Ativo</label>
                                <select id="vendedor-ativo" wire:model="vendedorForm.ativo" class="erp-pcad-form__select erp-pcad-form__select--ativo">
                                    <option value="S">S</option>
                                    <option value="N">N</option>
                                </select>
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--full">
                                <label class="erp-pcad-form__label" for="vendedor-nome">Nome</label>
                                <input id="vendedor-nome" type="text" wire:model="vendedorForm.nome"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow" autofocus>
                                @error('vendedorForm.nome') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--full">
                                <label class="erp-pcad-form__label">Empresas</label>
                                @php($empresaCodigos = $this->empresaCodigos())
                                <div
                                    class="erp-vform__multi"
                                    x-data="{ open: false, q: '', codigos: @js($empresaCodigos) }"
                                    @click.outside="open = false"
                                    @keydown.escape.stop="open = false"
                                >
                                    <button type="button" class="erp-vform__multi-toggle" @click="open = !open">
                                        <span
                                            class="erp-vform__multi-summary"
                                            :class="{ 'erp-vform__multi-summary--empty': !($wire.vendedorForm.empresas || []).length }"
                                            x-text="($wire.vendedorForm.empresas || []).length
                                                ? $wire.vendedorForm.empresas.map(id => codigos[id]).filter(Boolean).join(', ')
                                                : 'Selecione as empresas...'"
                                        ></span>
                                        <span class="erp-vform__multi-count" x-show="($wire.vendedorForm.empresas || []).length"
                                            x-text="($wire.vendedorForm.empresas || []).length"></span>
                                        <svg class="erp-vform__multi-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :style="open ? 'transform:rotate(180deg)' : ''" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                    </button>

                                    <div class="erp-vform__multi-panel" x-show="open" x-cloak x-transition.opacity>
                                        @if (count($empresaCodigos))
                                            <input type="text" class="erp-vform__multi-search" placeholder="Pesquisar empresa..." x-model="q" @click.stop>
                                            <div class="erp-vform__multi-list">
                                                @foreach ($this->empresaOptions() as $id => $nome)
                                                    <label
                                                        class="erp-vform__check"
                                                        x-show="q === '' || @js(mb_strtolower(($empresaCodigos[$id] ?? '').' '.$nome, 'UTF-8')).includes(q.toLowerCase())"
                                                    >
                                                        <input type="checkbox" value="{{ $id }}" wire:model="vendedorForm.empresas">
                                                        <strong>{{ $empresaCodigos[$id] ?? '' }}</strong> {{ $nome }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="erp-vform__empresas-empty">Nenhuma empresa cadastrada.</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-cargo">Cargo</label>
                                <input id="vendedor-cargo" type="text" wire:model="vendedorForm.cargo" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-usuario">Usuário</label>
                                <select id="vendedor-usuario" wire:model="vendedorForm.usuario_id" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                    <option value="">— não vinculado —</option>
                                    @foreach ($this->usuarioOptions() as $id => $nome)
                                        <option value="{{ $id }}">{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    {{-- Dados Pessoais --}}
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend">Dados Pessoais</legend>
                        <div class="erp-vform__grid">
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-cpf">CPF</label>
                                <input id="vendedor-cpf" type="text" wire:model="vendedorForm.cpf" data-mask="cpf-cnpj" data-mask-pessoa="fisica" class="erp-pcad-form__input erp-pcad-form__input--grow">
                                @error('vendedorForm.cpf')
                                    <span class="erp-pcad-form__error">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-rg">RG</label>
                                <input id="vendedor-rg" type="text" wire:model="vendedorForm.rg" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-pis">PIS/PASEP</label>
                                <input id="vendedor-pis" type="text" wire:model="vendedorForm.pis_pasep" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-nasc">Nascimento</label>
                                <input id="vendedor-nasc" type="date" wire:model="vendedorForm.data_nascimento" data-erp-date-wire="iso" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                        </div>
                    </fieldset>

                    {{-- Endereço --}}
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend">Endereço</legend>
                        <div class="erp-vform__grid">
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-cep">CEP</label>
                                <input id="vendedor-cep" type="text" wire:model="vendedorForm.cep" data-mask="cep"
                                    x-on:blur="$wire.buscarCep()"
                                    class="erp-pcad-form__input erp-vform__cep">
                                <button type="button" wire:click="buscarCep" class="erp-vform__cep-btn" title="Pesquisar CEP">
                                    <span wire:loading.remove wire:target="buscarCep">🔍</span>
                                    <span wire:loading wire:target="buscarCep">…</span>
                                </button>
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-logr">Logradouro</label>
                                <select id="vendedor-logr" wire:model="vendedorForm.logradouro" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                    <option value="">—</option>
                                    @foreach ($this->logradouroOptions() as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--full">
                                <label class="erp-pcad-form__label" for="vendedor-end">Endereço</label>
                                <input id="vendedor-end" type="text" wire:model="vendedorForm.endereco" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-num">Número</label>
                                <input id="vendedor-num" type="text" wire:model="vendedorForm.numero" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-bairro">Bairro</label>
                                <input id="vendedor-bairro" type="text" wire:model="vendedorForm.bairro" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--full">
                                <label class="erp-pcad-form__label" for="vendedor-compl">Complemento</label>
                                <input id="vendedor-compl" type="text" wire:model="vendedorForm.complemento" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-cidade">Cidade</label>
                                <input id="vendedor-cidade" type="text" wire:model="vendedorForm.cidade_nome" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-uf">UF</label>
                                <input id="vendedor-uf" type="text" maxlength="2" wire:model="vendedorForm.uf" class="erp-pcad-form__input erp-pcad-form__input--xs">
                            </div>
                        </div>
                    </fieldset>

                    {{-- Contato --}}
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend">Contato</legend>
                        <div class="erp-vform__grid">
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-tel">Telefone</label>
                                <input id="vendedor-tel" type="text" wire:model="vendedorForm.telefone" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-wpp">WhatsApp</label>
                                <input id="vendedor-wpp" type="text" wire:model="vendedorForm.whatsapp" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--full">
                                <label class="erp-pcad-form__label" for="vendedor-email">E-mail</label>
                                <input id="vendedor-email" type="email" wire:model="vendedorForm.email" class="erp-pcad-form__input erp-pcad-form__input--grow">
                                @error('vendedorForm.email') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </fieldset>

                    {{-- Dados Trabalhistas --}}
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend">Dados Trabalhistas</legend>
                        <div class="erp-vform__grid">
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-ctps">CTPS</label>
                                <input id="vendedor-ctps" type="text" wire:model="vendedorForm.ctps" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-inss">INSS</label>
                                <input id="vendedor-inss" type="text" wire:model="vendedorForm.inss" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-adm">Admissão</label>
                                <input id="vendedor-adm" type="date" wire:model="vendedorForm.admissao" data-erp-date-wire="iso" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-dem">Demissão</label>
                                <input id="vendedor-dem" type="date" wire:model="vendedorForm.demissao" data-erp-date-wire="iso" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-tsal">Tipo Salário</label>
                                <select id="vendedor-tsal" wire:model="vendedorForm.tipo_salario" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                    <option value="">—</option>
                                    @foreach ($this->tipoSalarioOptions() as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-sal">Salário</label>
                                <input id="vendedor-sal" type="text" wire:model="vendedorForm.salario" inputmode="decimal" class="erp-pcad-form__input erp-pcad-form__input--comissao">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-estoque">Estoque</label>
                                <input id="vendedor-estoque" type="text" wire:model="vendedorForm.estoque" class="erp-pcad-form__input erp-pcad-form__input--grow">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-vform__check">
                                    <input type="checkbox" wire:model="vendedorForm.usar_agendamento"> Usar agendamento
                                </label>
                            </div>
                        </div>
                    </fieldset>

                    {{-- Setor de Vendas --}}
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend erp-vform__legend--check">
                            <label class="erp-vform__check">
                                <input type="checkbox" wire:model="vendedorForm.setor_vendas"> Setor de Vendas
                            </label>
                        </legend>
                        <div class="erp-vform__grid">
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-tab">Tabela Venda</label>
                                <select id="vendedor-tab" wire:model="vendedorForm.tabela_venda_id" class="erp-pcad-form__select erp-pcad-form__input--comissao">
                                    <option value="">— padrão —</option>
                                    @foreach ($this->tabelaVendaOptions() as $id => $nome)
                                        <option value="{{ $id }}">{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-comissao-av">Comissão AV</label>
                                <input id="vendedor-comissao-av" type="text" wire:model="vendedorForm.comissao_av" inputmode="decimal" class="erp-pcad-form__input erp-pcad-form__input--comissao">
                                @error('vendedorForm.comissao_av') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-comissao-ap">Comissão AP</label>
                                <input id="vendedor-comissao-ap" type="text" wire:model="vendedorForm.comissao_ap" inputmode="decimal" class="erp-pcad-form__input erp-pcad-form__input--comissao">
                                @error('vendedorForm.comissao_ap') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-meta">Meta Venda</label>
                                <input id="vendedor-meta" type="text" wire:model="vendedorForm.mobile_meta_venda" inputmode="decimal" class="erp-pcad-form__input erp-pcad-form__input--comissao">
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--full">
                                <label class="erp-vform__check">
                                    <input type="checkbox" wire:model="vendedorForm.ganha_comissao_todas_vendas"> Ganha comissão sobre todas as vendas
                                </label>
                            </div>
                        </div>
                    </fieldset>

                    {{-- Setor de Serviços --}}
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend erp-vform__legend--check">
                            <label class="erp-vform__check">
                                <input type="checkbox" wire:model="vendedorForm.setor_servicos"> Setor de Serviços
                            </label>
                        </legend>
                        <div class="erp-vform__grid">
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-comissao-serv">Comissão Serv.</label>
                                <input id="vendedor-comissao-serv" type="text" wire:model="vendedorForm.comissao_servico" inputmode="decimal" class="erp-pcad-form__input erp-pcad-form__input--comissao">
                                @error('vendedorForm.comissao_servico') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--full">
                                <label class="erp-vform__check">
                                    <input type="checkbox" wire:model="vendedorForm.ganha_comissao_todos_servicos"> Ganha comissão sobre todos os serviços
                                </label>
                            </div>
                        </div>
                    </fieldset>

                    {{-- Funções --}}
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend">Funções</legend>
                        <div class="erp-vform__grid erp-vform__grid--checks">
                            <label class="erp-vform__check"><input type="checkbox" wire:model="vendedorForm.efetua_venda"> Efetua venda</label>
                            <label class="erp-vform__check"><input type="checkbox" wire:model="vendedorForm.motorista"> Motorista</label>
                            <label class="erp-vform__check"><input type="checkbox" wire:model="vendedorForm.ajudante"> Ajudante</label>
                        </div>
                    </fieldset>

                    {{-- Observações --}}
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend">Observações</legend>
                        <textarea wire:model="vendedorForm.observacoes" rows="2" class="erp-pcad-form__input erp-vform__textarea"></textarea>
                    </fieldset>

                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-vendedor-form-modal__actions">
                <button type="button" wire:click="saveVendedor" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeVendedorModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
