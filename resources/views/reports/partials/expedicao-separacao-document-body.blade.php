@php
    use App\Support\Erp\Reports\ExpedicaoSeparacaoReport;

    $columnTotals = ExpedicaoSeparacaoReport::columnTotals($linhas, $columns);
    $hasRows = count($linhas) > 0;
@endphp
<style>
    .exp-separacao__corredor td {
        background: #d1d5db !important;
        color: #111827 !important;
        font-weight: 800;
        font-size: 0.72rem;
        letter-spacing: 0.04em;
        text-align: center;
        padding: 0.22rem 0.4rem !important;
        border-top: 2px solid #6b7280 !important;
        border-bottom: 1px solid #9ca3af !important;
    }
    .exp-separacao__pedido td {
        background: linear-gradient(180deg, #dcfce7 0%, #bbf7d0 100%) !important;
        color: #14532d !important;
        font-weight: 800;
        font-size: 0.72rem;
        letter-spacing: 0.02em;
        text-align: left;
        padding: 0.28rem 0.45rem !important;
        border-top: 2px solid #16a34a !important;
        border-bottom: 1px solid #22c55e !important;
    }
</style>
<div class="pessoa-list-doc">
    <div class="pessoa-list-doc__frame">
        <div class="pessoa-list-doc__header">
            <div class="pessoa-list-doc__logo-cell">
                <div class="pessoa-list-doc__logo">
                    @if (filled($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="pessoa-list-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="pessoa-list-doc__company-cell">
                <span class="pessoa-list-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
                @if (filled($empresa?->responsavel))
                    <span>{{ mb_strtoupper($empresa->responsavel, 'UTF-8') }}<br></span>
                @endif
                @if (filled($empresaEndereco))
                    <span>{{ $empresaEndereco }}<br></span>
                @endif
                <span>
                    FONE: {{ $empresa?->telefone ?: '' }}&nbsp;&nbsp;EMAIL: {{ $empresa?->email ?: '' }}
                </span>
            </div>
        </div>

        <hr class="pessoa-list-doc__rule">

        <div class="pessoa-list-doc__title">{{ $reportTitle }}</div>

        <div class="pessoa-list-doc__filters">
            <span>| {{ $pedidosSummary }}</span>
        </div>

        <table class="pessoa-list-doc__table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ ExpedicaoSeparacaoReport::isNumericColumn($column) ? 'num' : '' }}">
                            {{ $columnLabels[$column] ?? $column }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $linha)
                    @if (ExpedicaoSeparacaoReport::isPedidoSeparatorRow($linha))
                        <tr class="exp-separacao__pedido">
                            <td colspan="{{ count($columns) }}">{{ $linha['label'] ?? 'PEDIDO' }}</td>
                        </tr>
                    @elseif (ExpedicaoSeparacaoReport::isCorredorSeparatorRow($linha))
                        <tr class="exp-separacao__corredor">
                            <td colspan="{{ count($columns) }}">{{ $linha['label'] ?? 'CORREDOR' }}</td>
                        </tr>
                    @else
                        <tr>
                            @foreach ($columns as $column)
                                <td class="{{ ExpedicaoSeparacaoReport::isNumericColumn($column) ? 'num' : '' }} {{ $column === 'descricao' ? 'nome' : '' }}">
                                    {{ ExpedicaoSeparacaoReport::cellValue($linha, $column) }}
                                </td>
                            @endforeach
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="pessoa-list-doc__empty">Nenhum item para separar.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($hasRows)
                <tfoot>
                    <tr class="pessoa-list-doc__totals">
                        @foreach ($columns as $column)
                            <td class="{{ ExpedicaoSeparacaoReport::isNumericColumn($column) ? 'num' : '' }}">
                                {{ $columnTotals[$column] }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="pessoa-list-doc__footer">
            <span>Relatório emitido em {{ $printedAt->format('d/m/Y - H:i:s') }}</span>
            <span class="pessoa-list-doc__footer-page">Pág. 1</span>
        </div>
    </div>
</div>
