@if ($this->limitePedidosAvisoOpen)
    <div
        class="erp-expedicao-aviso"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="erp-expedicao-limite-pedidos-title"
        wire:keydown.escape="fecharLimitePedidosAviso"
    >
        <div class="erp-expedicao-aviso__box">
            <div class="erp-expedicao-aviso__icon" aria-hidden="true">!</div>
            <h2 class="erp-expedicao-aviso__title" id="erp-expedicao-limite-pedidos-title">LIMITE DE PEDIDOS</h2>
            <p class="erp-expedicao-aviso__text">
                Máximo permitido: <strong>{{ $this->limitePedidosAvisoMax }}</strong> pedido(s) por vez.
            </p>
            <button
                type="button"
                wire:click="fecharLimitePedidosAviso"
                class="erp-expedicao-aviso__btn"
            >OK</button>
            <p class="erp-expedicao-aviso__hint">
                Desmarque um pedido para selecionar outro. Este aviso permanece até clicar em OK.
            </p>
        </div>
    </div>
@endif
