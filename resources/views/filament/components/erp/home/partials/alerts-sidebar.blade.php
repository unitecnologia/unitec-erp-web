@php
    $alerts = $alerts ?? [];
    $important = $alerts['important'] ?? [];
    $boletos = $alerts['boletos'] ?? [];
    $estoque = $alerts['estoque'] ?? [];
@endphp

<aside class="erp-dash__aside" aria-label="Alertas">
    <article class="erp-dash-panel">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Alertas importantes</h2>
        </header>
        <ul class="erp-dash-alert-list">
            @foreach ($important as $alert)
                <li @class([
                    'erp-dash-alert',
                    'erp-dash-alert--' . $alert['tone'],
                    'erp-dash-alert--featured' => ! empty($alert['featured']),
                    'erp-dash-alert--blink' => ! empty($alert['blink']),
                ])>
                    <span class="erp-dash-alert__title">{{ $alert['title'] }}</span>
                    <span class="erp-dash-alert__time">{{ $alert['time'] }}</span>
                </li>
            @endforeach
        </ul>
    </article>

    <article class="erp-dash-panel">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Boletos vencidos</h2>
        </header>
        <ul class="erp-dash-mini-list">
            @foreach ($boletos as $boleto)
                <li class="erp-dash-mini-list__item">
                    <span class="erp-dash-mini-list__title">{{ $boleto['cliente'] }}</span>
                    <span class="erp-dash-mini-list__meta">{{ $boleto['valor'] }} · {{ $boleto['vencimento'] }}</span>
                </li>
            @endforeach
        </ul>
    </article>

    <article class="erp-dash-panel">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Estoque mínimo</h2>
        </header>
        <ul class="erp-dash-mini-list">
            @foreach ($estoque as $item)
                <li class="erp-dash-mini-list__item">
                    <span class="erp-dash-mini-list__title">{{ $item['produto'] }}</span>
                    <span class="erp-dash-mini-list__meta">Atual {{ $item['atual'] }} · Mín. {{ $item['minimo'] }}</span>
                </li>
            @endforeach
        </ul>
    </article>
</aside>
