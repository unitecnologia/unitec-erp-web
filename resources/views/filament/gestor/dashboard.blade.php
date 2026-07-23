<x-filament-panels::page>
    @php
        $s = $snapshot;
        $saude = $s['saude'] ?? ['percent' => 0, 'tone' => 'gray', 'label' => '—', 'short' => '—', 'message' => ''];
        $pct = (float) ($saude['percent'] ?? 0);
        $ring = max(0, min(100, $pct));
    @endphp

    <div class="gestor-shell" data-theme="{{ $this->gestorTema }}">
        <div class="gestor-shell__inner">
            @include('filament.gestor.partials.top', [
                'title' => $s['saudacao'] ?? 'Início',
                'subtitle' => trim(($this->empresaNome() ?: '').' · atualizado '.$s['atualizado_em']),
                'eyebrow' => 'Pulso da empresa',
                'refresh' => 'refreshSnapshot',
            ])

            {{-- Saúde — diferencial --}}
            <section class="gestor-hero gestor-hero--{{ $saude['tone'] ?? 'gray' }}" aria-label="Saúde da empresa">
                <div
                    class="gestor-ring"
                    style="--pct: {{ $ring }}"
                    role="img"
                    aria-label="Saúde {{ number_format($pct, 0) }} por cento"
                >
                    <div class="gestor-ring__core">
                        <span class="gestor-ring__value">{{ number_format($pct, 0) }}%</span>
                        <span class="gestor-ring__short">{{ $saude['short'] ?? '' }}</span>
                    </div>
                </div>
                <div class="gestor-hero__copy">
                    <h2 class="gestor-hero__title">{{ $saude['label'] ?? 'Saúde' }}</h2>
                    <p class="gestor-hero__msg">{{ $saude['message'] ?? '' }}</p>
                </div>
            </section>

            {{-- Faturamento destaque --}}
            <section class="gestor-spotlight">
                <div class="gestor-spotlight__card">
                    <p class="gestor-kicker">Faturamento hoje</p>
                    <p class="gestor-spotlight__value">{{ $this->money((float) ($s['faturamento_hoje'] ?? 0)) }}</p>
                    <p class="gestor-spotlight__hint">{{ $s['variacao_dia_hint'] ?? '' }}</p>
                </div>
                <div class="gestor-spotlight__card gestor-spotlight__card--soft">
                    <p class="gestor-kicker">Faturamento mês</p>
                    <p class="gestor-spotlight__value gestor-spotlight__value--sm">{{ $this->money((float) ($s['faturamento_mes'] ?? 0)) }}</p>
                </div>
            </section>

            @if (((int) ($s['aprovacoes_pendentes'] ?? 0)) > 0)
                <a class="gestor-cta gestor-cta--aprov" href="{{ \App\Filament\Gestor\Pages\AprovacoesGestorPage::getUrl(panel: 'gestor') }}" wire:navigate>
                    {{ (int) $s['aprovacoes_pendentes'] }} aprovação(ões) aguardando →
                </a>
            @endif

            {{-- Grid KPIs --}}
            <section class="gestor-grid" aria-label="Indicadores">
                <article class="gestor-card">
                    <p class="gestor-kicker">Caixa</p>
                    <p class="gestor-card__value">{{ $this->money((float) ($s['caixa'] ?? 0)) }}</p>
                </article>
                <article class="gestor-card">
                    <p class="gestor-kicker">Receber hoje</p>
                    <p class="gestor-card__value">{{ $this->money((float) ($s['receber_hoje'] ?? 0)) }}</p>
                </article>
                <article class="gestor-card">
                    <p class="gestor-kicker">Pagar hoje</p>
                    <p class="gestor-card__value">{{ $this->money((float) ($s['pagar_hoje'] ?? 0)) }}</p>
                </article>
                <article class="gestor-card {{ (($s['pedidos_pendentes'] ?? 0) > 0) ? 'gestor-card--alert' : '' }}">
                    <p class="gestor-kicker">Pedidos pend.</p>
                    <p class="gestor-card__value">{{ (int) ($s['pedidos_pendentes'] ?? 0) }}</p>
                </article>
                <article class="gestor-card {{ (($s['entregas_pendentes'] ?? 0) > 0) ? 'gestor-card--alert' : '' }}">
                    <p class="gestor-kicker">Entregas</p>
                    <p class="gestor-card__value">{{ (int) ($s['entregas_pendentes'] ?? 0) }}</p>
                </article>
                <article class="gestor-card {{ (($s['estoque_baixo'] ?? 0) > 0) ? 'gestor-card--warn' : '' }}">
                    <p class="gestor-kicker">Estoque baixo</p>
                    <p class="gestor-card__value">{{ (int) ($s['estoque_baixo'] ?? 0) }}</p>
                </article>
            </section>

            {{-- Pulso / atenção --}}
            <section class="gestor-section">
                <div class="gestor-section__head">
                    <h2>Precisa de atenção</h2>
                </div>
                <ul class="gestor-pulse">
                    @foreach (($s['pulso'] ?? []) as $item)
                        <li class="gestor-pulse__item gestor-pulse__item--{{ $item['tom'] }}">
                            <div>
                                <p class="gestor-pulse__title">{{ $item['titulo'] }}</p>
                                <p class="gestor-pulse__detail">{{ $item['detalhe'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if (! empty($s['metas_vendedores']))
                <section class="gestor-section">
                    <div class="gestor-section__head">
                        <h2>Metas dos vendedores</h2>
                    </div>
                    <div class="gestor-metas">
                        @foreach ($s['metas_vendedores'] as $meta)
                            @php $mp = (float) ($meta['percent'] ?? 0); @endphp
                            <article class="gestor-meta">
                                <div class="gestor-meta__row">
                                    <span class="gestor-meta__name">{{ $meta['label'] ?? ($meta['value_label'] ?? 'Vendedor') }}</span>
                                    <span class="gestor-meta__pct">{{ number_format($mp, 0) }}%</span>
                                </div>
                                <div class="gestor-meta__bar" aria-hidden="true">
                                    <span style="width: {{ max(0, min(100, $mp)) }}%"></span>
                                </div>
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
