<div class="tabular-doc">
    <div class="tabular-doc__frame">
        <div class="tabular-doc__header">
            <div class="tabular-doc__logo-cell">
                <div class="tabular-doc__logo">
                    @if (filled($logoDataUri ?? null))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="tabular-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="tabular-doc__company-cell">
                <span class="tabular-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
                @if (filled($empresa?->responsavel))
                    <span>{{ mb_strtoupper($empresa->responsavel, 'UTF-8') }}<br></span>
                @endif
                @if (filled($empresaEndereco ?? null))
                    <span>{{ $empresaEndereco }}<br></span>
                @endif
                <span>
                    FONE: {{ $empresa?->telefone ?: '' }}&nbsp;&nbsp;EMAIL: {{ $empresa?->email ?: '' }}
                </span>
            </div>
        </div>

        <hr class="tabular-doc__rule">

        <div class="tabular-doc__title">{{ $reportTitle }}</div>

        @if (! empty($summary))
            <div class="tabular-doc__filters">
                @foreach ($summary as $item)
                    <span>| {{ mb_strtoupper($item, 'UTF-8') }}</span>
                @endforeach
            </div>
        @endif

        @php
            $hideMainTable = (bool) ($reportMeta['hide_main_table'] ?? false);
            $sectionsBefore = (bool) ($reportMeta['extra_sections_before'] ?? false);
        @endphp

        @if ($sectionsBefore)
            @include('reports.partials.tabular-extra-sections')
        @endif

        @unless ($hideMainTable)
        <table class="tabular-doc__table{{ ! empty($columnWidths) ? ' tabular-doc__table--compact' : '' }}">
            @if (! empty($columnWidths))
                <colgroup>
                    @foreach ($columns as $column)
                        <col @if (! empty($columnWidths[$column])) style="width: {{ $columnWidths[$column] }}" @endif>
                    @endforeach
                </colgroup>
            @endif
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ trim((in_array($column, $numericColumns ?? [], true) ? 'num' : '').' '.(in_array($column, $nowrapColumns ?? [], true) ? 'col-nowrap' : '')) }}">
                            {{ $columnLabels[$column] ?? $column }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $rowIndex => $row)
                    @php
                        $expandMeta = (! empty($supportsRowExpand) && is_array($row['_expand'] ?? null))
                            ? $row['_expand']
                            : null;
                    @endphp
                    <tr
                        @if ($expandMeta)
                            class="tabular-doc__data-row"
                            data-expand-row="{{ $rowIndex }}"
                        @endif
                    >
                        @foreach ($columns as $column)
                            @php
                                $cellClass = trim(
                                    (in_array($column, $numericColumns ?? [], true) ? 'num' : '')
                                    .' '
                                    .(in_array($column, $nowrapColumns ?? [], true) ? 'col-nowrap' : '')
                                );
                            @endphp
                            <td class="{{ $cellClass }}">
                                @if ($expandMeta && $column === 'qtd' && (($expandMeta['mode'] ?? '') !== 'produtos'))
                                    <button
                                        type="button"
                                        class="tabular-doc__qtd-toggle"
                                        data-expand-toggle
                                        data-expand='@json($expandMeta)'
                                        aria-expanded="false"
                                        title="Ver vendas deste vendedor"
                                    >{{ $row[$column] ?? '' }}</button>
                                @elseif ($expandMeta && $column === 'numero' && (($expandMeta['mode'] ?? '') === 'produtos'))
                                    <button
                                        type="button"
                                        class="tabular-doc__qtd-toggle"
                                        data-expand-toggle
                                        data-expand='@json($expandMeta)'
                                        aria-expanded="false"
                                        title="Ver produtos desta venda"
                                    >{{ $row[$column] ?? '' }}</button>
                                @else
                                    {{ $row[$column] ?? '' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @if (! empty($row['_items']) && is_array($row['_items']))
                        <tr class="tabular-doc__items-row">
                            <td colspan="{{ max(1, count($columns)) }}">
                                <table class="tabular-doc__detail">
                                    <thead>
                                        <tr>
                                            <th>CÓDIGO</th>
                                            <th>DESCRIÇÃO</th>
                                            <th>GRUPO</th>
                                            <th class="num">QTD</th>
                                            <th class="num">UNIT.</th>
                                            <th class="num">TOTAL ITEM</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($row['_items'] as $item)
                                            <tr>
                                                <td>{{ $item['codigo'] ?? '' }}</td>
                                                <td>{{ $item['descricao'] ?? '' }}</td>
                                                <td>{{ $item['grupo'] ?? '' }}</td>
                                                <td class="num">{{ $item['qtd'] ?? '' }}</td>
                                                <td class="num">{{ $item['unitario'] ?? '' }}</td>
                                                <td class="num">{{ $item['total'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                    @if (! empty($row['_pagamentos']) && is_array($row['_pagamentos']))
                        <tr class="tabular-doc__pagamentos-row">
                            <td colspan="{{ max(1, count($columns)) }}">
                                <div class="tabular-doc__pagamentos">
                                    <table class="tabular-doc__pagamentos-table">
                                        <thead>
                                            <tr>
                                                <th>Forma Pgto</th>
                                                <th class="num">Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($row['_pagamentos'] as $pag)
                                                <tr>
                                                    <td>{{ $pag['forma'] ?? '' }}</td>
                                                    <td class="num">{{ $pag['valor'] ?? '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="{{ max(1, count($columns)) }}" class="tabular-doc__empty">
                            {{ $emptyMessage ?? 'Nenhum registro encontrado.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if (! empty($rows) && ! empty($totals))
                <tfoot>
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ trim((in_array($column, $numericColumns ?? [], true) ? 'num' : '').' '.(in_array($column, $nowrapColumns ?? [], true) ? 'col-nowrap' : '').' tabular-doc__strong') }}">
                                {{ $totals[$column] ?? '' }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
        @endunless

        @unless ($sectionsBefore)
            @include('reports.partials.tabular-extra-sections')
        @endunless

        @php
            $dailyChart = is_array($reportMeta['daily_chart'] ?? null) ? $reportMeta['daily_chart'] : null;
            $dailyRows = is_array($dailyChart['rows'] ?? null) ? $dailyChart['rows'] : [];
            $chartOnly = (bool) ($reportMeta['chart_only'] ?? false);
            $showDailyBars = count($dailyRows) > 0 && count($dailyRows) <= 62;
            $labelStep = count($dailyRows) > 31 ? 2 : 1;
            if (count($dailyRows) > 50) {
                $labelStep = 3;
            }
        @endphp
        @if ($dailyChart && $dailyRows !== [])
            <div class="tabular-doc__chart-page{{ $chartOnly ? ' tabular-doc__chart-page--only' : '' }}">
                <div class="tabular-doc__section-title">{{ $dailyChart['title'] ?? 'Análise Gráfica Diária' }}</div>
                <div class="tabular-doc__section-note">
                    Período: {{ $dailyChart['period_label'] ?? '' }}
                    @if (! empty($dailyChart['clamped']))
                        — exibição limitada aos últimos 93 dias do filtro
                    @endif
                </div>

                @if ($showDailyBars)
                    <div class="tabular-doc__chart-bars" role="img" aria-label="Gráfico de colunas diário">
                        @foreach ($dailyRows as $i => $bar)
                            <div class="tabular-doc__chart-bar">
                                <span class="tabular-doc__chart-bar-value">{{ $bar['valor'] ?? '' }}</span>
                                <div class="tabular-doc__chart-bar-fill" style="height: {{ max(1, (float) ($bar['pct'] ?? 0)) }}%;"></div>
                                <span class="tabular-doc__chart-bar-label{{ ($i % $labelStep) === 0 ? '' : ' is-hidden' }}">{{ $bar['dia'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <table class="tabular-doc__table tabular-doc__table--summary tabular-doc__chart-table">
                    <thead>
                        <tr>
                            <th>Dia</th>
                            <th class="num">Total (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dailyRows as $bar)
                            <tr>
                                <td>{{ $bar['dia'] ?? '' }}</td>
                                <td class="num">{{ $bar['valor'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @php
            $hourly = is_array($reportMeta['hourly_summary'] ?? null) ? $reportMeta['hourly_summary'] : null;
            $hourlyRows = is_array($hourly['rows'] ?? null) ? $hourly['rows'] : [];
            $hourlyCharts = is_array($hourly['charts'] ?? null) ? $hourly['charts'] : [];
            $hourlyTotals = is_array($hourly['totals'] ?? null) ? $hourly['totals'] : [];
        @endphp
        @if ($hourly && $hourlyRows !== [])
            <div class="tabular-doc__chart-page{{ $chartOnly ? ' tabular-doc__chart-page--only' : '' }}">
                <div class="tabular-doc__section-title">{{ $hourly['title'] ?? 'Relatório de Vendas — Resumo por Hora' }}</div>
                @if (! empty($hourly['note']))
                    <div class="tabular-doc__section-note">{{ $hourly['note'] }}</div>
                @endif

                <table class="tabular-doc__table tabular-doc__table--summary">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th class="num">% Relativa</th>
                            <th class="num">Quantidade</th>
                            <th class="num">Valor de vendas</th>
                            <th class="num">Ticket médio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hourlyRows as $horaRow)
                            <tr>
                                <td>{{ $horaRow['hora'] ?? '' }}</td>
                                <td class="num">{{ $horaRow['percentual'] ?? '' }}</td>
                                <td class="num">{{ $horaRow['qtd'] ?? '' }}</td>
                                <td class="num">{{ $horaRow['valor'] ?? '' }}</td>
                                <td class="num">{{ $horaRow['ticket'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($hourlyTotals !== [])
                        <tfoot>
                            <tr>
                                <td class="tabular-doc__strong">{{ $hourlyTotals['hora'] ?? 'Total' }}</td>
                                <td class="num tabular-doc__strong">{{ $hourlyTotals['percentual'] ?? '' }}</td>
                                <td class="num tabular-doc__strong">{{ $hourlyTotals['qtd'] ?? '' }}</td>
                                <td class="num tabular-doc__strong">{{ $hourlyTotals['valor'] ?? '' }}</td>
                                <td class="num tabular-doc__strong">{{ $hourlyTotals['ticket'] ?? '' }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>

                @foreach (['qtd', 'valor', 'ticket'] as $chartKey)
                    @php
                        $chart = is_array($hourlyCharts[$chartKey] ?? null) ? $hourlyCharts[$chartKey] : null;
                        $chartLabels = is_array($chart['labels'] ?? null) ? $chart['labels'] : [];
                        $chartValues = is_array($chart['values'] ?? null) ? $chart['values'] : [];
                        $chartFormatted = is_array($chart['formatted'] ?? null) ? $chart['formatted'] : [];
                        $chartShort = is_array($chart['short'] ?? null) ? $chart['short'] : $chartFormatted;
                        $chartMax = max(0.00001, (float) ($chart['max'] ?? 0));
                    @endphp
                    @if ($chart && $chartLabels !== [])
                        <div class="tabular-doc__hourly-chart">
                            <div class="tabular-doc__section-title">{{ $chart['title'] ?? '' }}</div>
                            <div class="tabular-doc__hourly-scroll">
                                <div
                                    class="tabular-doc__chart-bars tabular-doc__chart-bars--hourly"
                                    role="img"
                                    aria-label="{{ $chart['title'] ?? 'Gráfico' }}"
                                    style="--hourly-cols: {{ max(1, count($chartLabels)) }};"
                                >
                                    <div class="tabular-doc__hourly-grid" aria-hidden="true">
                                        <span></span><span></span><span></span><span></span>
                                    </div>
                                    @foreach ($chartLabels as $i => $label)
                                        @php
                                            $raw = (float) ($chartValues[$i] ?? 0);
                                            $pct = max(2, round(($raw / $chartMax) * 100, 2));
                                            $full = (string) ($chartFormatted[$i] ?? '');
                                            $short = (string) ($chartShort[$i] ?? $full);
                                            $tip = trim($label.' — '.$full);
                                        @endphp
                                        <div
                                            class="tabular-doc__chart-bar tabular-doc__chart-bar--hourly"
                                            data-tip="{{ $tip }}"
                                            aria-label="{{ $tip }}"
                                        >
                                            <span class="tabular-doc__chart-bar-value tabular-doc__chart-bar-value--hourly">{{ $short }}</span>
                                            <div class="tabular-doc__chart-bar-track">
                                                <div class="tabular-doc__chart-bar-fill tabular-doc__chart-bar-fill--{{ $chartKey }}" style="height: {{ $pct }}%;"></div>
                                            </div>
                                            <span class="tabular-doc__chart-bar-label tabular-doc__chart-bar-label--hourly">{{ $label }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @elseif ($hourly && ($hourly['complete'] ?? true) === false)
            <div class="tabular-doc__chart-page">
                <div class="tabular-doc__section-title">{{ $hourly['title'] ?? 'Relatório de Vendas — Resumo por Hora' }}</div>
                <div class="tabular-doc__section-note">{{ $hourly['note'] ?? 'Sem horários válidos para montar o resumo.' }}</div>
            </div>
        @endif

        <div class="tabular-doc__meta">
            Impresso em {{ ($printedAt ?? now())->format('d/m/Y H:i') }}
            @if (! empty($reportMeta['truncated']))
                — LISTAGEM PARCIAL (totais do universo filtrado no cabeçalho)
            @endif
        </div>
    </div>
</div>
