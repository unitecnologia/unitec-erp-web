<?php

namespace App\Support\Erp\Dashboard;

class ErpDashboardDemoData
{
    /**
     * @return array<string, mixed>
     */
    public static function all(?int $empresaId = null): array
    {
        return [
            'kpis' => static::kpis(),
            'salesChart' => static::salesChart(),
            'cashflowChart' => static::cashflowChart(),
            'recentSales' => static::recentSales(),
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
     * Pontos demo completos (com data ISO) para fallback do gráfico de vendas.
     *
     * @return list<array{date: string, label: string, value: float}>
     */
    public static function salesChartPoints(): array
    {
        return [
            ['date' => '2026-05-17', 'label' => '17/05', 'value' => 8200],
            ['date' => '2026-05-19', 'label' => '19/05', 'value' => 9100],
            ['date' => '2026-05-21', 'label' => '21/05', 'value' => 7600],
            ['date' => '2026-05-23', 'label' => '23/05', 'value' => 11200],
            ['date' => '2026-05-25', 'label' => '25/05', 'value' => 9800],
            ['date' => '2026-05-27', 'label' => '27/05', 'value' => 12400],
            ['date' => '2026-05-29', 'label' => '29/05', 'value' => 10100],
            ['date' => '2026-05-31', 'label' => '31/05', 'value' => 11800],
            ['date' => '2026-06-02', 'label' => '02/06', 'value' => 13200],
            ['date' => '2026-06-04', 'label' => '04/06', 'value' => 10900],
            ['date' => '2026-06-06', 'label' => '06/06', 'value' => 14300],
            ['date' => '2026-06-08', 'label' => '08/06', 'value' => 12800],
            ['date' => '2026-06-10', 'label' => '10/06', 'value' => 15100],
            ['date' => '2026-06-12', 'label' => '12/06', 'value' => 13800],
            ['date' => '2026-06-14', 'label' => '14/06', 'value' => 12480],
        ];
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
            'boletos' => [
                ['cliente' => 'Loja Central', 'valor' => 'R$ 1.850,00', 'vencimento' => '12/06/2026'],
                ['cliente' => 'Mercado Sul', 'valor' => 'R$ 920,00', 'vencimento' => '10/06/2026'],
                ['cliente' => 'Atacado Oeste', 'valor' => 'R$ 2.100,00', 'vencimento' => '08/06/2026'],
            ],
            'estoque' => [
                ['produto' => 'Coca-Cola 2L', 'atual' => '4', 'minimo' => '12'],
                ['produto' => 'Arroz Tipo 1 5kg', 'atual' => '2', 'minimo' => '10'],
                ['produto' => 'Detergente Neutro', 'atual' => '6', 'minimo' => '15'],
                ['produto' => 'Papel A4 500fl', 'atual' => '1', 'minimo' => '8'],
            ],
        ];
    }
}
