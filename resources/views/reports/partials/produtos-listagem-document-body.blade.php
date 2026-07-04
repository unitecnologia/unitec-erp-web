@php
    use App\Support\Erp\Reports\ProductListagemReport;

    $columnTotals = ProductListagemReport::columnTotals($products, $columns);
    $hasProducts = count($products) > 0;
@endphp
<div class="prod-list-doc">
    <div class="prod-list-doc__frame">
        <div class="prod-list-doc__header">
            <div class="prod-list-doc__logo-cell">
                <div class="prod-list-doc__logo">
                    @if (filled($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="prod-list-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="prod-list-doc__company-cell">
                <span class="prod-list-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
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

        <hr class="prod-list-doc__rule">

        <div class="prod-list-doc__title">{{ $reportTitle ?? 'LISTAGEM DE PRODUTOS' }}</div>

        <div class="prod-list-doc__filters">
            <span>| SITUAÇÃO: {{ mb_strtoupper($statusLabel, 'UTF-8') }}</span>
            <span>| ORDENADO: {{ mb_strtoupper($orderLabel, 'UTF-8') }}</span>
            @if ($estoqueFilterLabel !== 'Todos')
                <span>| ESTOQUE: {{ mb_strtoupper($estoqueFilterLabel, 'UTF-8') }}</span>
            @endif
            @if ($grupoFilter)
                <span>| GRUPO: {{ mb_strtoupper($grupoFilter, 'UTF-8') }}</span>
            @endif
            @if ($searchLabel)
                <span>| FILTRO: {{ mb_strtoupper($searchLabel, 'UTF-8') }}</span>
            @endif
        </div>

        <table class="prod-list-doc__table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ ProductListagemReport::isNumericColumn($column) ? 'num' : '' }}">
                            {{ $columnLabels[$column] ?? $column }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        @foreach ($columns as $column)
                            <td @class([
                                ProductListagemReport::isNumericColumn($column) ? 'num' : '',
                                $column === 'descricao' ? 'descricao' : '',
                                $column === 'validade' && ProductListagemReport::validadeVencida($product) ? 'prod-list-doc__validade--vencida' : '',
                            ])>
                                {{ ProductListagemReport::cellValue($product, $column) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="prod-list-doc__empty">Nenhum produto encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($hasProducts)
                <tfoot>
                    <tr class="prod-list-doc__totals">
                        @foreach ($columns as $column)
                            <td class="{{ ProductListagemReport::isNumericColumn($column) ? 'num' : '' }}">
                                {{ $columnTotals[$column] }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="prod-list-doc__footer">
            <span>Relatório emitido em {{ $printedAt->format('d/m/Y - H:i:s') }}</span>
            <span class="prod-list-doc__footer-page">Pág. 1</span>
        </div>
    </div>
</div>
