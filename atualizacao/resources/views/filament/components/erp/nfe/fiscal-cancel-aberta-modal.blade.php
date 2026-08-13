@if ($this->nfeCancelAbertaModalOpen)
    <div
        class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--warning"
        role="alertdialog"
        aria-labelledby="erp-nfe-cancel-aberta-title"
        aria-modal="true"
        wire:keydown.escape.window="closeNfeCancelAbertaModal"
        wire:keydown.enter.window="confirmCancelarNfeAberta"
    >
        <div class="erp-nfe-fiscal-overlay__box">
            <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>

            <h2 id="erp-nfe-cancel-aberta-title" class="erp-nfe-fiscal-overlay__title">
                CANCELAR NOTA ABERTA
            </h2>

            <p class="erp-nfe-fiscal-overlay__codigo">
                {{ $this->nfeCancelAbertaNumeroDetalhe }}
            </p>

            <div class="erp-nfe-fiscal-overlay__text">
                Ao confirmar, o sistema vai <strong>zerar todos os itens</strong>
                e <strong>inutilizar o número</strong> na SEFAZ.
                Esta ação não pode ser desfeita.
            </div>

            <div class="erp-nfe-fiscal-overlay__actions">
                <button
                    type="button"
                    wire:click="confirmCancelarNfeAberta"
                    wire:loading.attr="disabled"
                    wire:target="confirmCancelarNfeAberta"
                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--confirm"
                    id="erp-nfe-cancel-aberta-sim"
                >
                    <span wire:loading.remove wire:target="confirmCancelarNfeAberta">Sim, confirmar</span>
                    <span wire:loading wire:target="confirmCancelarNfeAberta">Processando…</span>
                </button>
                <button
                    type="button"
                    wire:click="closeNfeCancelAbertaModal"
                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"
                >Não</button>
            </div>

            <p class="erp-nfe-fiscal-overlay__hint">Enter confirma · Esc cancela</p>
        </div>
    </div>
@endif
