@php
    $alerts = $alerts ?? [];
    $important = $alerts['important'] ?? [];
    $boletos = $alerts['boletos'] ?? [];
    $estoque = $alerts['estoque'] ?? [];
@endphp

<aside class="erp-dash__aside" aria-label="Alertas">
    <article class="erp-dash-panel erp-dash-panel--alerts">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Alertas importantes</h2>
            <span class="erp-dash-panel__meta">{{ count($important) }}</span>
        </header>
        <ul class="erp-dash-alert-list">
            @forelse ($important as $alert)
                <li @class([
                    'erp-dash-alert',
                    'erp-dash-alert--' . $alert['tone'],
                    'erp-dash-alert--featured' => ! empty($alert['featured']),
                    'erp-dash-alert--blink' => ! empty($alert['blink']),
                ])>
                    <span class="erp-dash-alert__title">{{ $alert['title'] }}</span>
                    <span class="erp-dash-alert__time">{{ $alert['time'] }}</span>
                </li>
            @empty
                <li class="erp-dash-alert erp-dash-alert--empty">
                    <span class="erp-dash-alert__title">Nenhum alerta no momento</span>
                </li>
            @endforelse
        </ul>
    </article>

    <article class="erp-dash-panel">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Boletos vencidos</h2>
            <span class="erp-dash-panel__meta">{{ count($boletos) }}</span>
        </header>
        <ul class="erp-dash-mini-list">
            @forelse ($boletos as $boleto)
                <li class="erp-dash-mini-list__item">
                    <span class="erp-dash-mini-list__title">{{ $boleto['cliente'] }}</span>
                    <span class="erp-dash-mini-list__meta">
                        <strong class="erp-dash-mini-list__amount">{{ $boleto['valor'] }}</strong>
                        <span>{{ $boleto['vencimento'] }}</span>
                    </span>
                </li>
            @empty
                <li class="erp-dash-mini-list__item erp-dash-mini-list__item--empty">
                    <span class="erp-dash-mini-list__title">Nenhum boleto vencido</span>
                </li>
            @endforelse
        </ul>
    </article>

    <article class="erp-dash-panel">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Estoque mínimo</h2>
            <span class="erp-dash-panel__meta">{{ count($estoque) }}</span>
        </header>
        <ul class="erp-dash-mini-list">
            @forelse ($estoque as $item)
                <li class="erp-dash-mini-list__item">
                    <span class="erp-dash-mini-list__title">{{ $item['produto'] }}</span>
                    <span class="erp-dash-mini-list__meta">Atual {{ $item['atual'] }} · Mín. {{ $item['minimo'] }}</span>
                </li>
            @empty
                <li class="erp-dash-mini-list__item erp-dash-mini-list__item--empty">
                    <span class="erp-dash-mini-list__title">Estoque dentro do mínimo</span>
                </li>
            @endforelse
        </ul>
    </article>
</aside>
