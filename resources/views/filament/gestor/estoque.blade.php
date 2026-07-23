<x-filament-panels::page>
    @php $s = $snapshot; @endphp
    <div class="gestor-shell" data-theme="{{ $this->gestorTema }}">
        <div class="gestor-shell__inner">
            @include('filament.gestor.partials.top', [
                'title' => 'Estoque',
                'subtitle' => $this->empresaNome(),
                'eyebrow' => 'Disponibilidade',
            ])

            <section class="gestor-spotlight">
                <div class="gestor-spotlight__card {{ (($s['estoque_baixo'] ?? 0) > 0) ? 'gestor-spotlight__card--warn' : '' }}">
                    <p class="gestor-kicker">Itens críticos</p>
                    <p class="gestor-spotlight__value">{{ (int) ($s['estoque_baixo'] ?? 0) }}</p>
                    <p class="gestor-spotlight__hint">Abaixo do estoque mínimo</p>
                </div>
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
</x-filament-panels::page>
