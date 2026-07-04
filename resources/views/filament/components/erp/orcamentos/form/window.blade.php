<div class="erp-orcamentos-window">
    <header class="erp-orcamentos-window__titlebar">
        <span class="erp-orcamentos-window__title">Lançamento de Orçamento</span>
        <button
            type="button"
            class="erp-orcamentos-window__close"
            wire:click="handleOrcamentoFormEscape"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-orcamentos-window__body">
        @include('filament.components.erp.orcamentos.form.shell')
        @include('filament.components.erp.orcamentos.form.totals')
        @include('filament.components.erp.orcamentos.form.action-bar')
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

    @include('filament.components.erp.orcamentos.form.post-save-prompt')
    @include('filament.components.erp.orcamentos.form.item-delete-confirm')
</div>
