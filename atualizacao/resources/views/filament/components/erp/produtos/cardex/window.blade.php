<div
    class="erp-produtos-window erp-produtos-cardex-window"
    wire:keydown.escape.window="closeProductCardex"
    wire:keydown.f5.window.prevent="refreshProductCardex"
>
    <header class="erp-produtos-window__titlebar">
        <span class="erp-produtos-window__title">Histórico de Movimentação</span>
        <button
            type="button"
            class="erp-produtos-window__close"
            wire:click="closeProductCardex"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-produtos-window__body erp-produtos-cardex__body">
        @include('filament.components.erp.produtos.cardex.body')
    </div>
</div>
