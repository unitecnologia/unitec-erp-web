{{-- Overlay da clonagem no Monitor — mesmo visual do cancelamento / faturamento --}}
<div
    class="erp-nfe-fiscal-progress erp-fv-mon-nfe-lote erp-fv-mon-cancelar {{ $this->clonarProgressOpen ? 'is-visible' : '' }}"
    aria-live="polite"
    aria-busy="{{ $this->clonarProgressOpen ? 'true' : 'false' }}"
    role="status"
    data-erp-fv-mon-clonar-progress
>
    <div class="erp-nfe-fiscal-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-nfe-fiscal-progress__panel" wire:ignore data-erp-fv-mon-clonar-panel>
        <div class="erp-nfe-fiscal-progress__spinner" aria-hidden="true"></div>

        <p class="erp-nfe-fiscal-progress__title">Clonando pedido</p>

        <p class="erp-nfe-fiscal-progress__status" data-erp-fv-mon-clonar-status>
            Preparando clonagem...
        </p>

        <div class="erp-nfe-fiscal-progress__track" aria-hidden="true">
            <div class="erp-nfe-fiscal-progress__bar" data-erp-fv-mon-clonar-bar style="width: 0%"></div>
        </div>

        <ol class="erp-nfe-fiscal-progress__steps">
            @foreach ([
                'Validando pedido cancelado',
                'Preparando clonagem',
                'Copiando cliente e vendedor',
                'Copiando itens',
                'Copiando condições comerciais',
                'Gerando novo DAV/pedido',
                'Criando reserva de estoque',
                'Abrindo Tela de Venda',
            ] as $i => $label)
                <li data-erp-fv-mon-clonar-step data-step="{{ $i }}">{{ $label }}</li>
            @endforeach
        </ol>

        <ul class="erp-fv-mon-faturar-log" data-erp-fv-mon-clonar-log></ul>

        <p class="erp-nfe-fiscal-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>

@once
    @php
        $jsPath = public_path('js/erp-fv-monitor-clonar.js');
        $jsV = is_file($jsPath) ? (string) filemtime($jsPath) : '1';
    @endphp
    <script src="{{ asset('js/erp-fv-monitor-clonar.js') }}?v={{ $jsV }}"></script>
@endonce
