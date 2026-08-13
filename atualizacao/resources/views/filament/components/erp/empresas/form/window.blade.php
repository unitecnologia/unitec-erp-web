<div class="erp-empresas-window">
    <header class="erp-empresas-window__titlebar">
        <span class="erp-empresas-window__title">Dados da Empresa</span>
        <button
            type="button"
            class="erp-empresas-window__close"
            wire:click="cancelForm"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-empresas-window__body">
        @include('filament.components.erp.empresas.form.shell')
        @include('filament.components.erp.empresas.form.action-bar')
    </div>
</div>
