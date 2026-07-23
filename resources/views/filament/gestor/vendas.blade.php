<x-filament-panels::page>
    @php $s = $snapshot; @endphp
    <div class="gestor-shell" data-theme="{{ $this->gestorTema }}">
        <div class="gestor-shell__inner">
            @include('filament.gestor.partials.top', [
                'title' => 'Vendas',
                'subtitle' => $this->empresaNome(),
                'eyebrow' => 'Performance',
            ])

            <section class="gestor-spotlight">
                <div class="gestor-spotlight__card">
                    <p class="gestor-kicker">Hoje</p>
                    <p class="gestor-spotlight__value">{{ $this->money((float) ($s['faturamento_hoje'] ?? 0)) }}</p>
                    <p class="gestor-spotlight__hint">{{ $s['variacao_dia_hint'] ?? '' }}</p>
                </div>
                <div class="gestor-spotlight__card gestor-spotlight__card--soft">
                    <p class="gestor-kicker">Mês</p>
                    <p class="gestor-spotlight__value gestor-spotlight__value--sm">{{ $this->money((float) ($s['faturamento_mes'] ?? 0)) }}</p>
                </div>
            </section>

            <section class="gestor-grid gestor-grid--2">
                <article class="gestor-card">
                    <p class="gestor-kicker">Pedidos pendentes</p>
                    <p class="gestor-card__value">{{ (int) ($s['pedidos_pendentes'] ?? 0) }}</p>
                </article>
                <article class="gestor-card">
                    <p class="gestor-kicker">Entregas</p>
                    <p class="gestor-card__value">{{ (int) ($s['entregas_pendentes'] ?? 0) }}</p>
                </article>
            </section>

            @if (! empty($s['metas_vendedores']))
                <section class="gestor-section">
                    <div class="gestor-section__head"><h2>Metas</h2></div>
                    <div class="gestor-metas">
                        @foreach ($s['metas_vendedores'] as $meta)
                            @php $mp = (float) ($meta['percent'] ?? 0); @endphp
                            <article class="gestor-meta">
                                <div class="gestor-meta__row">
                                    <span class="gestor-meta__name">{{ $meta['label'] ?? ($meta['value_label'] ?? 'Vendedor') }}</span>
                                    <span class="gestor-meta__pct">{{ number_format($mp, 0) }}%</span>
                                </div>
                                <div class="gestor-meta__bar"><span style="width: {{ max(0, min(100, $mp)) }}%"></span></div>
                                @if (! empty($meta['stat_left']) || ! empty($meta['stat_right']))
                                    <p class="gestor-meta__stats">
                                        {{ $meta['stat_left_label'] ?? 'Real' }}: {{ $meta['stat_left'] ?? '' }}
                                        · {{ $meta['stat_right_label'] ?? 'Meta' }}: {{ $meta['stat_right'] ?? '' }}
                                    </p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
        @include('filament.gestor.partials.bottom-nav')
    </div>
    @include('filament.gestor.partials.persist-snapshot')
</x-filament-panels::page>
