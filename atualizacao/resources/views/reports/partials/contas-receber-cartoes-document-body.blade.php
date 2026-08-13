@php
    use App\Support\Erp\Reports\ContaReceberCartoesReport;

    $columnTotals = ContaReceberCartoesReport::columnTotals($contas, $columns);
    $hasContas = count($contas) > 0;
@endphp
@include('reports.partials.contas-receber-cartoes-document-styles')
<div class="cr-cartoes-doc">
    <div class="cr-cartoes-doc__frame">
        <div class="cr-cartoes-doc__header">
            <div class="cr-cartoes-doc__logo-cell">
                <div class="cr-cartoes-doc__logo">
                    @if (filled($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="cr-cartoes-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="cr-cartoes-doc__company-cell">
                <span class="cr-cartoes-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
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

        <hr class="cr-cartoes-doc__rule">

        <div class="cr-cartoes-doc__title">{{ $reportTitle }}</div>

        <div class="cr-cartoes-doc__filters">
            <span>| FORMA: CARTÃO</span>
            <span>| SITUAÇÃO: {{ mb_strtoupper($situacaoLabel, 'UTF-8') }}</span>
            <span>| PERÍODO (VENC.): {{ mb_strtoupper($periodoLabel, 'UTF-8') }}</span>
            @if ($searchLabel)
                <span>| FILTRO: {{ $searchLabel }}</span>
            @endif
            <span>| QTD: {{ count($contas) }}</span>
        </div>

        <table class="cr-cartoes-doc__table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ ContaReceberCartoesReport::isNumericColumn($column) ? 'num' : '' }}">
                            {{ $columnLabels[$column] ?? $column }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($contas as $conta)
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ ContaReceberCartoesReport::isNumericColumn($column) ? 'num' : '' }}">
                                {{ ContaReceberCartoesReport::cellValue($conta, $column) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="cr-cartoes-doc__empty">
                            Nenhum título de cartão encontrado com os filtros atuais.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($hasContas)
                <tfoot>
                    <tr class="cr-cartoes-doc__totals">
                        @foreach ($columns as $column)
                            <td class="{{ ContaReceberCartoesReport::isNumericColumn($column) ? 'num' : '' }}">
                                {{ $columnTotals[$column] }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>

        @if ($hasContas && ($totaisBandeira ?? []) !== [])
            <table class="cr-cartoes-doc__bandeiras">
                <thead>
                    <tr>
                        <th>BANDEIRA</th>
                        <th class="num">QTD</th>
                        <th class="num">VALOR</th>
                        <th class="num">SALDO</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($totaisBandeira as $linha)
                        <tr>
                            <td>{{ $linha['bandeira'] }}</td>
                            <td class="num">{{ $linha['qtd'] }}</td>
                            <td class="num">{{ ContaReceberCartoesReport::formatMoney((float) $linha['valor']) }}</td>
                            <td class="num">{{ ContaReceberCartoesReport::formatMoney((float) $linha['saldo']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="cr-cartoes-doc__footer">
            <span>Relatório emitido em {{ $printedAt->format('d/m/Y - H:i:s') }}</span>
            <span>Pág. 1</span>
        </div>
    </div>
</div>
