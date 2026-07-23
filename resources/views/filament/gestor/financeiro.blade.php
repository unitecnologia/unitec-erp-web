<x-filament-panels::page>
    @php
        $f = $fin;
        $saldo = (float) ($f['saldo'] ?? 0);
        $saldoNeg = $saldo < 0;
        $varPct = $f['saldo_variacao_pct'] ?? null;
        $saude = $f['saude'] ?? ['percent' => 0, 'tone' => 'gray', 'short' => '—', 'message' => ''];
        $saudePct = (float) ($saude['percent'] ?? 0);
        $serie = $f['serie_7d'] ?? [];
        $maxAbs = max(1.0, ...array_map(fn ($p) => abs((float) ($p['valor'] ?? 0)), $serie ?: [['valor' => 1]]));
        $proj = $f['projecao'] ?? [];
        $hoje = $f['hoje'] ?? ['entradas' => 0, 'saidas' => 0, 'resultado' => 0];
        $resultadoHoje = (float) ($hoje['resultado'] ?? 0);
        $aprovUrl = \App\Filament\Gestor\Pages\AprovacoesGestorPage::getUrl(panel: 'gestor');
        $aberto = filled($this->detalheTipo);
    @endphp

    <div class="gestor-shell" data-theme="{{ $this->gestorTema }}">
        <div class="gestor-shell__inner" wire:key="fin-root-{{ $this->detalheTipo ?: 'home' }}">
            @include('filament.gestor.partials.top', [
                'title' => $aberto ? $this->detalheTitulo() : 'Financeiro',
                'subtitle' => trim(($this->empresaNome() ?: 'Empresa').' · '.($f['data_label'] ?? now()->format('d M Y'))),
                'eyebrow' => $aberto
                    ? (in_array($this->detalheTipo, ['inadimplencia', 'acima_limite'], true)
                        ? ($this->detalheTipo === 'acima_limite' ? 'Clientes acima do limite' : 'Clientes inadimplentes')
                        : 'Detalhe dos títulos')
                    : 'Caixa e títulos',
                'refresh' => $aberto ? null : 'refreshFinanceiro',
                'notify_url' => $aprovUrl,
            ])

            @if ($aberto)
                <button type="button" class="gestor-back" wire:click="fecharDetalhe" wire:loading.attr="disabled">
                    ← Voltar ao financeiro
                </button>

                @php
                    $listaClientes = in_array($this->detalheTipo, ['inadimplencia', 'acima_limite'], true);
                    $mostraPagar = in_array($this->detalheTipo, ['pagar_hoje', 'pagar_vencido'], true) && $this->podePagarTitulos();
                    $mostraReceber = in_array($this->detalheTipo, ['receber_hoje', 'receber_vencido', 'proximos_receber'], true) && $this->podeReceberTitulos();
                    $mostraAcao = $mostraPagar || $mostraReceber;
                @endphp
                <div class="gestor-edit__card {{ $listaClientes ? 'gestor-edit__card--lista' : '' }}" wire:loading.class="is-loading">
                    @if (empty($detalheItens))
                        <p class="gestor-empty">
                            @if ($this->detalheTipo === 'acima_limite')
                                Nenhum cliente acima do limite.
                            @elseif ($listaClientes)
                                Nenhum cliente inadimplente.
                            @else
                                Nenhum título neste filtro.
                            @endif
                        </p>
                    @else
                        <p class="gestor-note {{ $listaClientes ? 'gestor-note--compact' : '' }}">
                            {{ count($detalheItens) }}
                            {{ $listaClientes ? 'cliente(s)' : 'título(s)' }}
                        </p>
                        <div class="gestor-fin-table {{ $listaClientes ? 'gestor-fin-table--compact' : '' }}">
                            @unless ($listaClientes)
                                <div class="gestor-fin-table__head {{ $mostraAcao ? 'gestor-fin-table__head--pagar' : '' }}">
                                    <span>Cliente / Fornecedor</span>
                                    <span>Valor</span>
                                    @if ($mostraAcao)
                                        <span>Ação</span>
                                    @endif
                                </div>
                            @endunless
                            @foreach ($detalheItens as $t)
                                @php $vencido = ! empty($t['vencido']) || ($t['situacao'] ?? '') === 'Vencido'; @endphp
                                @if ($listaClientes)
                                    <div class="gestor-fin-table__row gestor-fin-table__row--cliente {{ $vencido ? 'is-vencido' : '' }}">
                                        <div class="gestor-fin-table__cliente">
                                            <strong>{{ $t['pessoa'] }}</strong>
                                            <small>
                                                @if ($this->detalheTipo === 'acima_limite')
                                                    {{ $t['situacao'] }} · {{ $t['vencimento'] }}
                                                @else
                                                    {{ (int) ($t['qtd'] ?? 0) }} tit. · desde {{ $t['vencimento'] }}
                                                @endif
                                            </small>
                                        </div>
                                        <span class="gestor-fin-table__valor {{ $vencido ? 'is-neg' : '' }}">{{ $this->money((float) ($t['valor'] ?? 0)) }}</span>
                                    </div>
                                @else
                                    <div class="gestor-fin-table__row {{ $vencido ? 'is-vencido' : '' }} {{ $mostraAcao ? 'gestor-fin-table__row--pagar' : '' }}">
                                        <div>
                                            <strong>{{ $t['pessoa'] }}</strong>
                                            <small>
                                                {{ $t['documento'] }} · {{ $t['vencimento'] }} ·
                                                <span class="{{ $vencido ? 'is-neg' : '' }}">{{ $t['situacao'] }}</span>
                                            </small>
                                        </div>
                                        <span class="{{ $vencido ? 'is-neg' : '' }}">{{ $this->money((float) ($t['valor'] ?? 0)) }}</span>
                                        @if (! empty($t['pode_pagar']) && $mostraPagar)
                                            <button
                                                type="button"
                                                class="gestor-btn gestor-btn--ok gestor-btn--sm"
                                                wire:click.prevent="abrirPagamento({{ (int) $t['id'] }})"
                                                wire:loading.attr="disabled"
                                            >
                                                Pagar
                                            </button>
                                        @elseif (! empty($t['pode_receber']) && $mostraReceber)
                                            <button
                                                type="button"
                                                class="gestor-btn gestor-btn--ok gestor-btn--sm"
                                                wire:click.prevent="abrirRecebimento({{ (int) $t['id'] }})"
                                                wire:loading.attr="disabled"
                                            >
                                                Receber
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($pagamentoModalOpen)
                    <div class="gestor-sheet is-open" wire:click.self="fecharPagamentoModal" role="dialog" aria-modal="true">
                        <div class="gestor-sheet__panel">
                            <div class="gestor-sheet__head">
                                <h2>{{ $this->baixaModo === 'receber' ? 'Confirmar recebimento' : 'Confirmar pagamento' }}</h2>
                                <button type="button" class="gestor-icon-btn" wire:click="fecharPagamentoModal" aria-label="Fechar">×</button>
                            </div>
                            <p class="gestor-note">{{ $pagamentoResumoPessoa }}</p>
                            <p class="gestor-fin-pay__valor {{ $this->baixaModo === 'receber' ? 'is-receber' : '' }}">R$ {{ $pagamentoResumoValor }}</p>
                            <div class="gestor-field">
                                <label class="gestor-field__label" for="gestor-forma-pag">Meio de pagamento</label>
                                <select id="gestor-forma-pag" class="gestor-field__input" wire:model="pagamentoFormaId">
                                    @foreach ($pagamentoFormas as $forma)
                                        <option value="{{ $forma['id'] }}">{{ $forma['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="gestor-aprov__actions">
                                <button type="button" class="gestor-btn gestor-btn--danger" wire:click="fecharPagamentoModal">Cancelar</button>
                                <button type="button" class="gestor-btn gestor-btn--ok" wire:click="confirmarPagamento" wire:loading.attr="disabled">
                                    Confirmar
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                @php
                    $fluxo = (float) ($f['fluxo_previsto_hoje'] ?? 0);
                    $cardsHoje = [
                        ['tipo' => 'receber_hoje', 'label' => 'Receber hoje', 'data' => $f['receber_hoje'] ?? [], 'tone' => 'ok'],
                        ['tipo' => 'pagar_hoje', 'label' => 'Pagar hoje', 'data' => $f['pagar_hoje'] ?? [], 'tone' => 'info'],
                    ];
                    $cardsPend = [
                        ['tipo' => 'receber_vencido', 'label' => 'Receber vencido', 'data' => $f['receber_vencido'] ?? [], 'tone' => 'alert'],
                        ['tipo' => 'pagar_vencido', 'label' => 'Pagar vencido', 'data' => $f['pagar_vencido'] ?? [], 'tone' => 'warn'],
                    ];
                    $mes = $f['mes'] ?? ['entradas' => 0, 'saidas' => 0];
                    $mesAnt = $f['mes_anterior'] ?? ['entradas' => 0, 'saidas' => 0];
                    $ontem = $f['ontem'] ?? ['resultado' => 0];
                    $cmp = [
                        ['label' => 'Hoje', 'valor' => (float) ($hoje['resultado'] ?? 0)],
                        ['label' => 'Ontem', 'valor' => (float) ($ontem['resultado'] ?? 0)],
                        ['label' => 'Este mês', 'valor' => (float) ($mes['entradas'] ?? 0) - (float) ($mes['saidas'] ?? 0)],
                        ['label' => 'Mês ant.', 'valor' => (float) ($mesAnt['entradas'] ?? 0) - (float) ($mesAnt['saidas'] ?? 0)],
                    ];
                @endphp

                {{-- Bloco 1: Caixa --}}
                <section class="gestor-fin-block" aria-label="Caixa">
                    <div class="gestor-fin-hero {{ $saldoNeg ? 'is-neg' : 'is-pos' }}">
                        <div class="gestor-fin-hero__main">
                            <p class="gestor-fin-hero__label">Saldo atual</p>
                            <p class="gestor-fin-hero__value">
                                <span class="gestor-fin-hero__dot" aria-hidden="true"></span>
                                {{ $this->money($saldo) }}
                            </p>
                            <p class="gestor-fin-hero__meta">Atualizado às {{ $f['atualizado_em'] ?? '—' }}</p>
                        </div>
                        @if ($varPct !== null)
                            <div class="gestor-fin-hero__trend {{ $varPct >= 0 ? 'is-up' : 'is-down' }}">
                                <span>{{ $varPct >= 0 ? '↗' : '↘' }}</span>
                                <strong>{{ ($varPct >= 0 ? '+' : '').number_format((float) $varPct, 0) }}%</strong>
                                <small>vs ontem</small>
                            </div>
                        @endif
                    </div>

                    <div class="gestor-fin-chart">
                        <div class="gestor-fin-chart__head">
                            <h2>Evolução do saldo</h2>
                            <span>7 dias</span>
                        </div>
                        <div class="gestor-spark" role="img" aria-label="Gráfico de saldo">
                            @foreach ($serie as $ponto)
                                @php
                                    $v = (float) ($ponto['valor'] ?? 0);
                                    $h = max(8, (int) round((abs($v) / $maxAbs) * 100));
                                    $cls = $v < 0 ? 'is-neg' : 'is-pos';
                                @endphp
                                <div class="gestor-spark__col">
                                    <div class="gestor-spark__bar {{ $cls }}" style="--h: {{ $h }}%" title="{{ $ponto['dia'] ?? '' }}: {{ $this->money($v) }}"></div>
                                    <span>{{ $ponto['label'] ?? '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- Bloco 2: Atenção --}}
                <section class="gestor-fin-block">
                    <div class="gestor-section__head">
                        <h2>Atenção hoje</h2>
                    </div>
                    @if (($proj['tom'] ?? 'ok') !== 'ok' || $saldoNeg)
                        <div class="gestor-fin-smart gestor-fin-smart--{{ $proj['tom'] ?? 'warning' }}">
                            <p class="gestor-fin-smart__title">⚠ {{ ($proj['tom'] ?? '') === 'danger' ? 'Crítico' : 'Atenção' }}</p>
                            <p class="gestor-fin-smart__msg">{{ $proj['mensagem'] ?? '' }}</p>
                            <div class="gestor-fin-smart__grid">
                                <div>
                                    <span>Receber (7d)</span>
                                    <strong>{{ $this->money((float) ($proj['receber_7d'] ?? 0)) }}</strong>
                                </div>
                                <div>
                                    <span>Pagar (7d)</span>
                                    <strong>{{ $this->money((float) ($proj['pagar_7d'] ?? 0)) }}</strong>
                                </div>
                            </div>
                        </div>
                    @endif
                    <ul class="gestor-pulse">
                        @foreach (($f['atencao'] ?? []) as $item)
                            <li class="gestor-pulse__item gestor-pulse__item--{{ $item['tom'] ?? 'info' }}">
                                <div>
                                    <p class="gestor-pulse__title">{{ $item['texto'] ?? '' }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Bloco 3: Hoje --}}
                <section class="gestor-fin-block" aria-label="Movimento de hoje">
                    <div class="gestor-section__head">
                        <h2>Hoje</h2>
                    </div>
                    <div class="gestor-fin-resumo">
                        <div class="gestor-fin-resumo__row">
                            <div>
                                <span>Entradas</span>
                                <strong class="is-pos">{{ $this->money((float) ($hoje['entradas'] ?? 0)) }}</strong>
                            </div>
                            <div>
                                <span>Saídas</span>
                                <strong class="is-neg">{{ $this->money((float) ($hoje['saidas'] ?? 0)) }}</strong>
                            </div>
                            <div>
                                <span>Resultado</span>
                                <strong class="{{ $resultadoHoje >= 0 ? 'is-pos' : 'is-neg' }}">
                                    {{ $resultadoHoje < 0 ? '− ' : '' }}{{ $this->money(abs($resultadoHoje)) }}
                                </strong>
                            </div>
                        </div>
                    </div>
                    <div class="gestor-grid gestor-grid--2 gestor-grid--fin">
                        @foreach ($cardsHoje as $card)
                            @include('filament.gestor.partials.fin-card', ['card' => $card])
                        @endforeach
                    </div>
                </section>

                {{-- Bloco 4: Pendências --}}
                <section class="gestor-fin-block" aria-label="Pendências">
                    <div class="gestor-section__head">
                        <h2>Pendências</h2>
                    </div>
                    <div class="gestor-grid gestor-grid--2 gestor-grid--fin">
                        @foreach ($cardsPend as $card)
                            @include('filament.gestor.partials.fin-card', ['card' => $card])
                        @endforeach
                        <button type="button" class="gestor-card gestor-card--alert gestor-card--tap" wire:click.prevent="abrirDetalhe('inadimplencia')" wire:loading.attr="disabled">
                            <div class="gestor-card__body">
                                <span class="gestor-kicker">Inadimplência</span>
                                <span class="gestor-card__meta">{{ (int) ($f['inadimplencia']['clientes'] ?? 0) }} clientes</span>
                                <span class="gestor-card__value is-neg">{{ $this->money((float) ($f['inadimplencia']['valor'] ?? 0)) }}</span>
                            </div>
                            <span class="gestor-card__chev" aria-hidden="true">›</span>
                        </button>
                        <button type="button" class="gestor-card gestor-card--warn gestor-card--tap" wire:click.prevent="abrirDetalhe('acima_limite')" wire:loading.attr="disabled">
                            <div class="gestor-card__body">
                                <span class="gestor-kicker">Acima do limite</span>
                                <span class="gestor-card__meta">{{ (int) ($f['acima_limite']['qtd'] ?? 0) }} clientes</span>
                                <span class="gestor-card__value">{{ $this->money((float) ($f['acima_limite']['valor'] ?? 0)) }}</span>
                            </div>
                            <span class="gestor-card__chev" aria-hidden="true">›</span>
                        </button>
                    </div>
                </section>

                {{-- Bloco 5: Indicadores --}}
                <section class="gestor-fin-block" aria-label="Indicadores">
                    <div class="gestor-section__head">
                        <h2>Indicadores</h2>
                    </div>
                    <div class="gestor-grid gestor-grid--2 gestor-grid--fin">
                        <button
                            type="button"
                            class="gestor-card gestor-card--tap gestor-card--{{ $saude['tone'] ?? 'gray' }}"
                            wire:click.prevent="abrirSaudeDetalhe"
                            wire:loading.attr="disabled"
                        >
                            <div class="gestor-card__body">
                                <span class="gestor-kicker">Saúde financeira</span>
                                <span class="gestor-card__value">{{ number_format($saudePct, 0) }}%</span>
                                <span class="gestor-card__hint">{{ $saude['short'] ?? '' }}</span>
                            </div>
                            <span class="gestor-card__chev" aria-hidden="true">›</span>
                        </button>
                        <article class="gestor-card gestor-card--info">
                            <span class="gestor-kicker">Fluxo previsto</span>
                            <span class="gestor-card__value {{ $fluxo >= 0 ? 'is-pos' : 'is-neg' }}">
                                {{ ($fluxo >= 0 ? '+' : '').$this->money($fluxo) }}
                            </span>
                            <span class="gestor-card__hint">Títulos de hoje</span>
                        </article>
                    </div>
                    <div class="gestor-fin-cmp">
                        @foreach ($cmp as $c)
                            <div class="gestor-fin-cmp__item">
                                <span>{{ $c['label'] }}</span>
                                <strong class="{{ $c['valor'] >= 0 ? 'is-pos' : 'is-neg' }}">{{ $this->money($c['valor']) }}</strong>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Bloco 6: Próximas --}}
                <section class="gestor-fin-block">
                    <div class="gestor-section__head">
                        <h2>Próximas a vencer</h2>
                    </div>
                    @if (empty($f['proximos']))
                        <p class="gestor-empty">Nenhum título próximo nos próximos dias.</p>
                    @else
                        <ul class="gestor-fin-next">
                            @foreach ($f['proximos'] as $p)
                                <li>
                                    <div>
                                        <strong>{{ $p['pessoa'] }}</strong>
                                        <small>{{ ($p['tipo'] ?? '') === 'pagar' ? 'Pagar' : 'Receber' }} · {{ $p['vencimento'] }}</small>
                                    </div>
                                    <span class="{{ ($p['tipo'] ?? '') === 'pagar' ? 'is-neg' : 'is-pos' }}">
                                        {{ $this->money((float) ($p['valor'] ?? 0)) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            @if ($this->saudeDetalheOpen)
                @php
                    $saudeFactors = is_array($saude['factors'] ?? null) ? $saude['factors'] : [];
                @endphp
                <div class="gestor-sheet is-open" wire:click.self="fecharSaudeDetalhe" role="dialog" aria-modal="true" aria-labelledby="gestor-saude-title">
                    <div class="gestor-sheet__panel gestor-sheet__panel--saude">
                        <div class="gestor-sheet__head">
                            <h2 id="gestor-saude-title">Saúde da empresa — detalhe</h2>
                            <button type="button" class="gestor-icon-btn" wire:click="fecharSaudeDetalhe" aria-label="Fechar">×</button>
                        </div>

                        <div class="gestor-saude-summary gestor-saude-summary--{{ $saude['tone'] ?? 'gray' }}">
                            <strong>{{ number_format($saudePct, 1, ',', '') }}%</strong>
                            <span>{{ $saude['label'] ?? $saude['short'] ?? '—' }}</span>
                            <p>{{ $saude['message'] ?? '' }}</p>
                        </div>

                        <div class="gestor-saude-factors">
                            @forelse ($saudeFactors as $factor)
                                @php
                                    $fTone = $factor['tone'] ?? 'gray';
                                    $fPct = max(0, min(100, (float) ($factor['percent'] ?? 0)));
                                @endphp
                                <div class="gestor-saude-factor gestor-saude-factor--{{ $fTone }}">
                                    <div class="gestor-saude-factor__row">
                                        <span class="gestor-saude-factor__label">{{ $factor['label'] ?? '—' }}</span>
                                        <span class="gestor-saude-factor__pct">{{ number_format($fPct, 1, ',', '') }}%</span>
                                        <span class="gestor-saude-factor__weight">peso {{ (int) ($factor['weight'] ?? 0) }}</span>
                                    </div>
                                    <div class="gestor-saude-factor__bar" aria-hidden="true">
                                        <span style="width: {{ $fPct }}%;"></span>
                                    </div>
                                    @if (filled($factor['hint'] ?? null))
                                        <p class="gestor-saude-factor__hint">{{ $factor['hint'] }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="gestor-empty">Sem fatores para exibir.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>

        @include('filament.gestor.partials.bottom-nav')
    </div>
</x-filament-panels::page>
