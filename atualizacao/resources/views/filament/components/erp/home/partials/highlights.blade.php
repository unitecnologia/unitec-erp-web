@php
    $highlights = $highlights ?? [];
@endphp

<article class="erp-dash-panel erp-dash__highlights">
    <header class="erp-dash-panel__head">
        <h2 class="erp-dash-panel__title">Destaques do mês</h2>
        <span class="erp-dash-panel__meta">mês</span>
    </header>
    <div class="erp-dash-panel__body erp-dash__highlights-body">
        @forelse ($highlights as $item)
            <div class="erp-dash-highlight">
                <span class="erp-dash-highlight__label">{{ $item['label'] ?? '' }}</span>
                <strong class="erp-dash-highlight__value">{{ $item['value'] ?? '—' }}</strong>
                <span class="erp-dash-highlight__hint">{{ $item['hint'] ?? '' }}</span>
            </div>
        @empty
            <p class="erp-dash__highlights-empty">Sem dados no período.</p>
        @endforelse
    </div>
</article>
