<x-filament-panels::page>
    @php
        $g = $saudeEstoque;
        $tone = (string) ($g['tone'] ?? 'slate');
        $percent = max(0, (float) ($g['percent'] ?? 0));
        $rightTone = (string) ($g['stat_right_tone'] ?? '');
    @endphp
    <div class="gestor-shell" data-theme="{{ $this->gestorTema }}">
        <div class="gestor-shell__inner">
            @include('filament.gestor.partials.top', [
                'title' => 'Estoque',
                'subtitle' => $this->empresaNome(),
                'eyebrow' => 'Disponibilidade',
            ])

            <section class="gestor-estoque-gauge gestor-estoque-gauge--{{ $tone }}" aria-label="Saúde do estoque">
                <header class="gestor-estoque-gauge__head">
                    <h2>{{ $g['label'] ?? 'Saúde do Estoque' }}</h2>
                </header>
                <div class="gestor-estoque-gauge__body">
                    <div class="gestor-estoque-gauge__stats">
                        <div class="gestor-estoque-gauge__stat">
                            <span>{{ $g['stat_left_label'] ?? 'OK' }}</span>
                            <strong>{{ $g['stat_left'] ?? '—' }}</strong>
                        </div>
                        <div @class([
                            'gestor-estoque-gauge__stat',
                            'gestor-estoque-gauge__stat--'.$rightTone => filled($rightTone),
                        ])>
                            <span>{{ $g['stat_right_label'] ?? 'Crítico' }}</span>
                            <strong>{{ $g['stat_right'] ?? '—' }}</strong>
                        </div>
                    </div>
                    <div class="gestor-estoque-gauge__meter" aria-hidden="true">
                        <canvas
                            class="gestor-estoque-gauge__canvas"
                            data-gestor-gauge-canvas
                            data-percent="{{ $percent }}"
                            data-tone="{{ $tone }}"
                            width="280"
                            height="160"
                        ></canvas>
                        <div class="gestor-estoque-gauge__pct">{{ $g['display_percent'] ?? '0,0%' }}</div>
                    </div>
                </div>
                @if (filled($g['meta_label'] ?? null))
                    <p class="gestor-estoque-gauge__meta">{{ $g['meta_label'] }}</p>
                @endif
            </section>

            <a class="gestor-cta" href="{{ \App\Filament\Gestor\Pages\ProdutosGestorPage::getUrl(panel: 'gestor') }}" wire:navigate>
                Ajustar preço / nome / estoque
            </a>

            <section class="gestor-section">
                <div class="gestor-section__head"><h2>Críticos agora</h2></div>
                @if ($criticos === [])
                    <p class="gestor-empty">Nenhum produto abaixo do mínimo.</p>
                @else
                    <ul class="gestor-list">
                        @foreach ($criticos as $item)
                            <li>
                                <a
                                    class="gestor-item"
                                    href="{{ \App\Filament\Gestor\Pages\ProdutosGestorPage::getUrl(['produto' => $item['id']], panel: 'gestor') }}"
                                    wire:navigate
                                >
                                    <div class="gestor-item__main">
                                        <span class="gestor-item__name">{{ $item['descricao'] }}</span>
                                        <span class="gestor-item__code">Cód. {{ $item['codigo'] }}</span>
                                    </div>
                                    <div class="gestor-item__side">
                                        <span class="gestor-item__price">{{ rtrim(rtrim(number_format($item['estoque'], 3, ',', '.'), '0'), ',') }}</span>
                                        <span class="gestor-item__stock">mín. {{ rtrim(rtrim(number_format($item['minimo'], 3, ',', '.'), '0'), ',') }}</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
        @include('filament.gestor.partials.bottom-nav')
    </div>
    @include('filament.gestor.partials.persist-snapshot')
    <script src="{{ asset('js/gestor-gauge.js') }}?v=1" defer></script>
</x-filament-panels::page>
