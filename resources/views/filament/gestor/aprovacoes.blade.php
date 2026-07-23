<x-filament-panels::page>
    @php
        $pedidos = $pendencias['pedidos'] ?? [];
        $aparelhos = $pendencias['aparelhos'] ?? [];
        $total = (int) ($pendencias['total'] ?? 0);
        $pedido = $this->pedidoSelecionado();
    @endphp

    <div class="gestor-shell" data-theme="{{ $this->gestorTema }}">
        <div class="gestor-shell__inner">
            @if ($pedido)
                @include('filament.gestor.partials.top', [
                    'title' => 'Liberação financeira',
                    'subtitle' => $pedido['cliente'],
                    'eyebrow' => 'Financeiro',
                    'refresh' => null,
                ])

                <button type="button" class="gestor-back" wire:click="fecharPedido">← Voltar</button>

                <article class="gestor-aprov gestor-aprov--fin gestor-aprov--detail">
                    <div class="gestor-aprov__head">
                        <div>
                            <p class="gestor-aprov__title">{{ $pedido['titulo'] }}</p>
                            <p class="gestor-aprov__sub">{{ $pedido['cliente'] }}</p>
                        </div>
                        <div class="gestor-aprov__head-right">
                            <span class="gestor-aprov__badge-fin">Financeiro</span>
                            <p class="gestor-aprov__valor">{{ $this->money((float) $pedido['total']) }}</p>
                        </div>
                    </div>

                    <div class="gestor-aprov__meta">
                        <span>{{ $pedido['quando'] }}</span>
                        <span>{{ $pedido['vendedor'] }}</span>
                        <span>{{ $pedido['forma'] }}</span>
                        @if (($pedido['desconto_pct'] ?? null) !== null)
                            <span class="gestor-aprov__tag">Desconto {{ number_format((float) $pedido['desconto_pct'], 1, ',', '') }}%</span>
                        @endif
                    </div>

                    @if (! empty($pedido['motivos']))
                        <div class="gestor-aprov-fin">
                            <p class="gestor-aprov-fin__label">Motivos</p>
                            <ul class="gestor-aprov-fin__motivos">
                                @foreach ($pedido['motivos'] as $motivo)
                                    <li>{{ $motivo }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($pedido['situacao']))
                        <div class="gestor-aprov-fin">
                            <p class="gestor-aprov-fin__label">Situação</p>
                            <dl class="gestor-aprov-fin__grid">
                                @foreach ($pedido['situacao'] as $row)
                                    <div class="gestor-aprov-fin__row">
                                        <dt>{{ $row['label'] }}</dt>
                                        <dd>{{ $row['valor'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @elseif (! empty($pedido['motivo']))
                        <div class="gestor-aprov-fin">
                            <p class="gestor-aprov-fin__motivo">{!! nl2br(e($pedido['motivo'])) !!}</p>
                        </div>
                    @endif

                    <p class="gestor-aprov-fin__hint">
                        Liberar → Pendente (app: Enviado). Negar → Cancelado.
                    </p>

                    <div class="gestor-aprov__actions">
                        <button
                            type="button"
                            class="gestor-btn gestor-btn--danger"
                            wire:click="rejeitarPedido({{ $pedido['id'] }})"
                            wire:loading.attr="disabled"
                            wire:confirm="Negar e cancelar este pedido?"
                            @disabled(! $this->podeAprovarPedidos())
                        >
                            Negar
                        </button>
                        <button
                            type="button"
                            class="gestor-btn gestor-btn--fin"
                            wire:click="aprovarPedido({{ $pedido['id'] }})"
                            wire:loading.attr="disabled"
                            @disabled(! $this->podeAprovarPedidos())
                        >
                            Liberar
                        </button>
                    </div>
                </article>
            @else
                @include('filament.gestor.partials.top', [
                    'title' => 'Aprovações',
                    'subtitle' => $total > 0 ? ($total.' pendência(s)') : 'Nada aguardando',
                    'eyebrow' => 'Central',
                    'refresh' => 'refreshPendencias',
                ])

                @if ($total === 0)
                    <div class="gestor-empty-card">
                        <p class="gestor-empty-card__title">Tudo em dia</p>
                        <p class="gestor-empty">Nenhuma liberação financeira ou aparelho aguardando.</p>
                    </div>
                @endif

                @if ($pedidos !== [])
                    <section class="gestor-section">
                        <div class="gestor-section__head">
                            <h2>Liberação financeira</h2>
                            <span class="gestor-badge">{{ count($pedidos) }}</span>
                        </div>

                        <div class="gestor-aprov-list">
                            @foreach ($pedidos as $item)
                                <button
                                    type="button"
                                    class="gestor-aprov gestor-aprov--fin gestor-aprov--row"
                                    wire:click="abrirPedido({{ $item['id'] }})"
                                >
                                    <div class="gestor-aprov__head">
                                        <div>
                                            <p class="gestor-aprov__title">{{ $item['cliente'] }}</p>
                                            <p class="gestor-aprov__sub">
                                                {{ $item['titulo'] }}
                                                · {{ $item['quando'] }}
                                                · {{ $item['vendedor'] }}
                                            </p>
                                        </div>
                                        <div class="gestor-aprov__head-right">
                                            <span class="gestor-aprov__badge-fin">Financeiro</span>
                                            <p class="gestor-aprov__valor">{{ $this->money((float) $item['total']) }}</p>
                                        </div>
                                    </div>
                                    <p class="gestor-aprov__tap">Toque para liberar →</p>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($aparelhos !== [])
                    <section class="gestor-section">
                        <div class="gestor-section__head">
                            <h2>Aparelhos</h2>
                            <span class="gestor-badge">{{ count($aparelhos) }}</span>
                        </div>

                        <div class="gestor-aprov-list">
                            @foreach ($aparelhos as $ap)
                                <article class="gestor-aprov">
                                    <div class="gestor-aprov__head">
                                        <div>
                                            <p class="gestor-aprov__title">{{ $ap['titulo'] }}</p>
                                            <p class="gestor-aprov__sub">{{ $ap['app'] }} · {{ $ap['platform'] }}</p>
                                        </div>
                                        <p class="gestor-aprov__valor gestor-aprov__valor--sm">v{{ $ap['versao'] }}</p>
                                    </div>
                                    <div class="gestor-aprov__meta">
                                        <span>Cadastro {{ $ap['quando'] }}</span>
                                    </div>
                                    <div class="gestor-aprov__actions">
                                        <button
                                            type="button"
                                            class="gestor-btn gestor-btn--ok"
                                            wire:click="aprovarAparelho('{{ $ap['origem'] }}', {{ $ap['id'] }})"
                                            wire:loading.attr="disabled"
                                            @disabled(! $this->podeAprovarAparelhos($ap['origem']))
                                        >
                                            ✔ Autorizar
                                        </button>
                                        <button
                                            type="button"
                                            class="gestor-btn gestor-btn--danger"
                                            wire:click="rejeitarAparelho('{{ $ap['origem'] }}', {{ $ap['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:confirm="Rejeitar este aparelho?"
                                            @disabled(! $this->podeRejeitarAparelhos($ap['origem']))
                                        >
                                            ✖ Rejeitar
                                        </button>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endif
        </div>

        @include('filament.gestor.partials.bottom-nav')
    </div>
</x-filament-panels::page>
