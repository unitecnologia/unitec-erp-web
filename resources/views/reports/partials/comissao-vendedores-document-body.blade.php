@php
    use App\Support\Erp\Reports\ComissaoVendedoresReport as Rel;
    $money = fn ($v): string => Rel::formatMoney((float) $v);
@endphp

<div class="comissao-doc__frame">
    <div class="comissao-doc__head">
        @if (! empty($logoDataUri))
            <img src="{{ $logoDataUri }}" alt="Logo" class="comissao-doc__logo">
        @endif
        <div class="comissao-doc__head-info">
            <div class="comissao-doc__empresa">{{ mb_strtoupper($empresa?->nome_fantasia ?? $empresa?->razao_social ?? 'EMPRESA', 'UTF-8') }}</div>
            @if (! empty($empresaEndereco))
                <div class="comissao-doc__sub">{{ $empresaEndereco }}</div>
            @endif
        </div>
        <div class="comissao-doc__meta">
            <div>Emitido em {{ $printedAt->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <div class="comissao-doc__title">{{ $reportTitle }}</div>
    <div class="comissao-doc__period">Período: {{ $periodoLabel }}</div>

    <table class="comissao-doc__table">
        <thead>
            <tr>
                <th class="t-left">Vendedor</th>
                <th class="t-center">Qtd</th>
                <th class="t-right">Vendas à Vista</th>
                <th class="t-center">% AV</th>
                <th class="t-right">Comissão à Vista</th>
                <th class="t-right">Vendas a Prazo</th>
                <th class="t-center">% AP</th>
                <th class="t-right">Comissão a Prazo</th>
                <th class="t-right">Total Vendido</th>
                <th class="t-right">Comissão Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $l)
                <tr>
                    <td class="t-left">{{ $l['nome'] }}</td>
                    <td class="t-center">{{ $l['qtd'] }}</td>
                    <td class="t-right">{{ $money($l['total_avista']) }}</td>
                    <td class="t-center">{{ rtrim(rtrim(number_format((float) $l['comissao_av'], 2, ',', '.'), '0'), ',') }}%</td>
                    <td class="t-right">{{ $money($l['comissao_avista']) }}</td>
                    <td class="t-right">{{ $money($l['total_aprazo']) }}</td>
                    <td class="t-center">{{ rtrim(rtrim(number_format((float) $l['comissao_ap'], 2, ',', '.'), '0'), ',') }}%</td>
                    <td class="t-right">{{ $money($l['comissao_aprazo']) }}</td>
                    <td class="t-right">{{ $money($l['total_geral']) }}</td>
                    <td class="t-right comissao-doc__strong">{{ $money($l['comissao_total']) }}</td>
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
                    <td class="t-left comissao-doc__strong">TOTAL</td>
                    <td class="t-center comissao-doc__strong">{{ $totais['qtd'] }}</td>
                    <td class="t-right comissao-doc__strong">{{ $money($totais['total_avista']) }}</td>
                    <td></td>
                    <td class="t-right comissao-doc__strong">{{ $money($totais['comissao_avista']) }}</td>
                    <td class="t-right comissao-doc__strong">{{ $money($totais['total_aprazo']) }}</td>
                    <td></td>
                    <td class="t-right comissao-doc__strong">{{ $money($totais['comissao_aprazo']) }}</td>
                    <td class="t-right comissao-doc__strong">{{ $money($totais['total_geral']) }}</td>
                    <td class="t-right comissao-doc__strong">{{ $money($totais['comissao_total']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
