@php
    $recentSales = $recentSales ?? [];
@endphp

<article class="erp-dash-panel erp-dash__sales">
    <header class="erp-dash-panel__head">
        <h2 class="erp-dash-panel__title">Últimas vendas</h2>
        <span class="erp-dash-panel__meta">{{ count($recentSales) > 0 ? count($recentSales) . ' registros' : 'Sem vendas recentes' }}</span>
    </header>
    <div class="erp-dash-panel__body erp-dash-panel__body--flush">
        <div class="erp-dash-table-wrap">
            <table class="erp-dash-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Valor</th>
                        <th>Data</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentSales as $sale)
                        <tr>
                            <td>{{ $sale['id'] }}</td>
                            <td>{{ $sale['cliente'] }}</td>
                            <td class="erp-dash-table__money">{{ $sale['valor'] }}</td>
                            <td>{{ $sale['data'] }}</td>
                            <td>
                                <span class="erp-dash-badge erp-dash-badge--{{ \Illuminate\Support\Str::slug($sale['status']) }}">
                                    {{ $sale['status'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="erp-dash-table__empty">Nenhuma venda registrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</article>
