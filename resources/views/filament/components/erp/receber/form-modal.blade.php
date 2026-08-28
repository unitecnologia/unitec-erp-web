@if ($this->contaFormModalOpen)
    @php $editando = $this->contaFormRecordId !== null; @endphp
    <div
        class="erp-receber-form-modal"
        x-data
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.handleContaFormEscape(); }
            if ($event.key === 'F5') { $event.preventDefault(); $wire.salvarContaForm(); }
        "
    >
        <div class="erp-receber-form-modal__backdrop" wire:click="closeContaFormModal"></div>

        <div
            class="erp-receber-form-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-receber-form-modal-title"
            wire:click.stop
        >
            <header class="erp-receber-form-modal__titlebar">
                <span id="erp-receber-form-modal-title">
                    {{ $editando ? 'Alterar Conta a Receber' : 'Lançamento de Contas a Receber' }}
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
                <label class="erp-receber-form-modal__field erp-receber-form-modal__field--sm">
                    <span>Código</span>
                    <input type="text" class="erp-receber-form-modal__input erp-receber-form-modal__input--ro" value="{{ $this->contaFormNumero }}" readonly tabindex="-1">
                </label>

                <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                    <span>Emissão</span>
                    <input type="date" class="erp-receber-form-modal__input" wire:model="contaFormEmissao">
                    @error('contaFormEmissao') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                    <span>Tipo</span>
                    <select class="erp-receber-form-modal__input" wire:model="contaFormForma">
                        @foreach (\App\Support\Erp\Financeiro\ContaReceberCadastroService::tiposAvulso() as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                    @error('contaFormForma') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                    <span>Documento</span>
                    <input type="text" class="erp-receber-form-modal__input" wire:model="contaFormDocumento" maxlength="40" autofocus>
                    @error('contaFormDocumento') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-receber-form-modal__field">
                    <span>Empresa</span>
                    <input type="text" class="erp-receber-form-modal__input erp-receber-form-modal__input--ro" value="{{ $this->contaFormEmpresa }}" readonly tabindex="-1">
                </label>

                <label class="erp-receber-form-modal__field">
                    <span>Cliente</span>
                    <div class="erp-receber-form-modal__cliente">
                        <input
                            type="text"
                            class="erp-receber-form-modal__input"
                            wire:model.live.debounce.250ms="contaFormClienteBusca"
                            wire:focus="openContaFormClienteLookup"
                            wire:keydown.arrow-up.prevent="moveContaFormClienteSelection(-1)"
                            wire:keydown.arrow-down.prevent="moveContaFormClienteSelection(1)"
                            wire:keydown.enter.prevent="handleContaFormClienteEnter"
                            placeholder="PESQUISAR CLIENTE"
                            autocomplete="off"
                            data-erp-uppercase
                        >
                        @if ($this->contaFormClienteLookupOpen && filled(trim($this->contaFormClienteBusca)))
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
                    <input type="date" class="erp-receber-form-modal__input" wire:model="contaFormVencimento">
                    @error('contaFormVencimento') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-receber-form-modal__field">
                    <span>Histórico</span>
                    <input type="text" class="erp-receber-form-modal__input" wire:model="contaFormHistorico" maxlength="500">
                    @error('contaFormHistorico') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-receber-form-modal__field erp-receber-form-modal__field--md">
                    <span>Valor</span>
                    <input type="text" class="erp-receber-form-modal__input erp-receber-form-modal__input--money" wire:model.blur="contaFormValor" inputmode="decimal" data-mask="money-br" autocomplete="off">
                    @error('contaFormValor') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-receber-form-modal__field erp-receber-form-modal__field--sm">
                    <span>Repetir por</span>
                    <input
                        type="number"
                        class="erp-receber-form-modal__input @if ($editando) erp-receber-form-modal__input--ro @endif"
                        wire:model="contaFormParcelas"
                        min="1"
                        max="120"
                        step="1"
                        @disabled($editando)
                        @if ($editando) tabindex="-1" @endif
                    >
                    @error('contaFormParcelas') <em class="erp-receber-form-modal__error">{{ $message }}</em> @enderror
                </label>
            </div>

            <footer class="erp-receber-form-modal__footer">
                <button
                    type="button"
                    class="erp-receber-form-modal__btn erp-receber-form-modal__btn--save"
                    wire:click="salvarContaForm"
                    wire:loading.attr="disabled"
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
@endif
