{{-- Overlay de reabertura do Monitor — mesmo visual do cancelamento / faturamento --}}
<div
    class="erp-nfe-fiscal-progress erp-fv-mon-nfe-lote erp-fv-mon-cancelar {{ $this->reabrirProgressOpen ? 'is-visible' : '' }}"
    aria-live="polite"
    aria-busy="{{ $this->reabrirProgressOpen ? 'true' : 'false' }}"
    role="status"
    data-erp-fv-mon-reabrir-progress
>
    <div class="erp-nfe-fiscal-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-nfe-fiscal-progress__panel" wire:ignore data-erp-fv-mon-reabrir-panel>
        <div class="erp-nfe-fiscal-progress__spinner" aria-hidden="true"></div>

        <p class="erp-nfe-fiscal-progress__title">Reabrindo pedido</p>

        <p class="erp-nfe-fiscal-progress__status" data-erp-fv-mon-reabrir-status>
            {{ $this->reabrirProgressStatus !== '' ? $this->reabrirProgressStatus : 'Preparando reabertura...' }}
        </p>

        <div class="erp-nfe-fiscal-progress__track" aria-hidden="true">
            <div class="erp-nfe-fiscal-progress__bar" data-erp-fv-mon-reabrir-bar style="width: 0%"></div>
        </div>

        <ol class="erp-nfe-fiscal-progress__steps">
            @foreach ([
                'Validando pedido',
                'Verificando documentos fiscais',
                'Verificando boleto bancário',
                'Baixando boleto no banco',
                'Estornando financeiro',
                'Estornando Livro Caixa',
                'Devolvendo estoque',
                'Recriando reserva',
                'Reabrindo pedido',
            ] as $i => $label)
                <li data-erp-fv-mon-reabrir-step data-step="{{ $i }}">{{ $label }}</li>
            @endforeach
        </ol>

        <div class="erp-fv-mon-cancelar-boleto" data-erp-fv-mon-reabrir-boleto>
            <p data-erp-fv-mon-reabrir-boleto-msg></p>
            <div class="erp-fv-mon-cancelar-boleto__actions">
                <button type="button" data-erp-fv-mon-reabrir-boleto-sim data-erp-fv-mon-cancelar-boleto-sim>Continuar</button>
                <button type="button" data-erp-fv-mon-reabrir-boleto-nao>Não</button>
            </div>
        </div>

        <ul class="erp-fv-mon-faturar-log" data-erp-fv-mon-reabrir-log></ul>

        <p class="erp-nfe-fiscal-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>

@once
    @php
        $jsPath = public_path('js/erp-fv-monitor-reabrir.js');
        $jsV = is_file($jsPath) ? (string) filemtime($jsPath) : '1';
    @endphp
    <script src="{{ asset('js/erp-fv-monitor-reabrir.js') }}?v={{ $jsV }}"></script>
@endonce
