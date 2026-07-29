@if ($this->baixaModalOpen)
    <div
        class="erp-lookup-modal erp-receber-baixa-modal"
        wire:keydown.escape.window="closeBaixaModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeBaixaModal"></div>

        <div
            class="erp-lookup-modal__window erp-receber-baixa-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-receber-baixa-modal-title"
            wire:click.stop
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-receber-baixa-modal-title">Baixar conta a receber</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeBaixaModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-receber-baixa-modal__body">
                <div class="erp-receber-baixa-modal__resumo">
                    <div class="erp-receber-baixa-modal__resumo-item">
                        <span class="erp-receber-baixa-modal__label">Quantidade</span>
                        <strong class="erp-receber-baixa-modal__value">{{ $this->baixaResumoQtd }}</strong>
                    </div>
                    <div class="erp-receber-baixa-modal__resumo-item">
                        <span class="erp-receber-baixa-modal__label">Total a receber</span>
                        <strong class="erp-receber-baixa-modal__value erp-receber-baixa-modal__value--money">
                            R$ {{ $this->baixaResumoTotal }}
                        </strong>
                    </div>
                </div>

                <label class="erp-receber-baixa-modal__field">
                    <span class="erp-receber-baixa-modal__label">Meio de pagamento</span>
                    <select
                        class="erp-receber-baixa-modal__select"
                        wire:model.live="baixaFormaPagamentoId"
                        autofocus
                    >
                        @foreach ($this->baixaFormasOptions as $forma)
                            <option value="{{ $forma['id'] }}">{{ $forma['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <p class="erp-receber-baixa-modal__hint">
                    A baixa usa o saldo em aberto de cada título selecionado.
                </p>
            </div>

            <div class="erp-receber-baixa-modal__footer">
                <button
                    type="button"
                    class="erp-receber-baixa-modal__btn erp-receber-baixa-modal__btn--cancel"
                    wire:click="closeBaixaModal"
                >Cancelar</button>
                <button
                    type="button"
                    class="erp-receber-baixa-modal__btn erp-receber-baixa-modal__btn--ok"
                    wire:click="confirmarBaixaConta"
                    wire:loading.attr="disabled"
                >OK</button>
            </div>
        </div>
    </div>
@endif
