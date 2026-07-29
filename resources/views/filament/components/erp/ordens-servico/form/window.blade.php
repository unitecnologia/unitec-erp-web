<div class="erp-os-window">
    <header class="erp-os-window__titlebar">
        <span>Lançamento OS</span>
        <button
            type="button"
            class="erp-os-window__close"
            wire:click="handleOsFormEscape"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-os-window__body">
        @include('filament.components.erp.ordens-servico.form.shell')
        @include('filament.components.erp.ordens-servico.form.totals')
        @include('filament.components.erp.ordens-servico.form.action-bar')
    </div>

    @if ($this->overlayProductOpen)
        @include('filament.components.erp.form-overlay', [
            'title' => 'Cadastro de Produtos',
            'iframeUrl' => $this->productOverlayUrl,
            'closeAction' => 'closeProductOverlay',
        ])
    @endif

    @if ($this->overlayPersonOpen)
        @include('filament.components.erp.form-overlay', [
            'title' => 'Cadastro de Clientes',
            'iframeUrl' => $this->personOverlayUrl,
            'closeAction' => 'closePersonOverlay',
        ])
    @endif

    @include('filament.components.erp.ordens-servico.form.item-delete-confirm')
</div>
