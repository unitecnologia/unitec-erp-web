@if ($this->pdvConfirmCancelarVenda)
    <div
        class="erp-pdv-naoencontrado erp-pdv-confirm-overlay"
        role="alertdialog"
        aria-labelledby="erp-pdv-cancelar-venda-title"
        aria-live="assertive"
    >
        <div class="erp-pdv-naoencontrado__box">
            <div class="erp-pdv-naoencontrado__icon" aria-hidden="true">!</div>
            <h2 id="erp-pdv-cancelar-venda-title" class="erp-pdv-naoencontrado__title">
                CANCELAR VENDA
            </h2>
            <p class="erp-pdv-naoencontrado__codigo">Cancelar a venda toda?</p>
            <div class="erp-pdv-naoencontrado__actions">
                <button
                    type="button"
                    wire:click="confirmCancelarCupom"
                    class="erp-pdv-naoencontrado__btn"
                    id="erp-pdv-cancelar-venda-sim"
                >Sim</button>
                <button
                    type="button"
                    wire:click="cancelCancelarCupom"
                    class="erp-pdv-naoencontrado__btn erp-pdv-naoencontrado__btn--secondary"
                    id="erp-pdv-cancelar-venda-nao"
                >Não</button>
            </div>
            <p class="erp-pdv-naoencontrado__hint">Pressione Enter para confirmar ou Esc para voltar.</p>
        </div>
    </div>
@endif
