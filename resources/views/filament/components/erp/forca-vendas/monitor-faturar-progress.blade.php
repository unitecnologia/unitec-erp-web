{{-- Overlay de faturamento do Monitor — mesmo visual do lote de NF-e --}}
<div
    class="erp-nfe-fiscal-progress erp-fv-mon-nfe-lote erp-fv-mon-faturar {{ $this->faturarProgressOpen ? 'is-visible' : '' }}"
    aria-live="polite"
    aria-busy="{{ $this->faturarProgressOpen ? 'true' : 'false' }}"
    role="status"
    data-erp-fv-mon-faturar-progress
>
    <div class="erp-nfe-fiscal-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-nfe-fiscal-progress__panel" wire:ignore data-erp-fv-mon-faturar-panel>
        <div class="erp-nfe-fiscal-progress__spinner" aria-hidden="true"></div>

        <p class="erp-nfe-fiscal-progress__title">Faturando pedidos</p>

        <p class="erp-nfe-fiscal-progress__status" data-erp-fv-mon-faturar-status>
            {{ $this->faturarProgressStatus !== '' ? $this->faturarProgressStatus : 'Preparando faturamento...' }}
        </p>

        <div class="erp-nfe-fiscal-progress__track" aria-hidden="true">
            <div class="erp-nfe-fiscal-progress__bar" data-erp-fv-mon-faturar-bar style="width: 0%"></div>
        </div>

        <ol class="erp-nfe-fiscal-progress__steps">
            @foreach ([
                'Validando pedido',
                'Gerando venda',
                'Movimentando estoque',
                'Gerando financeiro',
                'Finalizando pedido',
            ] as $i => $label)
                <li data-erp-fv-mon-faturar-step data-step="{{ $i }}">{{ $label }}</li>
            @endforeach
        </ol>

        <ul class="erp-fv-mon-faturar-log" data-erp-fv-mon-faturar-log></ul>

        <p class="erp-nfe-fiscal-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>

@once
    @php
        $jsPath = public_path('js/erp-fv-monitor-faturar.js');
        $jsV = is_file($jsPath) ? (string) filemtime($jsPath) : '1';
    @endphp
    <script src="{{ asset('js/erp-fv-monitor-faturar.js') }}?v={{ $jsV }}"></script>
@endonce
