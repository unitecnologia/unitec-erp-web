{{-- Bridge de clique: a grade fica fora do header; captura no container da página. --}}
<div
    x-data
    x-init="
        const root = $el.closest('.erp-fv-monitor-page') || document.querySelector('.erp-fv-monitor-page');
        if (!root || root.dataset.fvFinBound === '1') return;
        root.dataset.fvFinBound = '1';
        root.addEventListener('click', (e) => {
            const cell = e.target.closest('[data-fv-fin]');
            if (!cell) return;
            e.stopPropagation();
            e.preventDefault();
            const id = Number(cell.getAttribute('data-fv-fin'));
            if (id) $wire.abrirLiberacaoFinanceira(id);
        }, true);
    "
    class="erp-fv-fin-click-bridge"
    hidden
></div>

@if ($this->financeiroModalOpen && $this->financeiroPedido)
@php
    $order = $this->financeiroPedido;
    $dav = $order->orcamento?->numero
        ? (string) (int) preg_replace('/\D/', '', (string) $order->orcamento->numero)
        : '#'.$order->id;
    $total = 'R$ '.number_format((float) $order->total, 2, ',', '.');
    $resumo = $order->financeiroResumo();
@endphp
<div
    class="erp-fv-fin-alert"
    wire:keydown.escape.window="fecharLiberacaoFinanceira"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="erp-fv-fin-alert-title"
>
    <div class="erp-fv-fin-alert__backdrop" wire:click="fecharLiberacaoFinanceira"></div>
    <div class="erp-fv-fin-alert__box">
        <button
            type="button"
            class="erp-fv-fin-alert__close"
            wire:click="fecharLiberacaoFinanceira"
            aria-label="Fechar"
            title="Fechar"
        >&times;</button>

        <div class="erp-fv-fin-alert__head">
            <span class="erp-fv-fin-alert__icon" aria-hidden="true">!</span>
            <h2 id="erp-fv-fin-alert-title" class="erp-fv-fin-alert__title">Liberação financeira</h2>
        </div>

        <p class="erp-fv-fin-alert__linha">
            DAV <strong>{{ $dav }}</strong>
            · {{ $order->clienteNome() }}
            · <strong>{{ $total }}</strong>
        </p>

        @if ($resumo['motivos'] !== [])
            <div class="erp-fv-fin-alert__block">
                <div class="erp-fv-fin-alert__label">Motivos</div>
                <ul class="erp-fv-fin-alert__motivos">
                    @foreach ($resumo['motivos'] as $motivo)
                        <li>{{ $motivo }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($resumo['situacao'] !== [])
            <div class="erp-fv-fin-alert__block">
                <div class="erp-fv-fin-alert__label">Situação</div>
                <dl class="erp-fv-fin-alert__grid">
                    @foreach ($resumo['situacao'] as $row)
                        <div class="erp-fv-fin-alert__row">
                            <dt>{{ $row['label'] }}</dt>
                            <dd>{{ $row['valor'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        <div class="erp-fv-fin-alert__actions">
            <button
                type="button"
                class="erp-fv-fin-alert__btn erp-fv-fin-alert__btn--secondary"
                wire:click="negarLiberacaoFinanceira"
            >Negar</button>
            <button
                type="button"
                class="erp-fv-fin-alert__btn"
                wire:click="aprovarLiberacaoFinanceira"
            >Liberar</button>
        </div>

        <p class="erp-fv-fin-alert__hint">
            Liberar → Pendente (app: Enviado). Negar → Cancelado.
        </p>
    </div>
</div>
@endif
