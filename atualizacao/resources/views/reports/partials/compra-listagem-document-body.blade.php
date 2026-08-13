@php
    use App\Support\Erp\Reports\CompraListagemReport;

    $columnTotals = CompraListagemReport::columnTotals($compras, $columns);
    $hasCompras = count($compras) > 0;
@endphp
<div class="nfe-list-doc">
    <div class="nfe-list-doc__frame">
        <div class="nfe-list-doc__header">
            <div class="nfe-list-doc__logo-cell">
                <div class="nfe-list-doc__logo">
                    @if (filled($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="nfe-list-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="nfe-list-doc__company-cell">
                <span class="nfe-list-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
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

        <hr class="nfe-list-doc__rule">

        <div class="nfe-list-doc__title">{{ $reportTitle }}</div>

        <div class="nfe-list-doc__filters">
            <span>| SITUAÇÃO: {{ mb_strtoupper($statusLabel, 'UTF-8') }}</span>
            <span>| ORDENADO: {{ mb_strtoupper($orderLabel, 'UTF-8') }}</span>
            @if ($locateLabel ?? null)
                <span>| LOCALIZAR: {{ mb_strtoupper($locateLabel, 'UTF-8') }}</span>
            @endif
        </div>

        <table class="nfe-list-doc__table">
            <colgroup>
                @foreach ($columns as $column)
                    <col style="width: {{ CompraListagemReport::columnWidthPercent($column) }};">
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th @class([
                            CompraListagemReport::columnCssClass($column),
                            'num' => CompraListagemReport::isNumericColumn($column),
                        ])>
                            {{ $columnLabels[$column] ?? $column }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($compras as $compra)
                    <tr>
                        @foreach ($columns as $column)
                            <td @class([
                                CompraListagemReport::columnCssClass($column),
                                'num' => CompraListagemReport::isNumericColumn($column),
                            ])>
                                {{ CompraListagemReport::cellValue($compra, $column) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="nfe-list-doc__empty">Nenhuma compra encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($hasCompras)
                <tfoot>
                    <tr class="nfe-list-doc__totals">
                        @foreach ($columns as $column)
                            <td @class([
                                CompraListagemReport::columnCssClass($column),
                                'num' => CompraListagemReport::isNumericColumn($column),
                            ])>
                                {{ $columnTotals[$column] }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="nfe-list-doc__footer">
            <span>Impresso em {{ $printedAt->format('d/m/Y H:i') }}</span>
        </div>
    </div>
</div>
