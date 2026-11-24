{{--
  Overlay Cadastro de Produtos (iframe sob demanda).
  Props: $closeAction, $applyAction, $listenMessage (default true; false se outro JS já trata postMessage)
  Fit + postMessage só enquanto o overlay está no DOM — sem listener global na tela.
--}}
@php
    $closeAction = $closeAction ?? 'closeProductOverlay';
    $applyAction = $applyAction ?? 'applyOverlayProdutoSaved';
    $listenMessage = (bool) ($listenMessage ?? true);
@endphp
@if ($this->overlayProductOpen)
    <div
        class="erp-product-create-overlay"
        wire:key="erp-product-create-overlay"
        x-data
        x-init="$nextTick(() => window.erpFormOverlayFit?.($el))"
        @if ($listenMessage)
            x-on:message.window="
                if ($event.data?.type !== 'erp-orcamento-overlay-close') return;
                const codigo = String($event.data.produtoCodigo ?? '').trim();
                if (codigo !== '') {
                    $wire.{{ $applyAction }}(codigo);
                    return;
                }
                if ($event.data?.clienteId) return;
                $wire.{{ $closeAction }}();
            "
        @endif
    >
        @include('filament.components.erp.form-overlay', [
            'title' => 'Cadastro de Produtos',
            'iframeUrl' => $this->productOverlayUrl,
            'closeAction' => $closeAction,
        ])
    </div>
@endif
