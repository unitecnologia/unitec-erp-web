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
                <span id="erp-vendedor-form-title">
                    @if (\App\Support\Erp\ErpOnboarding::step() === \App\Support\Erp\ErpOnboarding::STEP_COLABORADOR)
                        Primeiro acesso — Cadastro de Operador
                    @else
                        {{ $this->vendedorModalRecordId ? 'Alterar Operador' : 'Novo Operador' }}
                    @endif
                </span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeVendedorModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-vendedor-form-modal__body">
                <div
                    class="erp-pcad-form erp-vendedor-form-modal__form"
                    data-erp-form
                    autocomplete="off"
                    x-data
                    x-on:input.capture="
                        const t = $event.target;
                        if (!t || t.disabled) return;
                        if (t.dataset.mask) return;
                        if (t.classList?.contains('erp-pcad-form__input--comissao')) return;
                        if (t.inputMode === 'decimal') return;
                        if (!(t.matches('input[type=text], input:not([type]), textarea'))) return;
                        const u = String(t.value || '').toLocaleUpperCase('pt-BR');
                        if (t.value === u) return;
                        const s = t.selectionStart, e = t.selectionEnd;
                        t.value = u;
                        if (document.activeElement === t && s != null && e != null) {
                            try { t.setSelectionRange(s, e); } catch (_) {}
                        }
                    "
                >

                    {{-- Identificação --}}
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend">Identificação</legend>
                        <div class="erp-vform__grid erp-vform__grid--ident">
                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--codigo">
                                <label class="erp-pcad-form__label" for="vendedor-codigo">Código</label>
                                <input id="vendedor-codigo" type="text" wire:model="vendedorForm.codigo"
                                    class="erp-pcad-form__input erp-pcad-form__input--xs erp-pcad-form__input--locked"
                                    style="text-transform: uppercase;"
                                    autocomplete="off"
                                    readonly
                                    tabindex="-1"
                                    title="Código gerado automaticamente">
                                @error('vendedorForm.codigo') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--ativo">
                                <label class="erp-pcad-form__label" for="vendedor-ativo">Ativo</label>
                                <select id="vendedor-ativo" wire:model="vendedorForm.ativo" class="erp-pcad-form__select erp-pcad-form__select--ativo">
                                    <option value="S">S</option>
                                    <option value="N">N</option>
                                </select>
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--full">
                                <label class="erp-pcad-form__label" for="vendedor-rh-funcionario">
                                    Funcionário <em class="erp-vform__req">*</em>
                                </label>
                                <div class="erp-vform__field-stack">
                                    <select
                                        id="vendedor-rh-funcionario"
                                        wire:model.live="vendedorForm.rh_funcionario_id"
                                        class="erp-pcad-form__select"
                                        autofocus
                                        required
                                    >
                                        <option value="">— selecione (RH → Funcionários) —</option>
                                        @foreach ($this->rhFuncionarioOptions() as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('vendedorForm.rh_funcionario_id') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                                    @error('vendedorForm.nome') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--half">
                                <label class="erp-pcad-form__label" for="vendedor-cargo">
                                    Cargo <em class="erp-vform__req">*</em>
                                </label>
                                <select id="vendedor-cargo" wire:model.live="vendedorForm.cargo" class="erp-pcad-form__select erp-pcad-form__input--grow" required>
                                    <option value="">— selecione (RH → Cargos) —</option>
                                    @foreach ($this->cargoOptions() as $cargoNome)
                                        <option value="{{ $cargoNome }}">{{ $cargoNome }}</option>
                                    @endforeach
                                </select>
                                @error('vendedorForm.cargo') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--half">
                                <label class="erp-pcad-form__label" for="vendedor-usuario">
                                    Usuário <em class="erp-vform__req">*</em>
                                </label>
                                <select id="vendedor-usuario" wire:model="vendedorForm.usuario_id" class="erp-pcad-form__select erp-pcad-form__input--grow" required>
                                    <option value="">— selecione —</option>
                                    @foreach ($this->usuarioOptions() as $id => $nome)
                                        <option value="{{ $id }}">{{ $nome }}</option>
                                    @endforeach
                                </select>
                                @error('vendedorForm.usuario_id') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--half erp-vform__cell--top">
                                <label class="erp-pcad-form__label">
                                    Empresas <em class="erp-vform__req">*</em>
                                </label>
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
                                                        <input type="checkbox" value="{{ $id }}" wire:model.live="vendedorForm.empresas">
                                                        <strong>{{ $empresaCodigos[$id] ?? '' }}</strong> {{ $nome }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="erp-vform__empresas-empty">Nenhuma empresa cadastrada.</span>
                                        @endif
                                    </div>
                                </div>
                                @error('vendedorForm.empresas')
                                    <span class="erp-vendedor-form-modal__error">{{ $message }}</span>
                                @enderror
                                @error('vendedorForm.empresas.*')
                                    <span class="erp-vendedor-form-modal__error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--half erp-vform__cell--top">
                                <label class="erp-pcad-form__label" for="vendedor-caixa">
                                    Caixa <em class="erp-vform__req">*</em>
                                </label>
                                @php($empresasSelecionadas = array_values(array_filter(array_map('intval', (array) ($this->vendedorForm['empresas'] ?? [])))))
                                @php($caixaOptions = (array) $this->caixaContaOptions())
                                @php($empresaNomes = (array) $this->empresaOptions())
                                @php($empresaCodigosCaixa = (array) $this->empresaCodigos())
                                <div class="erp-vform__field-stack">
                                    @if (count($empresasSelecionadas) === 0)
                                        <select id="vendedor-caixa" class="erp-pcad-form__select" disabled>
                                            <option value="">Selecione a empresa primeiro...</option>
                                        </select>
                                    @elseif (count($empresasSelecionadas) === 1)
                                        @php($empresaIdUnica = $empresasSelecionadas[0])
                                        <select
                                            id="vendedor-caixa"
                                            wire:model="vendedorForm.caixas_por_empresa.{{ $empresaIdUnica }}"
                                            class="erp-pcad-form__select"
                                            required
                                        >
                                            <option value="">— selecione —</option>
                                            @foreach ($caixaOptions as $caixaId => $caixaLabel)
                                                <option value="{{ $caixaId }}">{{ $caixaLabel }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <div class="erp-vform__caixas-por-empresa">
                                            @foreach ($empresasSelecionadas as $empresaId)
                                                <div class="erp-vform__caixa-empresa-row">
                                                    <span class="erp-vform__caixa-empresa-badge" title="{{ $empresaNomes[$empresaId] ?? '' }}">
                                                        Emp. {{ $empresaCodigosCaixa[$empresaId] ?? $empresaId }}
                                                    </span>
                                                    <select
                                                        wire:model="vendedorForm.caixas_por_empresa.{{ $empresaId }}"
                                                        class="erp-pcad-form__select"
                                                        required
                                                    >
                                                        <option value="">— selecione —</option>
                                                        @foreach ($caixaOptions as $caixaId => $caixaLabel)
                                                            <option value="{{ $caixaId }}">{{ $caixaLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    @error('vendedorForm.caixas_por_empresa')
                                        <span class="erp-vendedor-form-modal__error">{{ $message }}</span>
                                    @enderror
                                    @error('vendedorForm.caixas_por_empresa.*')
                                        <span class="erp-vendedor-form-modal__error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--full erp-vform__cell--top">
                                <label class="erp-pcad-form__label">PDVs liberados</label>
                                @php($terminalLabels = $this->terminalOptions())
                                <div class="erp-vform__field-stack">
                                    <div
                                        class="erp-vform__multi"
                                        x-data="{ open: false, q: '', labels: @js($terminalLabels) }"
                                        @click.outside="open = false"
                                        @keydown.escape.stop="open = false"
                                    >
                                        <button type="button" class="erp-vform__multi-toggle" @click="open = !open">
                                            <span
                                                class="erp-vform__multi-summary"
                                                :class="{ 'erp-vform__multi-summary--empty': !($wire.vendedorForm.terminais || []).length }"
                                                x-text="($wire.vendedorForm.terminais || []).length
                                                    ? $wire.vendedorForm.terminais.map(id => labels[id]).filter(Boolean).join(', ')
                                                    : 'Todos os PDVs (sem restrição)'"
                                            ></span>
                                            <span class="erp-vform__multi-count" x-show="($wire.vendedorForm.terminais || []).length"
                                                x-text="($wire.vendedorForm.terminais || []).length"></span>
                                            <svg class="erp-vform__multi-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :style="open ? 'transform:rotate(180deg)' : ''" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                        </button>

                                        <div class="erp-vform__multi-panel" x-show="open" x-cloak x-transition.opacity>
                                            @if (count($terminalLabels))
                                                <input type="text" class="erp-vform__multi-search" placeholder="Pesquisar PDV..." x-model="q" @click.stop>
                                                <div class="erp-vform__multi-list">
                                                    @foreach ($terminalLabels as $id => $label)
                                                        <label
                                                            class="erp-vform__check"
                                                            x-show="q === '' || @js(mb_strtolower((string) $label, 'UTF-8')).includes(q.toLowerCase())"
                                                        >
                                                            <input type="checkbox" value="{{ $id }}" wire:model.live="vendedorForm.terminais">
                                                            {{ $label }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="erp-vform__empresas-empty">Nenhum terminal/PDV cadastrado.</span>
                                            @endif
                                        </div>
                                    </div>
                                    <p class="erp-vform__hint">Marque 1 ou mais PDVs. Vazio = pode operar em qualquer terminal.</p>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend">Operação</legend>
                        <div class="erp-vform__grid">
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-estoque">
                                    Estoque <em class="erp-vform__req">*</em>
                                </label>
                                <select id="vendedor-estoque" wire:model="vendedorForm.estoque_id" class="erp-pcad-form__select erp-pcad-form__input--grow" required>
                                    <option value="">— selecione —</option>
                                    @foreach ($this->estoqueOptions() as $id => $nome)
                                        <option value="{{ $id }}">{{ $nome }}</option>
                                    @endforeach
                                </select>
                                @error('vendedorForm.estoque_id') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-vform__check">
                                    <input type="checkbox" wire:model="vendedorForm.usar_agendamento"> Usar agendamento
                                </label>
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--full">
                                <p class="erp-vform__hint" style="margin:0;font-size:0.72rem;color:#64748b;">
                                    Dados pessoais, endereço, contato e trabalhistas ficam em <strong>RH → Funcionários</strong>.
                                    Selecione o funcionário acima — o nome na lista de Operadores vem dele.
                                </p>
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
                                    @foreach ($this->tabelaVendaOptions() as $id => $nome)
                                        <option value="{{ $id }}">{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-comissao-av">Comissão AV</label>
                                <div class="erp-vform__affix">
                                    <input
                                        id="vendedor-comissao-av"
                                        type="text"
                                        wire:model.blur="vendedorForm.comissao_av"
                                        data-mask="percent-br"
                                        class="erp-pcad-form__input erp-pcad-form__input--comissao erp-vform__affix-input"
                                        autocomplete="off"
                                        inputmode="decimal"
                                    >
                                    <span class="erp-vform__affix-unit erp-vform__affix-unit--suffix">%</span>
                                </div>
                                @error('vendedorForm.comissao_av') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-comissao-ap">Comissão AP</label>
                                <div class="erp-vform__affix">
                                    <input
                                        id="vendedor-comissao-ap"
                                        type="text"
                                        wire:model.blur="vendedorForm.comissao_ap"
                                        data-mask="percent-br"
                                        class="erp-pcad-form__input erp-pcad-form__input--comissao erp-vform__affix-input"
                                        autocomplete="off"
                                        inputmode="decimal"
                                    >
                                    <span class="erp-vform__affix-unit erp-vform__affix-unit--suffix">%</span>
                                </div>
                                @error('vendedorForm.comissao_ap') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell">
                                <label class="erp-pcad-form__label" for="vendedor-meta" title="Meta mensal em R$ — preenchida aparece no dashboard">Meta Mensal</label>
                                <div class="erp-vform__affix">
                                    <span class="erp-vform__affix-unit erp-vform__affix-unit--prefix">R$</span>
                                    <input
                                        id="vendedor-meta"
                                        type="text"
                                        wire:model.blur="vendedorForm.mobile_meta_venda"
                                        data-mask="money-br"
                                        class="erp-pcad-form__input erp-pcad-form__input--comissao erp-vform__affix-input"
                                        autocomplete="off"
                                        inputmode="decimal"
                                        title="Preencha para exibir a meta no dashboard (empresa e app). Zero ou vazio oculta."
                                    >
                                </div>
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
                                <div class="erp-vform__affix">
                                    <input
                                        id="vendedor-comissao-serv"
                                        type="text"
                                        wire:model.blur="vendedorForm.comissao_servico"
                                        data-mask="percent-br"
                                        class="erp-pcad-form__input erp-pcad-form__input--comissao erp-vform__affix-input"
                                        autocomplete="off"
                                        inputmode="decimal"
                                    >
                                    <span class="erp-vform__affix-unit erp-vform__affix-unit--suffix">%</span>
                                </div>
                                @error('vendedorForm.comissao_servico') <span class="erp-vendedor-form-modal__error">{{ $message }}</span> @enderror
                            </div>
                            <div class="erp-pcad-form__row erp-vform__cell erp-vform__cell--full">
                                <label class="erp-vform__check">
                                    <input type="checkbox" wire:model="vendedorForm.ganha_comissao_todos_servicos"> Ganha comissão sobre todos os serviços
                                </label>
                            </div>
                        </div>
                    </fieldset>

                    {{-- Observações --}}
                    <fieldset class="erp-vform__section">
                        <legend class="erp-vform__legend">Observações</legend>
                        <textarea wire:model.live="vendedorForm.observacoes" rows="1" class="erp-pcad-form__input erp-vform__textarea" autocomplete="off"></textarea>
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
