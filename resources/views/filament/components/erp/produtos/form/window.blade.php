<div class="erp-produtos-window" @if ($this->isEditingProduct()) wire:keydown.f7.window="openProductCardex" @endif>
    <header class="erp-produtos-window__titlebar">
        <span class="erp-produtos-window__title">Cadastro de Produtos</span>
        <button
            type="button"
            class="erp-produtos-window__close"
            wire:click="cancelForm"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-produtos-window__body">
        @include('filament.components.erp.produtos.form.shell')
    </div>
</div>
