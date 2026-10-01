@if ($this->boletoContaPickOpen)
    <div
        class="erp-lookup-modal erp-receber-boleto-pick-modal"
        wire:key="boleto-pick-modal"
        wire:keydown.escape.window="closeBoletoContaPickModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeBoletoContaPickModal"></div>

        <div
            class="erp-lookup-modal__window erp-receber-boleto-pick-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-receber-boleto-pick-title"
            wire:click.stop
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-receber-boleto-pick-title">Escolher banco / conta</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeBoletoContaPickModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-receber-boleto-pick-modal__body">
                <p class="erp-receber-boleto-pick-modal__hint">
                    Após emitir, o banco fica travado neste boleto.
                </p>

                <label class="erp-receber-boleto-pick-modal__field">
                    <span class="erp-receber-boleto-pick-modal__label">Conta de cobrança</span>
                    <select
                        class="erp-receber-boleto-pick-modal__select"
                        wire:model="boletoContaPickSelectedId"
                        autofocus
                    >
                        @foreach ($this->boletoContaPickOptions as $opt)
                            <option value="{{ $opt['id'] }}">
                                {{ $opt['rotulo'] }}{{ ! empty($opt['padrao']) ? ' (padrão)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="erp-receber-boleto-pick-modal__footer">
                <button
                    type="button"
                    class="erp-receber-boleto-pick-modal__btn erp-receber-boleto-pick-modal__btn--cancel"
                    wire:click="closeBoletoContaPickModal"
                >Cancelar</button>
                <button
                    type="button"
                    class="erp-receber-boleto-pick-modal__btn erp-receber-boleto-pick-modal__btn--ok"
                    wire:click="confirmarGerarBoletoComConta"
                    wire:loading.attr="disabled"
                    wire:target="confirmarGerarBoletoComConta"
                >
                    <span wire:loading.remove wire:target="confirmarGerarBoletoComConta">Gerar boleto</span>
                    <span wire:loading wire:target="confirmarGerarBoletoComConta">Gerando…</span>
                </button>
            </div>
        </div>
    </div>
@endif
