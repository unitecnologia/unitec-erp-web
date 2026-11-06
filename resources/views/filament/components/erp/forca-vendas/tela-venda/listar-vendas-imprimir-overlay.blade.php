@if ($this->fvListarImprimirOverlayOpen && filled($this->fvListarImprimirOverlayUrl))
    <div
        class="erp-form-overlay erp-fv-tv-listar-imprimir-overlay"
        role="dialog"
        aria-modal="true"
        aria-label="Visualizar comprovante da venda"
        x-data
        x-on:keydown.escape.window="$wire.fecharFvListarImprimirOverlay()"
        x-init="
            const onMessage = (event) => {
                if (event.data?.type === 'erp-monitor-pedidos-preview-close') {
                    $wire.fecharFvListarImprimirOverlay();
                }
            };
            window.addEventListener('message', onMessage);
            return () => window.removeEventListener('message', onMessage);
        "
    >
        <div class="erp-form-overlay__backdrop" wire:click="fecharFvListarImprimirOverlay"></div>

        <div class="erp-form-overlay__panel">
            <iframe
                src="{{ $this->fvListarImprimirOverlayUrl }}"
                class="erp-form-overlay__iframe"
                title="Visualizar comprovante da venda"
            ></iframe>
        </div>
    </div>
@endif
