@if ($this->rhFuncionarioModalOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-rh-funcionario-form-modal"
        wire:keydown.escape.window="closeRhFuncionarioModal"
        wire:keydown.f5.window.prevent="saveRhFuncionario"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeRhFuncionarioModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window erp-rh-func-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-rh-funcionario-form-title"
            x-data="{ tab: 'ident' }"
            @erp-rh-func-goto-tab.window="tab = ($event.detail?.tab ?? 'ident')"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-rh-funcionario-form-title">Cadastro de Funcionário</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeRhFuncionarioModal" title="Fechar">✕</button>
            </div>

            <nav class="erp-rh-func-modal__tabs" role="tablist" aria-label="Seções do cadastro">
                <button type="button" class="erp-rh-func-modal__tab" :class="{ 'is-active': tab === 'ident' }" @click="tab = 'ident'" role="tab">Identificação</button>
                <button type="button" class="erp-rh-func-modal__tab" :class="{ 'is-active': tab === 'end' }" @click="tab = 'end'" role="tab">Endereço</button>
                <button type="button" class="erp-rh-func-modal__tab" :class="{ 'is-active': tab === 'contato' }" @click="tab = 'contato'" role="tab">Contato</button>
                <button type="button" class="erp-rh-func-modal__tab" :class="{ 'is-active': tab === 'trab' }" @click="tab = 'trab'" role="tab">Trabalhistas</button>
                <button type="button" class="erp-rh-func-modal__tab" :class="{ 'is-active': tab === 'oper' }" @click="tab = 'oper'" role="tab">Operador</button>
            </nav>

            @if ($errors->any())
                <div class="erp-rh-func-modal__error-banner" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-rh-func-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-rh-func-modal__form">

                    <div class="erp-rh-func-modal__panel" x-show="tab === 'ident'" x-cloak>
                        <div class="erp-rh-func-modal__grid">
                            <label class="erp-rh-func-modal__field erp-rh-func-modal__field--sm">
                                <span>Código</span>
                                <input type="text" wire:model="rhFuncionarioForm.codigo" class="erp-rh-func-modal__input" readonly tabindex="-1">
                            </label>
                            <label class="erp-rh-func-modal__field erp-rh-func-modal__field--grow">
                                <span>Nome <em class="erp-rh-func-modal__req">*</em></span>
                                <input type="text" wire:model="rhFuncionarioForm.nome" class="erp-rh-func-modal__input" data-erp-uppercase maxlength="120" autofocus required>
                            </label>
                            <label class="erp-rh-func-modal__field erp-rh-func-modal__field--check">
                                <span>Situação</span>
                                <span class="erp-rh-func-modal__check">
                                    <input type="checkbox" wire:model="rhFuncionarioForm.ativo" id="rh-func-ativo">
                                    <label for="rh-func-ativo">Ativo</label>
                                </span>
                            </label>

                            <label class="erp-rh-func-modal__field">
                                <span>CPF</span>
                                <input type="text" wire:model.blur="rhFuncionarioForm.cpf" data-mask="cpf-cnpj" data-mask-pessoa="fisica" class="erp-rh-func-modal__input" maxlength="14" inputmode="numeric" placeholder="000.000.000-00">
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>RG</span>
                                <input type="text" wire:model="rhFuncionarioForm.rg" class="erp-rh-func-modal__input" data-erp-uppercase maxlength="30">
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>PIS/PASEP</span>
                                <input type="text" wire:model="rhFuncionarioForm.pis_pasep" class="erp-rh-func-modal__input" maxlength="20">
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>Nascimento</span>
                                <input type="date" wire:model="rhFuncionarioForm.data_nascimento" data-erp-date-wire="iso" class="erp-rh-func-modal__input">
                            </label>

                            <label class="erp-rh-func-modal__field">
                                <span>Cargo <em class="erp-rh-func-modal__req">*</em></span>
                                <select wire:model="rhFuncionarioForm.cargo_id" class="erp-rh-func-modal__input" required>
                                    <option value="">— selecione —</option>
                                    @foreach ($this->rhCargoOptions as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['nome'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>Departamento</span>
                                <select wire:model="rhFuncionarioForm.departamento_id" class="erp-rh-func-modal__input">
                                    <option value="">— selecione —</option>
                                    @foreach ($this->rhDepartamentoOptions as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['nome'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        @error('rhFuncionarioForm.nome')
                            <p class="erp-rh-func-modal__error">{{ $message }}</p>
                        @enderror
                        @error('rhFuncionarioForm.cargo_id')
                            <p class="erp-rh-func-modal__error">{{ $message }}</p>
                        @enderror
                        @error('rhFuncionarioForm.departamento_id')
                            <p class="erp-rh-func-modal__error">{{ $message }}</p>
                        @enderror
                        @error('rhFuncionarioForm.codigo')
                            <p class="erp-rh-func-modal__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="erp-rh-func-modal__panel" x-show="tab === 'end'" x-cloak>
                        <div class="erp-rh-func-modal__grid">
                            <label class="erp-rh-func-modal__field erp-rh-func-modal__field--sm">
                                <span>CEP</span>
                                <input type="text" wire:model="rhFuncionarioForm.cep" data-mask="cep" x-on:blur="$wire.buscarCepRhFuncionario()" class="erp-rh-func-modal__input" maxlength="9">
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>Logradouro</span>
                                <select wire:model="rhFuncionarioForm.logradouro" class="erp-rh-func-modal__input">
                                    <option value="">—</option>
                                    @foreach ($this->rhLogradouroOptions() as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-rh-func-modal__field erp-rh-func-modal__field--grow">
                                <span>Endereço</span>
                                <input type="text" wire:model="rhFuncionarioForm.endereco" class="erp-rh-func-modal__input" data-erp-uppercase maxlength="120">
                            </label>

                            <label class="erp-rh-func-modal__field erp-rh-func-modal__field--sm">
                                <span>Número</span>
                                <input type="text" wire:model="rhFuncionarioForm.numero" class="erp-rh-func-modal__input" maxlength="20">
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>Bairro</span>
                                <input type="text" wire:model="rhFuncionarioForm.bairro" class="erp-rh-func-modal__input" data-erp-uppercase maxlength="80">
                            </label>
                            <label class="erp-rh-func-modal__field erp-rh-func-modal__field--grow">
                                <span>Complemento</span>
                                <input type="text" wire:model="rhFuncionarioForm.complemento" class="erp-rh-func-modal__input" data-erp-uppercase maxlength="80">
                            </label>

                            <label class="erp-rh-func-modal__field erp-rh-func-modal__field--grow">
                                <span>Cidade</span>
                                <input type="text" wire:model="rhFuncionarioForm.cidade_nome" class="erp-rh-func-modal__input" data-erp-uppercase maxlength="80">
                            </label>
                            <label class="erp-rh-func-modal__field erp-rh-func-modal__field--xs">
                                <span>UF</span>
                                <input type="text" wire:model="rhFuncionarioForm.uf" class="erp-rh-func-modal__input" data-erp-uppercase maxlength="2">
                            </label>
                        </div>
                    </div>

                    <div class="erp-rh-func-modal__panel" x-show="tab === 'contato'" x-cloak>
                        <div class="erp-rh-func-modal__grid">
                            <label class="erp-rh-func-modal__field">
                                <span>Telefone</span>
                                <input type="text" wire:model.blur="rhFuncionarioForm.telefone" data-mask="phone" class="erp-rh-func-modal__input" maxlength="20" inputmode="tel">
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>WhatsApp</span>
                                <input type="text" wire:model.blur="rhFuncionarioForm.whatsapp" data-mask="phone" class="erp-rh-func-modal__input" maxlength="20" inputmode="tel">
                            </label>
                            <label class="erp-rh-func-modal__field erp-rh-func-modal__field--grow">
                                <span>E-mail</span>
                                <input type="email" wire:model="rhFuncionarioForm.email" class="erp-rh-func-modal__input" maxlength="120">
                            </label>
                        </div>
                        @error('rhFuncionarioForm.email')
                            <p class="erp-rh-func-modal__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="erp-rh-func-modal__panel" x-show="tab === 'trab'" x-cloak>
                        <div class="erp-rh-func-modal__grid">
                            <label class="erp-rh-func-modal__field">
                                <span>CTPS</span>
                                <input type="text" wire:model="rhFuncionarioForm.ctps" class="erp-rh-func-modal__input" data-erp-uppercase maxlength="30">
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>INSS</span>
                                <input type="text" wire:model="rhFuncionarioForm.inss" class="erp-rh-func-modal__input" maxlength="30">
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>Tipo salário</span>
                                <select wire:model="rhFuncionarioForm.tipo_salario" class="erp-rh-func-modal__input">
                                    <option value="">—</option>
                                    @foreach ($this->rhTipoSalarioOptions() as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>Salário</span>
                                <input type="text" wire:model.blur="rhFuncionarioForm.salario" class="erp-rh-func-modal__input" inputmode="decimal" data-mask="money-br">
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>Admissão</span>
                                <input type="date" wire:model="rhFuncionarioForm.data_admissao" data-erp-date-wire="iso" class="erp-rh-func-modal__input">
                            </label>
                            <label class="erp-rh-func-modal__field">
                                <span>Demissão</span>
                                <input type="date" wire:model="rhFuncionarioForm.data_demissao" data-erp-date-wire="iso" class="erp-rh-func-modal__input">
                            </label>
                        </div>
                    </div>

                    <div class="erp-rh-func-modal__panel erp-rh-func-modal__panel--oper" x-show="tab === 'oper'" x-cloak>
                        <div class="erp-rh-oper">
                            <label class="erp-rh-oper__toggle">
                                <input type="checkbox" wire:model.live="rhFuncionarioForm.eh_operador" id="rh-func-eh-operador">
                                <span>Este funcionário é operador (PDV / vendas)</span>
                            </label>
                            <p class="erp-rh-oper__hint">
                                Empresas e caixas vêm de <strong>Permissões / Usuários</strong> (abas Empresas e Caixas).
                            </p>

                            <div class="erp-rh-oper__fields" @if (! ($this->rhFuncionarioForm['eh_operador'] ?? false)) style="opacity:.55;pointer-events:none" @endif>
                                <div class="erp-rh-func-modal__grid">
                                    <label class="erp-rh-func-modal__field erp-rh-func-modal__field--grow">
                                        <span>Usuário @if ($this->rhFuncionarioForm['eh_operador'] ?? false)<em class="erp-rh-func-modal__req">*</em>@endif</span>
                                        <select wire:model="rhFuncionarioForm.usuario_id" class="erp-rh-func-modal__input" @if ($this->rhFuncionarioForm['eh_operador'] ?? false) required @endif>
                                            <option value="">— selecione —</option>
                                            @foreach ($this->rhUsuarioOptions() as $id => $nome)
                                                <option value="{{ $id }}">{{ $nome }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="erp-rh-func-modal__field erp-rh-func-modal__field--grow">
                                        <span>Estoque @if ($this->rhFuncionarioForm['eh_operador'] ?? false)<em class="erp-rh-func-modal__req">*</em>@endif</span>
                                        <select wire:model="rhFuncionarioForm.estoque_id" class="erp-rh-func-modal__input" @if ($this->rhFuncionarioForm['eh_operador'] ?? false) required @endif>
                                            <option value="">— selecione —</option>
                                            @foreach ($this->rhEstoqueOptions() as $id => $nome)
                                                <option value="{{ $id }}">{{ $nome }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                @error('rhFuncionarioForm.usuario_id')
                                    <div class="erp-rh-func-modal__error">{{ $message }}</div>
                                @enderror
                                @error('rhFuncionarioForm.estoque_id')
                                    <div class="erp-rh-func-modal__error">{{ $message }}</div>
                                @enderror

                                <div class="erp-rh-oper__block">
                                    <span class="erp-rh-oper__label">PDVs liberados</span>
                                    @php($terminalLabels = $this->rhTerminalOptions())
                                    <div
                                        class="erp-rh-oper__multi"
                                        x-data="{ open: false, q: '', labels: @js($terminalLabels) }"
                                        @click.outside="open = false"
                                        @keydown.escape.stop="open = false"
                                    >
                                        <button type="button" class="erp-rh-oper__multi-toggle" @click="open = !open">
                                            <span
                                                class="erp-rh-oper__multi-summary"
                                                :class="{ 'is-empty': !($wire.rhFuncionarioForm.terminais || []).length }"
                                                x-text="($wire.rhFuncionarioForm.terminais || []).length
                                                    ? $wire.rhFuncionarioForm.terminais.map(id => labels[id]).filter(Boolean).join(', ')
                                                    : 'Todos os PDVs (sem restrição)'"
                                            ></span>
                                            <span class="erp-rh-oper__multi-count" x-show="($wire.rhFuncionarioForm.terminais || []).length"
                                                x-text="($wire.rhFuncionarioForm.terminais || []).length"></span>
                                        </button>
                                        <div class="erp-rh-oper__multi-panel" x-show="open" x-cloak x-transition.opacity>
                                            @forelse ($terminalLabels as $id => $label)
                                                <label class="erp-rh-oper__check" x-show="q === '' || @js(mb_strtolower((string) $label, 'UTF-8')).includes(q.toLowerCase())">
                                                    <input type="checkbox" value="{{ $id }}" wire:model.live="rhFuncionarioForm.terminais">
                                                    {{ $label }}
                                                </label>
                                            @empty
                                                <span class="erp-rh-oper__empty">Nenhum terminal/PDV cadastrado.</span>
                                            @endforelse
                                        </div>
                                    </div>
                                    <p class="erp-rh-oper__hint">Marque 1 ou mais PDVs. Vazio = pode operar em qualquer terminal.</p>
                                </div>

                                <label class="erp-rh-oper__check erp-rh-oper__check--inline">
                                    <input type="checkbox" wire:model="rhFuncionarioForm.usar_agendamento">
                                    Usar agendamento
                                </label>

                                <fieldset class="erp-rh-oper__section">
                                    <legend>
                                        <label class="erp-rh-oper__check erp-rh-oper__check--inline">
                                            <input type="checkbox" wire:model="rhFuncionarioForm.setor_vendas">
                                            Setor de Vendas
                                        </label>
                                    </legend>
                                    <div class="erp-rh-func-modal__grid">
                                        <label class="erp-rh-func-modal__field">
                                            <span>Tabela Venda</span>
                                            <select wire:model="rhFuncionarioForm.tabela_venda_id" class="erp-rh-func-modal__input">
                                                @foreach ($this->rhTabelaVendaOptions() as $id => $nome)
                                                    <option value="{{ $id }}">{{ $nome }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="erp-rh-func-modal__field erp-rh-func-modal__field--sm">
                                            <span>Comissão AV %</span>
                                            <input type="text" wire:model.blur="rhFuncionarioForm.comissao_av" data-mask="percent-br" class="erp-rh-func-modal__input" inputmode="decimal">
                                        </label>
                                        <label class="erp-rh-func-modal__field erp-rh-func-modal__field--sm">
                                            <span>Comissão AP %</span>
                                            <input type="text" wire:model.blur="rhFuncionarioForm.comissao_ap" data-mask="percent-br" class="erp-rh-func-modal__input" inputmode="decimal">
                                        </label>
                                        <label class="erp-rh-func-modal__field erp-rh-func-modal__field--sm">
                                            <span>Meta Mensal R$</span>
                                            <input type="text" wire:model.blur="rhFuncionarioForm.mobile_meta_venda" data-mask="money-br" class="erp-rh-func-modal__input" inputmode="decimal">
                                        </label>
                                    </div>
                                    <label class="erp-rh-oper__check erp-rh-oper__check--inline">
                                        <input type="checkbox" wire:model="rhFuncionarioForm.ganha_comissao_todas_vendas">
                                        Ganha comissão sobre todas as vendas
                                    </label>
                                </fieldset>

                                <fieldset class="erp-rh-oper__section">
                                    <legend>
                                        <label class="erp-rh-oper__check erp-rh-oper__check--inline">
                                            <input type="checkbox" wire:model="rhFuncionarioForm.setor_servicos">
                                            Setor de Serviços
                                        </label>
                                    </legend>
                                    <div class="erp-rh-func-modal__grid">
                                        <label class="erp-rh-func-modal__field erp-rh-func-modal__field--sm">
                                            <span>Comissão Serv. %</span>
                                            <input type="text" wire:model.blur="rhFuncionarioForm.comissao_servico" data-mask="percent-br" class="erp-rh-func-modal__input" inputmode="decimal">
                                        </label>
                                    </div>
                                    <label class="erp-rh-oper__check erp-rh-oper__check--inline">
                                        <input type="checkbox" wire:model="rhFuncionarioForm.ganha_comissao_todos_servicos">
                                        Ganha comissão sobre todos os serviços
                                    </label>
                                </fieldset>

                                <label class="erp-rh-func-modal__field erp-rh-func-modal__field--full">
                                    <span>Observações (operador)</span>
                                    <textarea wire:model="rhFuncionarioForm.observacoes_operador" class="erp-rh-func-modal__input erp-rh-oper__textarea" rows="2" maxlength="500"></textarea>
                                </label>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-rh-func-modal__actions">
                <button type="button" wire:click="saveRhFuncionario" wire:loading.attr="disabled" wire:target="saveRhFuncionario" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="saveRhFuncionario"><kbd>F5</kbd> | Gravar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="saveRhFuncionario">Salvando…</span>
                </button>
                <button type="button" wire:click="closeRhFuncionarioModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
