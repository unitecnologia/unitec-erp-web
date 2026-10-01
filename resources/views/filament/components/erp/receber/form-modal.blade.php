{{-- Sempre no DOM: display (evita morph do Livewire reabrir o form após corrida). --}}
@php
    $editando = $this->contaFormRecordId !== null;
    $somenteVencimento = (bool) $this->contaFormSomenteVencimento;
    $formVisivel = (bool) $this->contaFormModalOpen;
    $roClass = 'erp-receber-form-modal__input--ro';
@endphp
<div
    id="erp-receber-form-modal"
    class="erp-receber-form-modal"
    role="dialog"
    aria-modal="{{ $formVisivel ? 'true' : 'false' }}"
    aria-hidden="{{ $formVisivel ? 'false' : 'true' }}"
    style="display: {{ $formVisivel ? 'flex' : 'none' }};"
    data-erp-conta-form-has-boleto="{{ filled($this->contaFormBoletoBancoNome) ? '1' : '0' }}"
    data-erp-conta-form-banco="{{ e($this->contaFormBoletoBancoNome !== '' ? $this->contaFormBoletoBancoNome : 'banco') }}"
    data-erp-conta-form-venc-original="{{ e($this->contaFormVencimentoOriginal) }}"
    x-data
    x-on:keydown.window="
        if (! $wire.contaFormModalOpen) return;
        if ($event.key === 'Escape') { $event.preventDefault(); $wire.handleContaFormEscape(); }
        if ($event.key === 'F5') { $event.preventDefault(); $wire.salvarContaForm(); }
    "
>
    <div class="erp-receber-form-modal__backdrop" wire:click="closeContaFormModal"></div>

    <div
        class="erp-receber-form-modal__dialog"
        aria-labelledby="erp-receber-form-modal-title"
        wire:click.stop
    >
        <header class="erp-receber-form-modal__titlebar">
            <span id="erp-receber-form-modal-title">
                @if ($somenteVencimento)
                    Alterar vencimento / tipo
                @elseif ($editando)
                    Alterar Conta a Receber
                @else
                    Lançamento de Contas a Receber
                @endif
            </span>
            <button
                type="button"
                class="erp-receber-form-modal__close"
                wire:click="closeContaFormModal"
                title="ESC | Sair"
                aria-label="Fechar"
            >&times;</button>
        </header>

        <div class="erp-receber-form-modal__body">
            @if ($somenteVencimento)
                <p class="erp-receber-form-modal__hint-lock">
                    Conta vinculada a pedido: só é permitido alterar <strong>Tipo</strong>, <strong>Vencimento</strong>
                    @if (mb_strtolower(trim($this->contaFormForma), 'UTF-8') === \App\Models\ContaReceber::FORMA_CARTEIRA)
                        e <strong>juros da carteira</strong>
                    @endif
                    .
                </p>
            @endif

            <div class="erp-receber-form-modal__grid">
            <label class="erp-receber-form-modal__field erp-receber-form-modal__field--sm">
                <span>Código</span>
                <input type="text" class="erp-receber-form-modal__input erp-receber-form-modal__input--ro" value="{{ $this->contaFormNumero }}" readonly disabled tabindex="-1">
            </label>

            <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                <span>Emissão</span>
                <input
                    type="date"
                    class="erp-receber-form-modal__input @if ($somenteVencimento) {{ $roClass }} @endif"
                    wire:model="contaFormEmissao"
                    @disabled($somenteVencimento)
                    @readonly($somenteVencimento)
                    @if ($somenteVencimento) tabindex="-1" @endif
                >
                @error('contaFormEmissao') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
            </label>

            <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                <span>Tipo</span>
                <select
                    class="erp-receber-form-modal__input"
                    wire:model.live="contaFormForma"
                    @if ($somenteVencimento) autofocus @endif
                >
                    @foreach (\App\Support\Erp\Financeiro\ContaReceberCadastroService::tiposAvulso() as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
                @error('contaFormForma') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
            </label>

            <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                <span>Documento</span>
                <input
                    type="text"
                    class="erp-receber-form-modal__input @if ($somenteVencimento) {{ $roClass }} @endif"
                    wire:model="contaFormDocumento"
                    maxlength="40"
                    @if (! $somenteVencimento) autofocus @endif
                    @disabled($somenteVencimento)
                    @readonly($somenteVencimento)
                    @if ($somenteVencimento) tabindex="-1" @endif
                >
                @error('contaFormDocumento') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
            </label>

            <label class="erp-receber-form-modal__field erp-receber-form-modal__field--wide">
                <span>Empresa</span>
                <input
                    type="text"
                    class="erp-receber-form-modal__input erp-receber-form-modal__input--ro"
                    value="{{ $this->contaFormEmpresa }}"
                    readonly
                    disabled
                    tabindex="-1"
                >
            </label>

            <label class="erp-receber-form-modal__field erp-receber-form-modal__field--wide">
                <span>Cliente</span>
                <div class="erp-receber-form-modal__cliente">
                    <input
                        type="text"
                        class="erp-receber-form-modal__input @if ($somenteVencimento) {{ $roClass }} @endif"
                        wire:model.live.debounce.250ms="contaFormClienteBusca"
                        @if (! $somenteVencimento)
                            wire:focus="openContaFormClienteLookup"
                            wire:keydown.arrow-up.prevent="moveContaFormClienteSelection(-1)"
                            wire:keydown.arrow-down.prevent="moveContaFormClienteSelection(1)"
                            wire:keydown.enter.prevent="handleContaFormClienteEnter"
                        @endif
                        placeholder="PESQUISAR CLIENTE"
                        autocomplete="off"
                        data-erp-uppercase
                        @disabled($somenteVencimento)
                        @readonly($somenteVencimento)
                        @if ($somenteVencimento) tabindex="-1" @endif
                    >
                    @if (! $somenteVencimento && $this->contaFormClienteLookupOpen && filled(trim($this->contaFormClienteBusca)))
                        @if ($this->contaFormClienteResults !== [])
                            <div class="erp-receber-form-modal__cliente-lookup">
                                <table class="erp-receber-form-modal__cliente-table">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Razão social</th>
                                            <th>CNPJ/CPF</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->contaFormClienteResults as $index => $row)
                                            <tr
                                                wire:key="conta-form-cliente-{{ $row['id'] }}"
                                                wire:click="highlightContaFormClienteResult({{ $index }})"
                                                wire:dblclick.prevent="selectContaFormClienteResult({{ $index }})"
                                                @class(['erp-receber-form-modal__cliente-row', 'erp-receber-form-modal__cliente-row--active' => $this->contaFormClienteIndex === $index])
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
                            <div class="erp-receber-form-modal__cliente-lookup erp-receber-form-modal__cliente-lookup--empty">
                                Nenhum cliente encontrado.
                            </div>
                        @endif
                    @endif
                    @error('contaFormClienteId') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
                </div>
            </label>

            <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                <span>Vencimento</span>
                <input
                    type="date"
                    class="erp-receber-form-modal__input"
                    wire:model="contaFormVencimento"
                >
                @error('contaFormVencimento') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
            </label>

            <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                <span>Valor</span>
                <input
                    type="text"
                    class="erp-receber-form-modal__input erp-receber-form-modal__input--money @if ($somenteVencimento) {{ $roClass }} @endif"
                    wire:model.blur="contaFormValor"
                    inputmode="decimal"
                    data-mask="money-br"
                    autocomplete="off"
                    @disabled($somenteVencimento)
                    @readonly($somenteVencimento)
                    @if ($somenteVencimento) tabindex="-1" @endif
                >
                @error('contaFormValor') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
            </label>

            <label class="erp-receber-form-modal__field erp-receber-form-modal__field--wide">
                <span>Histórico</span>
                <input
                    type="text"
                    class="erp-receber-form-modal__input @if ($somenteVencimento) {{ $roClass }} @endif"
                    wire:model="contaFormHistorico"
                    maxlength="500"
                    @disabled($somenteVencimento)
                    @readonly($somenteVencimento)
                    @if ($somenteVencimento) tabindex="-1" @endif
                >
                @error('contaFormHistorico') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
            </label>

            <label class="erp-receber-form-modal__field erp-receber-form-modal__field--wide">
                <span>Plano de contas</span>
                <select
                    class="erp-receber-form-modal__input @if ($editando || $somenteVencimento) {{ $roClass }} @endif"
                    wire:model="contaFormPlanoContaId"
                    @disabled($editando || $somenteVencimento)
                    @if ($editando || $somenteVencimento) tabindex="-1" @endif
                >
                    <option value="">—</option>
                    @foreach ($this->contaFormPlanosOptions as $plano)
                        <option value="{{ $plano['id'] }}">{{ $plano['label'] }}</option>
                    @endforeach
                </select>
                @error('contaFormPlanoContaId') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
            </label>

            <label class="erp-receber-form-modal__field erp-receber-form-modal__field--sm">
                <span>Repetir por</span>
                <input
                    type="number"
                    class="erp-receber-form-modal__input @if ($editando || $somenteVencimento) erp-receber-form-modal__input--ro @endif"
                    wire:model="contaFormParcelas"
                    min="1"
                    max="120"
                    step="1"
                    @disabled($editando || $somenteVencimento)
                    @readonly($editando || $somenteVencimento)
                    @if ($editando || $somenteVencimento) tabindex="-1" @endif
                >
                @error('contaFormParcelas') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
            </label>

            @if (mb_strtolower(trim($this->contaFormForma), 'UTF-8') === \App\Models\ContaReceber::FORMA_CARTEIRA)
                <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                    <span>Carência juros (dias)</span>
                    <input
                        type="number"
                        class="erp-receber-form-modal__input"
                        wire:model="contaFormCarenciaJurosDias"
                        min="0"
                        max="3650"
                        step="1"
                        title="Dias sem cobrar juros após o vencimento (padrão da Empresa)."
                    >
                    @error('contaFormCarenciaJurosDias') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                    <span>% multa atraso</span>
                    <input
                        type="text"
                        class="erp-receber-form-modal__input erp-receber-form-modal__input--money"
                        wire:model.blur="contaFormMultaPct"
                        inputmode="decimal"
                        data-mask="money-br"
                        autocomplete="off"
                        title="Percentual único de multa depois da carência. Não é cobrado por dia."
                    >
                    @error('contaFormMultaPct') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                    <span>% juros diário</span>
                    <input
                        type="text"
                        class="erp-receber-form-modal__input erp-receber-form-modal__input--money"
                        wire:model.blur="contaFormJurosDiarioPct"
                        inputmode="decimal"
                        data-mask="money-br"
                        autocomplete="off"
                        title="Percentual ao dia sobre o valor do título após a carência. Boleto usa juros próprios."
                    >
                    @error('contaFormJurosDiarioPct') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
                </label>
            @endif
            </div>
        </div>

        <footer class="erp-receber-form-modal__footer">
            <button
                type="button"
                class="erp-receber-form-modal__btn erp-receber-form-modal__btn--save"
                wire:click="salvarContaForm"
                wire:loading.attr="disabled"
                wire:target="salvarContaForm"
            >
                <kbd>F5</kbd> | Salvar
            </button>
            <button
                type="button"
                class="erp-receber-form-modal__btn erp-receber-form-modal__btn--exit"
                wire:click="closeContaFormModal"
            >
                <kbd>ESC</kbd> | Sair
            </button>
        </footer>
    </div>
</div>
