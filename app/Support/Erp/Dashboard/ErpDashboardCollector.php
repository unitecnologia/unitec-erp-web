<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\ErpSchema;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;

/**
 * Coletor único de dados do dashboard — memoiza agregados compartilhados
 * entre KPIs, gauges, gráficos e alertas no mesmo request.
 */
final class ErpDashboardCollector
{
    private static ?self $current = null;

    public readonly ?int $empresaId;

    /** @var int|list<int>|null */
    public readonly int|array|null $empresaScope;

    public readonly string $visao;

    public readonly Carbon $hoje;

    public readonly Carbon $inicioMes;

    public readonly Carbon $fimMes;

    /** @var array<string, mixed> */
    private array $memo = [];

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private function __construct(?int $empresaId, int|array|null $empresaScope, string $visao)
    {
        $this->empresaId = $empresaId;
        $this->empresaScope = $empresaScope;
        $this->visao = $visao;
        $this->hoje = ErpFinanceiroMetricas::hoje();
        $this->inicioMes = $this->hoje->copy()->startOfMonth();
        $this->fimMes = $this->hoje->copy();
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * @template T
     *
     * @param  callable(self): T  $fn
     * @return T
     */
    public static function run(callable $fn, ?int $empresaId, string $visao): mixed
    {
        $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();
        $empresaScope = ErpDashboardScope::resolve($empresaId, $visao);

        $prev = self::$current;
        $collector = new self($empresaId, $empresaScope, $visao);
        self::$current = $collector;
        ErpSchema::assumeMigrated(true);

        try {
            return $fn($collector);
        } finally {
            ErpSchema::assumeMigrated(false);
            self::$current = $prev;
        }
    }

    public function faturamentoDia(?Carbon $day = null): float
    {
        $day ??= $this->hoje;
        $key = 'fat_dia:'.$day->toDateString();

        return $this->memo[$key] ??= ErpDashboardSalesMetrics::faturamentoDia($day, $this->empresaScope);
    }

    public function faturamentoPeriodo(?Carbon $from = null, ?Carbon $to = null): float
    {
        $from ??= $this->inicioMes;
        $to ??= $this->fimMes;
        $key = 'fat_periodo:'.$from->toDateString().':'.$to->toDateString();

        return $this->memo[$key] ??= ErpDashboardSalesMetrics::faturamentoPeriodo($from, $to, $this->empresaScope);
    }

    public function saldoCaixa(): float
    {
        return $this->memo['saldo_caixa'] ??= ErpFinanceiroMetricas::saldoCaixa(null, $this->empresaScope);
    }

    /**
     * @return array{entradas: float, saidas: float, resultado: float}
     */
    public function movimentosCaixaHoje(): array
    {
        return $this->memo['mov_caixa_hoje'] ??= ErpFinanceiroMetricas::movimentosCaixaNoDia($this->hoje, $this->empresaScope);
    }

    /**
     * @return array{qtd: int, valor: float}
     */
    public function receberVencido(): array
    {
        return $this->memo['receber_vencido'] ??= ErpFinanceiroMetricas::receberVencido($this->hoje, $this->empresaScope);
    }

    /**
     * @return array{qtd: int, valor: float}
     */
    public function pagarVencido(): array
    {
        return $this->memo['pagar_vencido'] ??= ErpFinanceiroMetricas::pagarVencido($this->hoje, $this->empresaScope);
    }

    public function estoqueCriticoCount(): int
    {
        return $this->memo['estoque_critico'] ??= ErpDashboardKpis::countEstoqueCriticoPublic();
    }

    public function notasRejeitadasCount(): int
    {
        return $this->memo['notas_rejeitadas'] ??= ErpDashboardKpis::countNotasRejeitadasPublic($this->empresaScope);
    }

    public function realizadoPdvMonitor(?Carbon $inicio = null, ?Carbon $fim = null): float
    {
        $inicio ??= $this->inicioMes;
        $fim ??= $this->fimMes;
        $key = 'realizado_pdv:'.$inicio->toDateString().':'.$fim->toDateString();

        return $this->memo[$key] ??= ErpDashboardGauges::realizadoPdvMonitor($inicio, $fim, $this->empresaScope);
    }

    /**
     * @return list<array{inicio: Carbon, fim: Carbon}>
     */
    public function cashflowSemanas(): array
    {
        return $this->memo['cashflow_semanas'] ??= (function (): array {
            $semanas = [];
            for ($week = 3; $week >= 0; $week--) {
                $inicio = $this->hoje->copy()->startOfWeek()->subWeeks($week)->startOfDay();
                $fim = $inicio->copy()->endOfWeek()->endOfDay();
                if ($week === 0) {
                    $fim = $fim->min($this->hoje->copy()->endOfDay());
                }
                $semanas[] = ['inicio' => $inicio, 'fim' => $fim];
            }

            return $semanas;
        })();
    }

    /**
     * @return list<array{entradas: float, saidas: float}>
     */
    public function cashflowEntradasSaidas(): array
    {
        return $this->memo['cashflow_totals'] ??= ErpFinanceiroMetricas::entradasSaidasPorSemanas(
            $this->cashflowSemanas(),
            $this->empresaScope,
        );
    }
}
