@php
    use App\Support\Erp\Reports\VendaListagemReport;

    $columnTotals = VendaListagemReport::columnTotals($vendas, $columns);
    $hasVendas = count($vendas) > 0;
@endphp
<div class="venda-list-doc">
    <div class="venda-list-doc__frame">
        <div class="venda-list-doc__header">
            <div class="venda-list-doc__logo-cell">
                <div class="venda-list-doc__logo">
                    @if (filled($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="venda-list-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="venda-list-doc__company-cell">
                <span class="venda-list-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
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

        <hr class="venda-list-doc__rule">

        <div class="venda-list-doc__title">{{ $reportTitle }}</div>

        <div class="venda-list-doc__filters">
            <span>| SITUAÇÃO: {{ mb_strtoupper($statusLabel, 'UTF-8') }}</span>
            <span>| TIPO: {{ mb_strtoupper($tipoLabel, 'UTF-8') }}</span>
            <span>| ORDENADO: {{ mb_strtoupper($orderLabel, 'UTF-8') }}</span>
            @if ($searchLabel)
                <span>| FILTRO: {{ mb_strtoupper($searchLabel, 'UTF-8') }}</span>
            @endif
        </div>

        <table class="venda-list-doc__table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ VendaListagemReport::isNumericColumn($column) ? 'num' : '' }}">
                            {{ $columnLabels[$column] ?? $column }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($vendas as $venda)
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ VendaListagemReport::isNumericColumn($column) ? 'num' : '' }} {{ in_array($column, ['cliente', 'vendedor', 'meio_pagamento'], true) ? 'texto' : '' }}">
                                {{ VendaListagemReport::cellValue($venda, $column) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="venda-list-doc__empty">Nenhuma venda encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($hasVendas)
                <tfoot>
                    <tr class="venda-list-doc__totals">
                        @foreach ($columns as $column)
                            <td class="{{ VendaListagemReport::isNumericColumn($column) ? 'num' : '' }}">
                                {{ $columnTotals[$column] }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="venda-list-doc__footer">
            <span>Relatório emitido em {{ $printedAt->format('d/m/Y - H:i:s') }}</span>
            <span class="venda-list-doc__footer-page">Pág. 1</span>
        </div>
    </div>
</div>
