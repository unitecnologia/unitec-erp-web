<x-filament-panels::page>
@php
    $pageClass = 'erp-rh-treinamentos';
    $r = $this->gerado ? $this->resultado : [];
    $bars = is_array($r['bars'] ?? null) ? $r['bars'] : [];
    $resumo = is_array($r['resumo'] ?? null) ? $r['resumo'] : [];
    $barCount = count($bars);
@endphp

<div
    class="{{ $pageClass }} erp-faturamento-mensal"
    wire:keydown.f5.window.prevent="gerar"
    wire:keydown.escape.window="onEscape"
>
    <div class="{{ $pageClass }}__filters">
        <div class="{{ $pageClass }}__filters-row">
            <label class="erp-conciliacao__inline">
                <span class="{{ $pageClass }}__locate-label">Empresa</span>
                <span class="erp-faturamento-mensal__empresa">{{ $this->empresaLabel() }}</span>
            </label>

            <label class="erp-conciliacao__inline">
                <span class="{{ $pageClass }}__locate-label">Ano</span>
                <select wire:model.live="ano" class="{{ $pageClass }}__input erp-faturamento-mensal__select" aria-label="Ano">
                    @foreach ($this->anosOptions() as $anoOpt)
                        <option value="{{ $anoOpt }}">{{ $anoOpt }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-conciliacao__inline">
                <span class="{{ $pageClass }}__locate-label">Mês</span>
                <select wire:model.live="mes" class="{{ $pageClass }}__input erp-faturamento-mensal__select erp-faturamento-mensal__select--mes" aria-label="Mês">
                    @foreach ($this->mesesOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="{{ $pageClass }}__search-actions">
                <button
                    type="button"
                    wire:click="gerar"
                    class="{{ $pageClass }}__btn"
                    data-erp-key="F5"
                    wire:loading.attr="disabled"
                    wire:target="gerar,ano,mes"
                >
                    <span wire:loading.remove wire:target="gerar,ano,mes"><kbd>F5</kbd> | Gerar</span>
                    <span wire:loading wire:target="gerar,ano,mes">Gerando…</span>
                </button>
            </div>

            <button
                type="button"
                wire:click="sair"
                class="{{ $pageClass }}__btn erp-faturamento-mensal__btn-sair"
                data-erp-key="ESC"
                title="ESC | Sair"
            >
                <kbd>ESC</kbd> | Sair
            </button>
        </div>
    </div>

    <p class="{{ $pageClass }}__hint">
        Vendas fechadas da empresa logada (valor final). Pedidos, NFC-e e NF-e vinculados não são somados em separado.
    </p>

    @if ($this->erro !== '')
        <p class="{{ $pageClass }}__hint erp-faturamento-mensal__erro">{{ $this->erro }}</p>
    @endif

    @if ($this->gerado)
        <div class="erp-faturamento-mensal__cards" aria-label="Resumo do faturamento">
            <div class="erp-faturamento-mensal__card">
                <span class="erp-faturamento-mensal__card-label">Faturamento total</span>
                <strong class="erp-faturamento-mensal__card-value">{{ $resumo['faturamento_total_fmt'] ?? 'R$ 0,00' }}</strong>
            </div>
            <div class="erp-faturamento-mensal__card">
                <span class="erp-faturamento-mensal__card-label">Média mensal</span>
                <strong class="erp-faturamento-mensal__card-value">{{ $resumo['media_mensal_fmt'] ?? 'R$ 0,00' }}</strong>
            </div>
            <div class="erp-faturamento-mensal__card">
                <span class="erp-faturamento-mensal__card-label">Melhor mês</span>
                <strong class="erp-faturamento-mensal__card-value">{{ $resumo['melhor_mes_total_fmt'] ?? 'R$ 0,00' }}</strong>
                <em class="erp-faturamento-mensal__card-hint">{{ $resumo['melhor_mes'] ?? '—' }}</em>
            </div>
        </div>

        <div class="erp-faturamento-mensal__meta">
            {{ (int) ($resumo['qtd_vendas'] ?? 0) }} venda(s)
            · {{ $this->mes === '' ? '12 meses' : '1 mês' }}
            · {{ (int) ($r['ano'] ?? $this->ano) }}
        </div>

        <div
            class="erp-faturamento-mensal__chart{{ $barCount === 1 ? ' erp-faturamento-mensal__chart--single' : '' }}"
            role="img"
            aria-label="Gráfico de faturamento por mês"
            wire:loading.class="is-loading"
            wire:target="gerar,ano,mes"
        >
            @forelse ($bars as $bar)
                <div class="erp-faturamento-mensal__bar" title="{{ ($bar['label'] ?? '').': '.($bar['total_fmt'] ?? '') }}">
                    <span class="erp-faturamento-mensal__bar-value">{{ $bar['total_fmt'] ?? '' }}</span>
                    <div
                        class="erp-faturamento-mensal__bar-fill"
                        style="height: {{ max(2, (float) ($bar['pct'] ?? 0)) }}%;"
                    ></div>
                    <span class="erp-faturamento-mensal__bar-label">{{ $bar['label'] ?? '' }}</span>
                </div>
            @empty
                <p class="erp-faturamento-mensal__empty">Sem faturamento no período.</p>
            @endforelse
        </div>

        @if ($bars !== [])
            <div class="erp-faturamento-mensal__table-wrap">
                <table class="erp-faturamento-mensal__table">
                    <thead>
                        <tr>
                            <th>Mês</th>
                            <th class="num">Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bars as $bar)
                            <tr>
                                <td>{{ $bar['label'] ?? '' }}</td>
                                <td class="num">{{ $bar['total_fmt'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="num">{{ $resumo['faturamento_total_fmt'] ?? 'R$ 0,00' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    @endif
</div>
</x-filament-panels::page>
