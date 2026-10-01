@if ($this->clonarPedidoModalOpen)
    <div
        class="erp-fv-mon-clonar-aviso"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-fv-mon-clonar-title"
        x-data
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.recusarClonarPedidoCancelado(); }
        "
    >
        <div class="erp-fv-mon-clonar-aviso__box">
            <div class="erp-fv-mon-clonar-aviso__icon" aria-hidden="true">!</div>
            <h2 id="erp-fv-mon-clonar-title" class="erp-fv-mon-clonar-aviso__title">PEDIDO CANCELADO</h2>
            <p class="erp-fv-mon-clonar-aviso__text">Deseja clonar o pedido para uma nova venda?</p>
            <div class="erp-fv-mon-clonar-aviso__actions">
                <button
                    type="button"
                    class="erp-fv-mon-clonar-aviso__btn"
                    wire:click="confirmarClonarPedidoCancelado"
                    wire:loading.attr="disabled"
                    wire:target="confirmarClonarPedidoCancelado"
                >Sim</button>
                <button
                    type="button"
                    class="erp-fv-mon-clonar-aviso__btn erp-fv-mon-clonar-aviso__btn--nao"
                    wire:click="recusarClonarPedidoCancelado"
                    wire:loading.attr="disabled"
                    wire:target="confirmarClonarPedidoCancelado"
                >Não</button>
            </div>
        </div>
    </div>
@endif
