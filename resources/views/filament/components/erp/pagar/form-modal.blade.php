@php
    $editando = $this->contaFormRecordId !== null;
    $formVisivel = (bool) $this->contaFormModalOpen;
    $mostrarParcelas = ! $editando && (int) $this->contaFormParcelas > 1;
@endphp
{{-- Sempre no DOM (display): evita morph reabrir após corrida Livewire. --}}
<div
    id="erp-pagar-form-modal"
    class="erp-pagar-form-modal"
    role="dialog"
    aria-modal="{{ $formVisivel ? 'true' : 'false' }}"
    aria-hidden="{{ $formVisivel ? 'false' : 'true' }}"
    style="display: {{ $formVisivel ? 'flex' : 'none' }};"
    x-data="{ saving: false }"
    x-on:keydown.window="
        if (! $wire.contaFormModalOpen) return;
        if ($event.key === 'Escape') {
            $event.preventDefault();
            if (saving || $wire.contaFormSalvando || $wire.fornecedorRapidoSalvando) return;
            $wire.handleContaFormEscape();
            return;
        }
        if ($wire.fornecedorRapidoModalOpen) return;
        if ($event.key === 'F5') {
            $event.preventDefault();
            if ($event.repeat || saving || $wire.contaFormSalvando) return;
            saving = true;
            Promise.resolve($wire.salvarContaForm()).finally(() => { saving = false });
        }
    "
    x-on:erp-pagar-form-focus.window="$nextTick(() => {
        const el = document.getElementById('erp-pagar-form-documento');
        if (el) { el.focus(); el.select && el.select(); }
    })"
>
    <div class="erp-pagar-form-modal__backdrop" wire:click="closeContaFormModal"></div>

    <div
        class="erp-pagar-form-modal__dialog"
        aria-labelledby="erp-pagar-form-modal-title"
        wire:click.stop
    >
        <header class="erp-pagar-form-modal__titlebar">
            <span id="erp-pagar-form-modal-title">
                @if ($this->contaFormSomenteDre)
                    Competência DRE (conta baixada)
                @elseif ($editando)
                    Alterar Conta a Pagar
                @else
                    Cadastro de Contas a Pagar
                @endif
            </span>
            <button
                type="button"
                class="erp-pagar-form-modal__close"
                wire:click="closeContaFormModal"
                x-bind:disabled="saving"
                wire:loading.attr="disabled"
                wire:target="salvarContaForm,salvarContaFormENovo"
                title="ESC | Sair"
                aria-label="Fechar"
            >&times;</button>
        </header>

        <div class="erp-pagar-form-modal__body">
            <div class="erp-pagar-form-modal__grid">
                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--sm">
                    <span>Código</span>
                    <input type="text" class="erp-pagar-form-modal__input erp-pagar-form-modal__input--ro" value="{{ $this->contaFormNumero }}" readonly tabindex="-1">
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--md">
                    <span>Emissão</span>
                    <input type="date" class="erp-pagar-form-modal__input" wire:model="contaFormEmissao" @disabled($this->contaFormSomenteDre)>
                    @error('contaFormEmissao') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--md">
                    <span>Documento</span>
                    <input
                        id="erp-pagar-form-documento"
                        type="text"
                        class="erp-pagar-form-modal__input"
                        wire:model="contaFormDocumento"
                        maxlength="40"
                        autofocus
                        @disabled($this->contaFormSomenteDre)
                    >
                    @error('contaFormDocumento') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--wide">
                    <span>Empresa</span>
                    <input type="text" class="erp-pagar-form-modal__input erp-pagar-form-modal__input--ro" value="{{ $this->contaFormEmpresa }}" readonly tabindex="-1">
                </label>

                <div class="erp-pagar-form-modal__field erp-pagar-form-modal__field--wide">
                    <span>Fornecedor</span>
                    <div class="erp-pagar-form-modal__fornecedor">
                        <div class="erp-pagar-form-modal__fornecedor-row">
                            <input
                                type="text"
                                class="erp-pagar-form-modal__input"
                                wire:model.live.debounce.250ms="contaFormFornecedorBusca"
                                wire:focus="openContaFormFornecedorLookup"
                                wire:keydown.arrow-up.prevent="moveContaFormFornecedorSelection(-1)"
                                wire:keydown.arrow-down.prevent="moveContaFormFornecedorSelection(1)"
                                wire:keydown.enter.prevent="handleContaFormFornecedorEnter"
                                placeholder="PESQUISAR POR NOME OU DOCUMENTO"
                                autocomplete="off"
                                data-erp-uppercase
                                data-erp-pagar-fornecedor-busca
                                @disabled($this->contaFormSomenteDre)
                            >
                            @if (! $editando)
                                <button
                                    type="button"
                                    class="erp-pagar-form-modal__plus"
                                    wire:click="openFornecedorRapidoModal"
                                    title="Novo fornecedor"
                                    aria-label="Novo fornecedor"
                                >+</button>
                            @endif
                        </div>

                        @if ($this->contaFormFornecedorLookupOpen && filled(trim($this->contaFormFornecedorBusca)))
                            @if ($this->contaFormFornecedorResults !== [])
                                <div class="erp-pagar-form-modal__fornecedor-lookup">
                                    <table class="erp-pagar-form-modal__fornecedor-table">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Razão social</th>
                                                <th>CNPJ/CPF</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($this->contaFormFornecedorResults as $index => $row)
                                                <tr
                                                    wire:key="conta-form-forn-{{ $row['id'] }}"
                                                    wire:click="highlightContaFormFornecedorResult({{ $index }})"
                                                    wire:dblclick.prevent="selectContaFormFornecedorResult({{ $index }})"
                                                    @class(['erp-pagar-form-modal__fornecedor-row-item', 'erp-pagar-form-modal__fornecedor-row-item--active' => $this->contaFormFornecedorIndex === $index])
                                                >
                                                    <td>{{ $row['codigo'] !== '' ? $row['codigo'] : '—' }}</td>
                                                    <td>{{ $row['nome'] }}</td>
                                                    <td>{{ $row['cpf_cnpj'] !== '' ? $row['cpf_cnpj'] : '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="erp-pagar-form-modal__fornecedor-lookup erp-pagar-form-modal__fornecedor-lookup--empty">
                                    Nenhum fornecedor encontrado.
                                </div>
                            @endif
                        @endif
                        @error('contaFormFornecedorId') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                    </div>
                </div>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--md">
                    <span>Vencimento</span>
                    <input
                        type="date"
                        class="erp-pagar-form-modal__input"
                        wire:model.live="contaFormVencimento"
                        @disabled($this->contaFormSomenteDre)
                    >
                    @error('contaFormVencimento') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--md">
                    <span>Competência (DRE)</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        maxlength="7"
                        placeholder="MM/AAAA"
                        data-mask="month-year"
                        class="erp-pagar-form-modal__input erp-pagar-form-modal__input--competencia"
                        wire:model.blur="contaFormCompetencia"
                        title="Mês/ano da competência (MM/AAAA). Digite 112026 ou 11/2026."
                        autocomplete="off"
                    >
                    @error('contaFormCompetencia') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--md">
                    <span>Plano DRE</span>
                    <select class="erp-pagar-form-modal__input" wire:model="contaFormPlanoContaId">
                        <option value="">— não informado —</option>
                        @foreach ($this->contaFormPlanosOptions() as $planoOpt)
                            <option value="{{ $planoOpt['id'] }}">{{ $planoOpt['label'] }}</option>
                        @endforeach
                    </select>
                    @error('contaFormPlanoContaId') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--md">
                    <span>Valor</span>
                    <input
                        type="text"
                        class="erp-pagar-form-modal__input erp-pagar-form-modal__input--money"
                        wire:model.blur="contaFormValor"
                        inputmode="decimal"
                        data-mask="money-br"
                        autocomplete="off"
                        @disabled($this->contaFormSomenteDre)
                    >
                    @error('contaFormValor') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--wide">
                    <span>Histórico</span>
                    <input type="text" class="erp-pagar-form-modal__input" wire:model="contaFormHistorico" maxlength="500" data-erp-uppercase" @disabled($this->contaFormSomenteDre)>
                    @error('contaFormHistorico') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                @if ($this->contaFormSomenteDre)
                    <p class="erp-pagar-form-modal__field erp-pagar-form-modal__field--wide" style="margin:0;font-size:0.75rem;color:#64748b;">
                        Conta com baixa: apenas competência e plano DRE podem ser alterados. Valores financeiros permanecem inalterados.
                    </p>
                @endif

                @if (! $editando)
                    <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--sm">
                        <span>Nº Parcelas</span>
                        <input
                            type="number"
                            class="erp-pagar-form-modal__input"
                            wire:model.live="contaFormParcelas"
                            min="1"
                            max="120"
                            step="1"
                        >
                        @error('contaFormParcelas') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                    </label>

                    <div class="erp-pagar-form-modal__field erp-pagar-form-modal__field--md">
                        <span>Intervalo</span>
                        <div class="erp-pagar-form-modal__intervalo">
                            <input
                                type="number"
                                class="erp-pagar-form-modal__input erp-pagar-form-modal__input--intervalo"
                                wire:model.live="contaFormIntervalo"
                                min="1"
                                max="3650"
                                step="1"
                                @disabled((int) $this->contaFormParcelas <= 1)
                            >
                            <select
                                class="erp-pagar-form-modal__input erp-pagar-form-modal__input--unidade"
                                wire:model.live="contaFormIntervaloUnidade"
                                @disabled((int) $this->contaFormParcelas <= 1)
                            >
                                <option value="meses">Meses</option>
                                <option value="dias">Dias</option>
                            </select>
                        </div>
                        @error('contaFormIntervalo') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                        @error('contaFormIntervaloUnidade') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                    </div>
                @endif
            </div>

            @if ($mostrarParcelas && $this->contaFormParcelasPreview !== [])
                <div class="erp-pagar-form-modal__preview">
                    <div class="erp-pagar-form-modal__preview-title">Prévia das parcelas</div>
                    <table class="erp-pagar-form-modal__preview-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Vencimento</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->contaFormParcelasPreview as $index => $parcela)
                                <tr wire:key="pagar-parcela-preview-{{ $index }}">
                                    <td>{{ $parcela['n'] }}</td>
                                    <td>
                                        <input
                                            type="date"
                                            class="erp-pagar-form-modal__input"
                                            wire:model.live="contaFormParcelasPreview.{{ $index }}.vencimento"
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            class="erp-pagar-form-modal__input erp-pagar-form-modal__input--money"
                                            wire:model.blur="contaFormParcelasPreview.{{ $index }}.valor_br"
                                            inputmode="decimal"
                                            data-mask="money-br"
                                            autocomplete="off"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <footer class="erp-pagar-form-modal__footer">
            <button
                type="button"
                class="erp-pagar-form-modal__btn erp-pagar-form-modal__btn--save"
                x-bind:disabled="saving"
                wire:loading.attr="disabled"
                wire:target="salvarContaForm,salvarContaFormENovo"
                x-on:click="
                    if (saving || $wire.contaFormSalvando || $wire.fornecedorRapidoModalOpen) return;
                    saving = true;
                    Promise.resolve($wire.salvarContaForm()).finally(() => { saving = false });
                "
            >
                <kbd>F5</kbd> | Salvar
            </button>

            @if (! $editando)
                <button
                    type="button"
                    class="erp-pagar-form-modal__btn erp-pagar-form-modal__btn--save-new"
                    x-bind:disabled="saving"
                    wire:loading.attr="disabled"
                    wire:target="salvarContaForm,salvarContaFormENovo"
                    x-on:click="
                        if (saving || $wire.contaFormSalvando || $wire.fornecedorRapidoModalOpen) return;
                        saving = true;
                        Promise.resolve($wire.salvarContaFormENovo()).finally(() => { saving = false });
                    "
                >
                    Salvar e Novo
                </button>
            @endif

            <button
                type="button"
                class="erp-pagar-form-modal__btn erp-pagar-form-modal__btn--exit"
                wire:click="closeContaFormModal"
                x-bind:disabled="saving"
                wire:loading.attr="disabled"
                wire:target="salvarContaForm,salvarContaFormENovo"
            >
                <kbd>ESC</kbd> | Sair
            </button>
        </footer>
    </div>

    @include('filament.components.erp.pagar.fornecedor-rapido-modal')
</div>
