@if (filled($this->nfeInutilizarSucessoDetalhe) && ! $this->nfeInutilizarModalOpen)
    <div
        class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--sucesso"
        role="alertdialog"
        aria-labelledby="erp-nfe-inutilizar-sucesso-title"
        aria-live="polite"
    >
        <div class="erp-nfe-fiscal-overlay__box">
            <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">✓</div>

            <h2 id="erp-nfe-inutilizar-sucesso-title" class="erp-nfe-fiscal-overlay__title">
                {{ \App\Support\Erp\Nfe\NfeInutilizacaoMotivo::TITULO_SUCESSO }}
            </h2>

            <p class="erp-nfe-fiscal-overlay__codigo">{!! nl2br(e($this->nfeInutilizarSucessoDetalhe)) !!}</p>

            <button
                type="button"
                wire:click="closeNfeInutilizarSucessoOverlay"
                class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"
                id="erp-nfe-inutilizar-sucesso-ok"
            >OK</button>

            <p class="erp-nfe-fiscal-overlay__hint">{{ \App\Support\Erp\Nfe\NfeInutilizacaoMotivo::HINT_SUCESSO }}</p>
        </div>
    </div>
@endif
