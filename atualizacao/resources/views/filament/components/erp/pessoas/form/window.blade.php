<div class="erp-pessoas-window">
    <header class="erp-pessoas-window__titlebar">
        <span class="erp-pessoas-window__title">Cadastro de Pessoas</span>
        <button
            type="button"
            class="erp-pessoas-window__close"
            wire:click="cancelForm"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-pessoas-window__body">
        @include('filament.components.erp.pessoas.form.shell')
        @include('filament.components.erp.pessoas.form.action-bar')
    </div>
</div>
