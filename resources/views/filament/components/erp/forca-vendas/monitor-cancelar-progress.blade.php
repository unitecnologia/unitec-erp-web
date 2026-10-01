{{-- Overlay de cancelamento do Monitor — mesmo visual do faturamento / NF-e --}}
<div
    class="erp-nfe-fiscal-progress erp-fv-mon-nfe-lote erp-fv-mon-cancelar {{ $this->cancelarProgressOpen ? 'is-visible' : '' }}"
    aria-live="polite"
    aria-busy="{{ $this->cancelarProgressOpen ? 'true' : 'false' }}"
    role="status"
    data-erp-fv-mon-cancelar-progress
>
    <div class="erp-nfe-fiscal-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-nfe-fiscal-progress__panel" wire:ignore data-erp-fv-mon-cancelar-panel>
        <div class="erp-nfe-fiscal-progress__spinner" aria-hidden="true"></div>

        <p class="erp-nfe-fiscal-progress__title">Cancelando pedidos</p>

        <p class="erp-nfe-fiscal-progress__status" data-erp-fv-mon-cancelar-status>
            {{ $this->cancelarProgressStatus !== '' ? $this->cancelarProgressStatus : 'Preparando cancelamento...' }}
        </p>

        <div class="erp-nfe-fiscal-progress__track" aria-hidden="true">
            <div class="erp-nfe-fiscal-progress__bar" data-erp-fv-mon-cancelar-bar style="width: 0%"></div>
        </div>

        <ol class="erp-nfe-fiscal-progress__steps">
            @foreach ([
                'Validando pedido',
                'Verificando financeiro',
                'Verificando boleto bancário',
                'Solicitando baixa do boleto',
                'Devolvendo estoque',
                'Estornando financeiro',
                'Cancelando venda',
                'Finalizando pedido',
            ] as $i => $label)
                <li data-erp-fv-mon-cancelar-step data-step="{{ $i }}">{{ $label }}</li>
            @endforeach
        </ol>

        <div class="erp-fv-mon-cancelar-boleto" data-erp-fv-mon-cancelar-boleto>
            <p data-erp-fv-mon-cancelar-boleto-msg></p>
            <div class="erp-fv-mon-cancelar-boleto__actions">
                <button type="button" data-erp-fv-mon-cancelar-boleto-sim>Continuar</button>
                <button type="button" data-erp-fv-mon-cancelar-boleto-nao>Não</button>
            </div>
        </div>

        <ul class="erp-fv-mon-faturar-log" data-erp-fv-mon-cancelar-log></ul>

        <p class="erp-nfe-fiscal-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>

@once
    @php
        $jsPath = public_path('js/erp-fv-monitor-cancelar.js');
        $jsV = is_file($jsPath) ? (string) filemtime($jsPath) : '1';
    @endphp
    <script src="{{ asset('js/erp-fv-monitor-cancelar.js') }}?v={{ $jsV }}"></script>
@endonce
