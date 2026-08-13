@php
    use App\Support\Erp\Reports\ComissaoVendedoresReport as Rel;
    $money = fn ($v): string => Rel::formatMoney((float) $v);
@endphp

<div class="comissao-doc">
    <div class="comissao-doc__frame">
        <div class="comissao-doc__header">
            <div class="comissao-doc__logo-cell">
                <div class="comissao-doc__logo">
                    @if (filled($logoDataUri ?? null))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="comissao-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="comissao-doc__company-cell">
                <span class="comissao-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
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

        <hr class="comissao-doc__rule">

        <div class="comissao-doc__title">{{ $reportTitle ?? 'COMISSÃO DE OPERADORES' }}</div>

        <div class="comissao-doc__filters">
            <span>| PERÍODO: {{ mb_strtoupper($periodoLabel ?? '', 'UTF-8') }}</span>
            @if (($filters['vendedor'] ?? 'todos') !== 'todos')
                <span>| OPERADOR: {{ mb_strtoupper($filterOptions['vendedor'][$filters['vendedor']] ?? (string) $filters['vendedor'], 'UTF-8') }}</span>
            @else
                <span>| OPERADOR: TODOS</span>
            @endif
        </div>

        <table class="comissao-doc__table">
            <thead>
                <tr>
                    <th>Operador</th>
                    <th class="num">Qtd</th>
                    <th class="num">Vendas à Vista</th>
                    <th class="num">% AV</th>
                    <th class="num">Comissão à Vista</th>
                    <th class="num">Vendas a Prazo</th>
                    <th class="num">% AP</th>
                    <th class="num">Comissão a Prazo</th>
                    <th class="num">Total Vendido</th>
                    <th class="num">Comissão Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $l)
                    <tr>
                        <td>{{ $l['nome'] }}</td>
                        <td class="num">{{ $l['qtd'] }}</td>
                        <td class="num">{{ $money($l['total_avista']) }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $l['comissao_av'], 2, ',', '.'), '0'), ',') }}%</td>
                        <td class="num">{{ $money($l['comissao_avista']) }}</td>
                        <td class="num">{{ $money($l['total_aprazo']) }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $l['comissao_ap'], 2, ',', '.'), '0'), ',') }}%</td>
                        <td class="num">{{ $money($l['comissao_aprazo']) }}</td>
                        <td class="num">{{ $money($l['total_geral']) }}</td>
                        <td class="num comissao-doc__strong">{{ $money($l['comissao_total']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="comissao-doc__empty">Nenhuma venda faturada no período.</td>
                    </tr>
                @endforelse
            </tbody>
            @if (! empty($linhas))
                <tfoot>
                    <tr>
                        <td class="comissao-doc__strong">TOTAL</td>
                        <td class="num comissao-doc__strong">{{ $totais['qtd'] }}</td>
                        <td class="num comissao-doc__strong">{{ $money($totais['total_avista']) }}</td>
                        <td></td>
                        <td class="num comissao-doc__strong">{{ $money($totais['comissao_avista']) }}</td>
                        <td class="num comissao-doc__strong">{{ $money($totais['total_aprazo']) }}</td>
                        <td></td>
                        <td class="num comissao-doc__strong">{{ $money($totais['comissao_aprazo']) }}</td>
                        <td class="num comissao-doc__strong">{{ $money($totais['total_geral']) }}</td>
                        <td class="num comissao-doc__strong">{{ $money($totais['comissao_total']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
