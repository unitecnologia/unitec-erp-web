@php
    $d = $card['data'] ?? [];
    $vp = $d['variacao_pct'] ?? null;
    $toneClass = match ($card['tone'] ?? 'info') {
        'alert' => 'gestor-card--alert',
        'warn' => 'gestor-card--warn',
        'ok' => 'gestor-card--ok',
        default => 'gestor-card--info',
    };
@endphp
<button
    type="button"
    class="gestor-card gestor-card--tap {{ $toneClass }}"
    wire:click.prevent="abrirDetalhe('{{ $card['tipo'] }}')"
    wire:loading.attr="disabled"
>
    <div class="gestor-card__body">
        <div class="gestor-card__top">
            <span class="gestor-kicker">{{ $card['label'] }}</span>
            @if ($vp !== null)
                <span class="gestor-trend {{ $vp >= 0 ? 'is-up' : 'is-down' }}">
                    {{ $vp >= 0 ? '↑' : '↓' }} {{ number_format(abs((float) $vp), 0) }}%
                </span>
            @endif
        </div>
        <span class="gestor-card__meta">{{ (int) ($d['qtd'] ?? 0) }} títulos</span>
        <span class="gestor-card__value">{{ $this->money((float) ($d['valor'] ?? 0)) }}</span>
    </div>
    <span class="gestor-card__chev" aria-hidden="true">›</span>
</button>
