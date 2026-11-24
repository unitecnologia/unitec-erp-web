{{--
  Overlay Cadastro de Clientes (iframe sob demanda).
  Props: $closeAction, $applyAction, $listenMessage (default true; false se outro JS já trata postMessage, ex. PDV)
  Fit + postMessage só enquanto o overlay está no DOM — sem listener global na tela.
--}}
@php
    $closeAction = $closeAction ?? 'closePersonOverlay';
    $applyAction = $applyAction ?? 'applyOverlayPersonSaved';
    $listenMessage = (bool) ($listenMessage ?? true);
@endphp
@if ($this->overlayPersonOpen)
    <div
        class="erp-person-create-overlay"
        wire:key="erp-person-create-overlay"
        x-data
        x-init="$nextTick(() => window.erpFormOverlayFit?.($el))"
        @if ($listenMessage)
            x-on:message.window="
                if ($event.data?.type !== 'erp-orcamento-overlay-close') return;
                const id = Number.parseInt(String($event.data.clienteId ?? ''), 10);
                if (! Number.isNaN(id) && id > 0) {
                    $wire.{{ $applyAction }}(id);
                    return;
                }
                $wire.{{ $closeAction }}();
            "
        @endif
    >
        @include('filament.components.erp.form-overlay', [
            'title' => 'Cadastro de Clientes',
            'iframeUrl' => $this->personOverlayUrl,
            'closeAction' => $closeAction,
        ])
    </div>
@endif
