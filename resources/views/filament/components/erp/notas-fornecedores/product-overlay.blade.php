@if ($this->overlayProductOpen)
    @include('filament.components.erp.form-overlay', [
        'title' => 'Cadastro de Produtos',
        'iframeUrl' => $this->productOverlayUrl,
        'closeAction' => 'closeProductOverlay',
    ])
@endif
