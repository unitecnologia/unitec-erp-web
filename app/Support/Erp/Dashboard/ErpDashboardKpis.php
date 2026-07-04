<?php

namespace App\Support\Erp\Dashboard;

use App\Models\ContaReceber;
use App\Models\Nfe;
use App\Models\Product;
use App\Support\Erp\ErpMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ErpDashboardKpis
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function build(?int $empresaId = null): array
    {
        return [
            static::faturamentoHoje(),
            static::vendasMes(),
            static::contasReceber(),
            static::contasVencidas(),
            static::estoqueCritico(),
            static::notasRejeitadas($empresaId),
            ErpDashboardLicense::kpi(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function faturamentoHoje(): array
    {
        $hoje = ErpDashboardSalesMetrics::faturamentoDia(Carbon::today());
        $ontem = ErpDashboardSalesMetrics::faturamentoDia(Carbon::yesterday());

        return [
            'key' => 'faturamento_hoje',
            'label' => 'Faturamento hoje',
            'value' => 'R$ ' . ErpMoney::formatBr($hoje),
            'hint' => ErpDashboardSalesMetrics::hintVariacaoDia($hoje, $ontem),
            'tone' => 'blue',
            'icon' => 'heroicon-o-banknotes',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function vendasMes(): array
    {
        $inicioMes = Carbon::today()->startOfMonth();
        $fimMes = Carbon::today();
        $totalMes = ErpDashboardSalesMetrics::faturamentoPeriodo($inicioMes, $fimMes);

        $diasNoMes = max(1, (int) $inicioMes->diffInDays($fimMes) + 1);
        $mediaDia = round($totalMes / $diasNoMes, 2);

        return [
            'key' => 'vendas_mes',
            'label' => 'Vendas do mês',
            'value' => 'R$ ' . ErpMoney::formatBr($totalMes),
            'hint' => $totalMes > 0
                ? 'Média diária: R$ ' . ErpMoney::formatBr($mediaDia)
                : 'Nenhuma venda no mês',
            'tone' => 'green',
            'icon' => 'heroicon-o-shopping-bag',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function contasReceber(): array
    {
        [$total, $titulos] = static::receberAberto();

        return [
            'key' => 'contas_receber',
            'label' => 'Contas a receber',
            'value' => 'R$ ' . ErpMoney::formatBr($total),
            'hint' => $titulos === 1
                ? '1 título em aberto'
                : "{$titulos} títulos em aberto",
            'tone' => 'teal',
            'icon' => 'heroicon-o-arrow-down-circle',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function contasVencidas(): array
    {
        [$total, $titulos] = static::receberVencido();

        return [
            'key' => 'contas_vencidas',
            'label' => 'Contas vencidas',
            'value' => 'R$ ' . ErpMoney::formatBr($total),
            'hint' => $titulos === 1
                ? '1 título vencido'
                : "{$titulos} títulos vencidos",
            'tone' => 'red',
            'icon' => 'heroicon-o-exclamation-triangle',
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
     * @return array<string, mixed>
     */
    private static function notasRejeitadas(?int $empresaId): array
    {
        $count = static::countNotasRejeitadas($empresaId);

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

    /**
     * @return array{0: float, 1: int}
     */
    private static function receberAberto(): array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())) {
                return [0.0, 0];
            }

            $query = ContaReceber::query()->where('saldo', '>', 0);

            return [
                (float) (clone $query)->sum('saldo'),
                (int) $query->count(),
            ];
        } catch (Throwable) {
            return [0.0, 0];
        }
    }

    /**
     * @return array{0: float, 1: int}
     */
    private static function receberVencido(): array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())) {
                return [0.0, 0];
            }

            $query = ContaReceber::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<', Carbon::today()->toDateString());

            return [
                (float) (clone $query)->sum('saldo'),
                (int) $query->count(),
            ];
        } catch (Throwable) {
            return [0.0, 0];
        }
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

    private static function countNotasRejeitadas(?int $empresaId): int
    {
        try {
            if (! Schema::hasTable((new Nfe)->getTable())) {
                return 0;
            }

            $query = Nfe::query()->where('status', Nfe::STATUS_DENEGADA);

            if ($empresaId) {
                $query->where('empresa_id', $empresaId);
            }

            return (int) $query->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
