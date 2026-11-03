@if ($this->nfeDuplicarModalOpen)
    <div
        class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--warning"
        role="alertdialog"
        aria-labelledby="erp-nfe-duplicar-title"
        aria-modal="true"
        wire:keydown.escape.window="closeNfeDuplicarModal"
        wire:keydown.enter.window="confirmDuplicarNfe"
    >
        <div class="erp-nfe-fiscal-overlay__box">
            <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">⧉</div>

            <h2 id="erp-nfe-duplicar-title" class="erp-nfe-fiscal-overlay__title">
                DUPLICAR NF-e
            </h2>

            <p class="erp-nfe-fiscal-overlay__codigo">
                {{ $this->nfeDuplicarNumeroDetalhe }}
            </p>

            <div class="erp-nfe-fiscal-overlay__text">
                Será criada uma <strong>nova NF-e aberta</strong> com os mesmos
                produtos, valores, impostos e condições de pagamento.
                A nota original <strong>não será alterada</strong>.
            </div>

            <div class="erp-nfe-fiscal-overlay__actions">
                <button
                    type="button"
                    wire:click="confirmDuplicarNfe"
                    wire:loading.attr="disabled"
                    wire:target="confirmDuplicarNfe"
                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--confirm"
                    id="erp-nfe-duplicar-sim"
                >
                    <span wire:loading.remove wire:target="confirmDuplicarNfe">Sim, duplicar</span>
                    <span wire:loading wire:target="confirmDuplicarNfe">Duplicando…</span>
                </button>
                <button
                    type="button"
                    wire:click="closeNfeDuplicarModal"
                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"
                >Não</button>
            </div>

            <p class="erp-nfe-fiscal-overlay__hint">Enter confirma · Esc cancela</p>
        </div>
    </div>
@endif
