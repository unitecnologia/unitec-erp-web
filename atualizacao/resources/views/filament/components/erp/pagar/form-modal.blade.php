@if ($this->contaFormModalOpen)
    @php $editando = $this->contaFormRecordId !== null; @endphp
    <div
        class="erp-pagar-form-modal"
        x-data
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.closeContaFormModal(); }
            if ($event.key === 'F5') { $event.preventDefault(); $wire.salvarContaForm(); }
        "
    >
        <div class="erp-pagar-form-modal__backdrop" wire:click="closeContaFormModal"></div>

        <div
            class="erp-pagar-form-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-pagar-form-modal-title"
            wire:click.stop
        >
            <header class="erp-pagar-form-modal__titlebar">
                <span id="erp-pagar-form-modal-title">
                    {{ $editando ? 'Alterar Conta a Pagar' : 'Cadastro de Contas a Pagar' }}
                </span>
                <button
                    type="button"
                    class="erp-pagar-form-modal__close"
                    wire:click="closeContaFormModal"
                    title="ESC | Sair"
                    aria-label="Fechar"
                >&times;</button>
            </header>

            <div class="erp-pagar-form-modal__body">
                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--sm">
                    <span>Código</span>
                    <input type="text" class="erp-pagar-form-modal__input erp-pagar-form-modal__input--ro" value="{{ $this->contaFormNumero }}" readonly tabindex="-1">
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--md">
                    <span>Emissão</span>
                    <input type="date" class="erp-pagar-form-modal__input" wire:model="contaFormEmissao">
                    @error('contaFormEmissao') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--md">
                    <span>Documento</span>
                    <input type="text" class="erp-pagar-form-modal__input" wire:model="contaFormDocumento" maxlength="40" autofocus>
                    @error('contaFormDocumento') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field">
                    <span>Empresa</span>
                    <input type="text" class="erp-pagar-form-modal__input erp-pagar-form-modal__input--ro" value="{{ $this->contaFormEmpresa }}" readonly tabindex="-1">
                </label>

                <label class="erp-pagar-form-modal__field">
                    <span>Fornecedor</span>
                    <select class="erp-pagar-form-modal__input" wire:model="contaFormFornecedorId">
                        <option value="">— Selecione —</option>
                        @foreach ($this->fornecedoresOptions as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                    @error('contaFormFornecedorId') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--md">
                    <span>Vencimento</span>
                    <input type="date" class="erp-pagar-form-modal__input" wire:model="contaFormVencimento">
                    @error('contaFormVencimento') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field">
                    <span>Histórico</span>
                    <input type="text" class="erp-pagar-form-modal__input" wire:model="contaFormHistorico" maxlength="500">
                    @error('contaFormHistorico') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--md">
                    <span>Valor</span>
                    <input type="text" class="erp-pagar-form-modal__input erp-pagar-form-modal__input--money" wire:model.blur="contaFormValor" inputmode="decimal">
                    @error('contaFormValor') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>

                <label class="erp-pagar-form-modal__field erp-pagar-form-modal__field--sm">
                    <span>Nº Parcelas</span>
                    <input
                        type="number"
                        class="erp-pagar-form-modal__input @if ($editando) erp-pagar-form-modal__input--ro @endif"
                        wire:model="contaFormParcelas"
                        min="1"
                        max="120"
                        step="1"
                        @disabled($editando)
                        @if ($editando) tabindex="-1" @endif
                    >
                    @error('contaFormParcelas') <em class="erp-pagar-form-modal__error">{{ $message }}</em> @enderror
                </label>
            </div>

            <footer class="erp-pagar-form-modal__footer">
                <button
                    type="button"
                    class="erp-pagar-form-modal__btn erp-pagar-form-modal__btn--save"
                    wire:click="salvarContaForm"
                    wire:loading.attr="disabled"
                >
                    <kbd>F5</kbd> | Salvar
                </button>
                <button
                    type="button"
                    class="erp-pagar-form-modal__btn erp-pagar-form-modal__btn--exit"
                    wire:click="closeContaFormModal"
                >
                    <kbd>ESC</kbd> | Sair
                </button>
            </footer>
        </div>
    </div>
@endif
