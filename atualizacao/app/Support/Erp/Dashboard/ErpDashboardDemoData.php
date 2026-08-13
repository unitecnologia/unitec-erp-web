<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;

class ErpDashboardDemoData
{
    /**
     * @return array<string, mixed>
     */
    public static function all(?int $empresaId = null): array
    {
        return [
            'kpis' => static::kpis(),
            'gauges' => ErpDashboardGauges::build($empresaId),
            'sellerGauges' => ErpDashboardGauges::buildVendedores($empresaId),
            'salesChart' => static::salesChart(),
            'cashflowChart' => static::cashflowChart(),
            'salesMixChart' => static::salesMixChart(),
            'fiscalDocsChart' => static::fiscalDocsChart(),
            'paymentMethodsChart' => static::paymentMethodsChart(),
            'recentSales' => static::recentSales(),
            'highlights' => static::highlights(),
            'alerts' => static::alerts($empresaId),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function kpis(): array
    {
        return [
            ...static::baseKpis(),
            ErpDashboardLicense::kpi(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function baseKpis(): array
    {
        return [
            [
                'key' => 'faturamento_hoje',
                'label' => 'Faturamento hoje',
                'value' => 'R$ 12.480,50',
                'hint' => '+8,2% vs ontem',
                'tone' => 'blue',
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'key' => 'vendas_mes',
                'label' => 'Vendas do mês',
                'value' => 'R$ 284.920,00',
                'hint' => 'Meta: 72% atingida',
                'tone' => 'green',
                'icon' => 'heroicon-o-shopping-bag',
            ],
            [
                'key' => 'contas_receber',
                'label' => 'Contas a receber',
                'value' => 'R$ 48.350,00',
                'hint' => '126 títulos em aberto',
                'tone' => 'teal',
                'icon' => 'heroicon-o-arrow-down-circle',
            ],
            [
                'key' => 'contas_vencidas',
                'label' => 'Contas vencidas',
                'value' => 'R$ 9.740,00',
                'hint' => '18 títulos vencidos',
                'tone' => 'red',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            [
                'key' => 'estoque_critico',
                'label' => 'Estoque crítico',
                'value' => '14',
                'hint' => 'Produtos abaixo do mínimo',
                'tone' => 'orange',
                'icon' => 'heroicon-o-cube',
                'report_url' => route('erp.reports.produtos-estoque', [
                    'estoque' => 'critico',
                    'status' => 'ativos',
                    'ordenar' => 'estoque',
                ]),
                'report_title' => 'Relatório de estoque crítico',
            ],
            [
                'key' => 'notas_rejeitadas',
                'label' => 'Notas rejeitadas',
                'value' => '3',
                'hint' => '2 NFC-e · 1 NF-e',
                'tone' => 'indigo',
                'icon' => 'heroicon-o-document-text',
            ],
        ];
    }

    /**
     * @return array{
     *     defaultFrom: string,
     *     defaultTo: string,
     *     points: list<array{date: string, label: string, value: float}>
     * }
     */
    public static function salesChart(): array
    {
        return ErpDashboardSalesChart::data();
    }

    /**
     * Pontos demo do mês corrente (com data ISO) para fallback do gráfico de vendas.
     *
     * @return list<array{date: string, label: string, value: float}>
     */
    public static function salesChartPoints(): array
    {
        $values = [8200, 9100, 7600, 11200, 9800, 12400, 10100, 11800, 13200, 10900, 14300, 12800, 15100, 13800, 12480];
        $inicio = ErpFinanceiroMetricas::hoje()->copy()->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();
        $daysInMonth = (int) $inicio->daysInMonth;
        $step = max(1, (int) floor(($daysInMonth - 1) / (count($values) - 1)));
        $points = [];

        foreach ($values as $index => $value) {
            $dayOffset = min($daysInMonth - 1, $index * $step);
            $date = $inicio->copy()->addDays($dayOffset);

            if ($date->gt($fim)) {
                $date = $fim->copy();
            }

            $points[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('d/m'),
                'value' => (float) $value,
            ];
        }

        return $points;
    }

    /**
     * @deprecated Use salesChart() com points/defaultFrom/defaultTo.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public static function salesChartLegacy(): array
    {
        $points = static::salesChartPoints();

        return [
            'labels' => array_column($points, 'label'),
            'values' => array_column($points, 'value'),
        ];
    }

    /**
     * @return array{labels: list<string>, entradas: list<int>, saidas: list<int>}
     */
    public static function cashflowChart(): array
    {
        return [
            'labels' => ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
            'entradas' => [68400, 72100, 69800, 74200],
            'saidas' => [51200, 54800, 53100, 56900],
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string}
     */
    public static function salesMixChart(): array
    {
        return [
            'labels' => ['PDV', 'Pedidos', 'Orçamentos'],
            'values' => [42850.0, 91240.0, 18630.0],
            'colors' => ['#1e5a9e', '#0d9488', '#d97706'],
            'unit' => 'money',
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string}
     */
    public static function fiscalDocsChart(): array
    {
        return [
            'labels' => ['NFe Aut.', 'NFe Pend.', 'NFCe Aut.', 'NFCe Pend.'],
            'values' => [42.0, 8.0, 186.0, 11.0],
            'colors' => ['#1d4ed8', '#93c5fd', '#0f766e', '#f59e0b'],
            'unit' => 'count',
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string}
     */
    public static function paymentMethodsChart(): array
    {
        return [
            'labels' => ['PIX', 'Dinheiro', 'Crédito', 'Débito'],
            'values' => [48200.0, 21540.0, 19870.0, 12430.0],
            'colors' => ['#1d4ed8', '#0f766e', '#7c3aed', '#6366f1'],
            'unit' => 'money',
        ];
    }

    /**
     * @return list<array{label: string, value: string, hint: string}>
     */
    public static function highlights(): array
    {
        return [
            ['label' => 'Ticket médio', 'value' => 'R$ 186,40', 'hint' => '214 vendas no mês'],
            ['label' => 'Produto mais vendido', 'value' => 'OLEO 20W50 1L', 'hint' => '142 un. no mês'],
            ['label' => 'Cliente que mais comprou', 'value' => 'João Comércio LTDA', 'hint' => 'R$ 12450,00 no mês'],
            ['label' => 'Vendedor destaque', 'value' => 'LOJA', 'hint' => 'R$ 3135,00 no mês'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function recentSales(): array
    {
        return [
            ['id' => '18452', 'cliente' => 'Maria Silva', 'valor' => 'R$ 890,00', 'data' => '15/06 11:42', 'status' => 'Finalizada'],
            ['id' => '18451', 'cliente' => 'João Comércio LTDA', 'valor' => 'R$ 2.340,50', 'data' => '15/06 10:18', 'status' => 'Finalizada'],
            ['id' => '18450', 'cliente' => 'Consumidor', 'valor' => 'R$ 127,90', 'data' => '15/06 09:55', 'status' => 'PDV'],
            ['id' => '18449', 'cliente' => 'Ana Paula Souza', 'valor' => 'R$ 560,00', 'data' => '14/06 17:30', 'status' => 'Finalizada'],
            ['id' => '18448', 'cliente' => 'Distribuidora Norte', 'valor' => 'R$ 4.120,00', 'data' => '14/06 16:02', 'status' => 'Orçamento'],
            ['id' => '18447', 'cliente' => 'Carlos Mendes', 'valor' => 'R$ 315,40', 'data' => '14/06 14:48', 'status' => 'Finalizada'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function alerts(?int $empresaId = null): array
    {
        $important = [];

        $nfeRejeitadasAlert = ErpDashboardNfeRejeitadasAlert::resolve($empresaId);

        if ($nfeRejeitadasAlert !== null) {
            $important[] = $nfeRejeitadasAlert;
        }

        $important[] = ErpDashboardBackupAlert::resolve();

        $certAlert = ErpDashboardCertificadoAlert::fromEmpresa($empresaId);

        if ($certAlert !== null) {
            array_unshift($important, $certAlert);
        } else {
            array_unshift($important, [
                'tone' => 'red',
                'title' => 'Certificado A1 vence em 12 dias',
                'time' => 'Hoje',
            ]);
        }

        return [
            'important' => $important,
            'a_pagar_vencidos' => [
                'total' => 8,
                'items' => [
                    ['fornecedor' => 'Distribuidora Norte', 'valor' => 'R$ 1.850,00', 'vencimento' => '12/06/2026'],
                    ['fornecedor' => 'Fornecedor Sul', 'valor' => 'R$ 920,00', 'vencimento' => '10/06/2026'],
                    ['fornecedor' => 'Atacado Oeste', 'valor' => 'R$ 2.100,00', 'vencimento' => '08/06/2026'],
                    ['fornecedor' => 'Embalagens BR', 'valor' => 'R$ 430,00', 'vencimento' => '05/06/2026'],
                    ['fornecedor' => 'Transportadora Leste', 'valor' => 'R$ 1.240,00', 'vencimento' => '03/06/2026'],
                    ['fornecedor' => 'Insumos Centro', 'valor' => 'R$ 675,50', 'vencimento' => '01/06/2026'],
                    ['fornecedor' => 'Tech Parts Ltda', 'valor' => 'R$ 3.420,00', 'vencimento' => '28/05/2026'],
                    ['fornecedor' => 'Serviços Gerais SA', 'valor' => 'R$ 890,00', 'vencimento' => '25/05/2026'],
                ],
            ],
            'estoque' => [
                'total' => 4,
                'items' => [
                    ['produto' => 'Coca-Cola 2L', 'atual' => '4', 'minimo' => '12'],
                    ['produto' => 'Arroz Tipo 1 5kg', 'atual' => '2', 'minimo' => '10'],
                    ['produto' => 'Detergente Neutro', 'atual' => '6', 'minimo' => '15'],
                    ['produto' => 'Papel A4 500fl', 'atual' => '1', 'minimo' => '8'],
                ],
            ],
        ];
    }
}
