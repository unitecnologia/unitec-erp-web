<?php

namespace App\Support\Erp\Dashboard;

use App\Models\Nfe;
use App\Models\Product;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * KPIs do dashboard ERP — alinhados às mesmas bases do Painel Executivo
 * (ErpFinanceiroMetricas + ErpDashboardSalesMetrics).
 *
 * Indicadores exclusivos do ERP (notas, licença) ficam aqui; aprovações FV
 * ficam só no Executivo.
 */
final class ErpDashboardKpis
{
    /**
     * @param  int|list<int>|null  $empresaScope
     * @return list<array<string, mixed>>
     */
    public static function build(?int $empresaId = null, int|array|null $empresaScope = null): array
    {
        $scope = $empresaScope ?? $empresaId;

        return [
            static::faturamentoHoje($scope),
            static::vendasMes($scope),
            static::saldoCaixa($scope),
            static::contasVencidas($scope),
            static::contasPagarVencidas($scope),
            static::estoqueCritico(),
            static::notasRejeitadas($scope),
            ErpDashboardLicense::kpi(),
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array<string, mixed>
     */
    private static function faturamentoHoje(int|array|null $empresaScope = null): array
    {
        $dia = ErpFinanceiroMetricas::hoje();
        $hoje = ErpDashboardSalesMetrics::faturamentoDia($dia, $empresaScope);
        $ontem = ErpDashboardSalesMetrics::faturamentoDia($dia->copy()->subDay(), $empresaScope);

        return [
            'key' => 'faturamento_hoje',
            'label' => 'Faturamento hoje',
            'value' => 'R$ '.ErpMoney::formatBr($hoje),
            'hint' => ErpDashboardSalesMetrics::hintVariacaoDia($hoje, $ontem),
            'tone' => 'blue',
            'icon' => 'heroicon-o-banknotes',
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array<string, mixed>
     */
    private static function vendasMes(int|array|null $empresaScope = null): array
    {
        $fimMes = ErpFinanceiroMetricas::hoje();
        $inicioMes = $fimMes->copy()->startOfMonth();
        $totalMes = ErpDashboardSalesMetrics::faturamentoPeriodo($inicioMes, $fimMes, $empresaScope);
        $diasNoMes = max(1, (int) $inicioMes->diffInDays($fimMes) + 1);
        $mediaDia = round($totalMes / $diasNoMes, 2);

        return [
            'key' => 'vendas_mes',
            'label' => 'Vendas do mês',
            'value' => 'R$ '.ErpMoney::formatBr($totalMes),
            'hint' => $totalMes > 0
                ? 'Média diária: R$ '.ErpMoney::formatBr($mediaDia)
                : 'Nenhuma venda no mês',
            'tone' => 'green',
            'icon' => 'heroicon-o-shopping-bag',
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array<string, mixed>
     */
    private static function saldoCaixa(int|array|null $empresaScope = null): array
    {
        $saldo = ErpFinanceiroMetricas::saldoCaixa(null, $empresaScope);
        $hoje = ErpFinanceiroMetricas::movimentosCaixaNoDia(ErpFinanceiroMetricas::hoje(), $empresaScope);

        return [
            'key' => 'saldo_caixa',
            'label' => 'Saldo de caixa',
            'value' => 'R$ '.ErpMoney::formatBr($saldo),
            'hint' => 'Hoje: entr. R$ '.ErpMoney::formatBr((float) $hoje['entradas'])
                .' · saí. R$ '.ErpMoney::formatBr((float) $hoje['saidas']),
            'tone' => $saldo >= 0 ? 'teal' : 'red',
            'icon' => 'heroicon-o-wallet',
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array<string, mixed>
     */
    private static function contasVencidas(int|array|null $empresaScope = null): array
    {
        $base = ErpFinanceiroMetricas::receberVencido(null, $empresaScope);
        $total = (float) $base['valor'];
        $titulos = (int) $base['qtd'];

        return [
            'key' => 'contas_vencidas',
            'label' => 'Receber vencido',
            'value' => 'R$ '.ErpMoney::formatBr($total),
            'hint' => $titulos === 1
                ? '1 título vencido'
                : "{$titulos} títulos vencidos",
            'tone' => 'red',
            'icon' => 'heroicon-o-exclamation-triangle',
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array<string, mixed>
     */
    private static function contasPagarVencidas(int|array|null $empresaScope = null): array
    {
        $base = ErpFinanceiroMetricas::pagarVencido(null, $empresaScope);
        $total = (float) $base['valor'];
        $titulos = (int) $base['qtd'];

        return [
            'key' => 'contas_pagar_vencidas',
            'label' => 'Pagar vencido',
            'value' => 'R$ '.ErpMoney::formatBr($total),
            'hint' => $titulos === 1
                ? '1 título vencido'
                : "{$titulos} títulos vencidos",
            'tone' => 'orange',
            'icon' => 'heroicon-o-arrow-up-circle',
            'report_url' => route('erp.reports.tabular', [
                'slug' => 'contas-pagar',
                'situacao' => 'vencidos',
            ]),
            'report_title' => 'Relatório de contas a pagar vencidas',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function estoqueCritico(): array
    {
        $count = static::countEstoqueCritico();

        return [
            'key' => 'estoque_critico',
            'label' => 'Estoque crítico',
            'value' => (string) $count,
            'hint' => $count === 1
                ? 'Produto abaixo do mínimo'
                : 'Produtos abaixo do mínimo',
            'tone' => 'orange',
            'icon' => 'heroicon-o-cube',
            'report_url' => route('erp.reports.produtos-estoque', [
                'estoque' => 'critico',
                'status' => 'ativos',
                'ordenar' => 'estoque',
            ]),
            'report_title' => 'Relatório de estoque crítico',
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array<string, mixed>
     */
    private static function notasRejeitadas(int|array|null $empresaScope = null): array
    {
        $count = static::countNotasRejeitadas($empresaScope);

        return [
            'key' => 'notas_rejeitadas',
            'label' => 'Notas rejeitadas',
            'value' => (string) $count,
            'hint' => $count === 0
                ? 'Nenhuma nota denegada'
                : ($count === 1 ? '1 nota denegada na SEFAZ' : "{$count} notas denegadas na SEFAZ"),
            'tone' => 'indigo',
            'icon' => 'heroicon-o-document-text',
        ];
    }

    private static function countEstoqueCritico(): int
    {
        try {
            if (! Schema::hasTable((new Product)->getTable())) {
                return 0;
            }

            return (int) Product::query()->estoqueCritico()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function countNotasRejeitadas(int|array|null $empresaScope = null): int
    {
        try {
            if (! Schema::hasTable((new Nfe)->getTable())) {
                return 0;
            }

            $query = Nfe::query()->where('status', Nfe::STATUS_DENEGADA);

            ErpFinanceiroMetricas::applyEmpresaColumn($query, (new Nfe)->getTable(), $empresaScope);

            return (int) $query->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
