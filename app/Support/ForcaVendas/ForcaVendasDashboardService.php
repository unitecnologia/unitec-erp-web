<?php

namespace App\Support\ForcaVendas;

use App\Models\ForcaVendasOrder;
use App\Models\User;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\Dashboard\ErpDashboardGauges;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot do dashboard mobile (online): meta mensal + vendas + títulos.
 * Meta/realizado usam a mesma regra do gauge "Meta Vendedores" do ERP.
 */
final class ForcaVendasDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $vendedorId = (int) ($user->vendedor_id ?? 0);
        $vendedor = $vendedorId > 0 ? Vendedor::query()->find($vendedorId) : null;

        // Meta do colaborador: aparece no app só se preenchida (> 0). Sem flag de empresa.
        $meta = $vendedor ? (float) ($vendedor->mobile_meta_venda ?? 0) : 0.0;
        if ($meta <= 0) {
            $meta = 0.0;
        }

        $inicioMes = Carbon::today()->startOfMonth();
        $fimMes = Carbon::today()->endOfMonth();
        $hoje = Carbon::today();

        // Mesma base do dashboard ERP (PDV + pedidos mobile — sem somar vendas de novo).
        $realizadoMes = $vendedorId > 0
            ? ErpDashboardGauges::realizadoDoVendedor($vendedorId, $inicioMes, $fimMes)
            : 0.0;
        $realizadoHoje = $vendedorId > 0
            ? ErpDashboardGauges::realizadoDoVendedor($vendedorId, $hoje, $hoje)
            : 0.0;

        $qtdHoje = $vendedorId > 0 ? $this->qtdPedidosPeriodo($vendedorId, $hoje, $hoje) : 0;
        $qtdMes = $vendedorId > 0 ? $this->qtdPedidosPeriodo($vendedorId, $inicioMes, $fimMes) : 0;

        $titulos = $this->titulosCarteira($vendedorId);
        $percent = $meta > 0 ? round(($realizadoMes / $meta) * 100, 1) : 0.0;

        return [
            'metas_habilitadas' => $meta > 0,
            'meta_mensal' => round($meta, 2),
            'realizado_mes' => round($realizadoMes, 2),
            'percentual_meta' => $percent,
            'faltam_meta' => round(max(0, $meta - $realizadoMes), 2),
            'vendido_hoje' => round($realizadoHoje, 2),
            'pedidos_hoje' => $qtdHoje,
            'vendido_mes' => round($realizadoMes, 2),
            'pedidos_mes' => $qtdMes,
            'ticket_medio_mes' => $qtdMes > 0 ? round($realizadoMes / $qtdMes, 2) : 0.0,
            'titulos_aberto_qtd' => $titulos['qtd'],
            'titulos_aberto_valor' => $titulos['valor'],
            'titulos_vencidos_qtd' => $titulos['vencidos_qtd'],
            'titulos_vencidos_valor' => $titulos['vencidos_valor'],
            'vendedor_nome' => $vendedor?->nome,
            'periodo' => [
                'inicio' => $inicioMes->toDateString(),
                'fim' => $fimMes->toDateString(),
                'hoje' => $hoje->toDateString(),
            ],
        ];
    }

    private function qtdPedidosPeriodo(int $vendedorId, Carbon $inicio, Carbon $fim): int
    {
        $qtd = 0;

        if (Schema::hasTable((new ForcaVendasOrder)->getTable())) {
            $qtd += (int) ForcaVendasOrder::query()
                ->where('vendedor_id', $vendedorId)
                ->where('situacao', '!=', ForcaVendasOrder::SITUACAO_CANCELADO)
                ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO)
                ->where(function ($query) use ($inicio, $fim): void {
                    $query->where(function ($faturado) use ($inicio, $fim): void {
                        $faturado->whereNotNull('faturado_at')
                            ->whereDate('faturado_at', '>=', $inicio->toDateString())
                            ->whereDate('faturado_at', '<=', $fim->toDateString());
                    })->orWhere(function ($received) use ($inicio, $fim): void {
                        $received->whereNull('faturado_at')
                            ->whereNotNull('received_at')
                            ->whereDate('received_at', '>=', $inicio->toDateString())
                            ->whereDate('received_at', '<=', $fim->toDateString());
                    });
                })
                ->count();
        }

        if (Schema::hasTable((new Venda)->getTable()) && Schema::hasColumn('vendas', 'vendedor_id')) {
            $qtd += (int) Venda::query()
                ->where('vendedor_id', $vendedorId)
                ->whereDate('data', '>=', $inicio->toDateString())
                ->whereDate('data', '<=', $fim->toDateString())
                ->when(
                    Schema::hasColumn('vendas', 'status'),
                    fn ($q) => $q->where('status', Venda::STATUS_FECHADO),
                )
                ->when(
                    Schema::hasColumn('vendas', 'cancelada'),
                    fn ($q) => $q->where(fn ($w) => $w->whereNull('cancelada')->orWhere('cancelada', false)),
                )
                ->count();
        }

        return $qtd;
    }

    /**
     * @return array{qtd: int, valor: float, vencidos_qtd: int, vencidos_valor: float}
     */
    private function titulosCarteira(int $vendedorId): array
    {
        $empty = ['qtd' => 0, 'valor' => 0.0, 'vencidos_qtd' => 0, 'vencidos_valor' => 0.0];

        if ($vendedorId <= 0 || ! Schema::hasTable('contas_receber') || ! Schema::hasTable('people')) {
            return $empty;
        }

        $hoje = Carbon::today()->toDateString();
        $base = DB::table('contas_receber as cr')
            ->join('people as p', 'p.id', '=', 'cr.cliente_id')
            ->where('cr.saldo', '>', 0)
            ->where(function ($q) use ($vendedorId): void {
                $q->where('p.vendedor_fv_id', $vendedorId);
                if (Schema::hasColumn('people', 'vendedor_id')) {
                    $q->orWhere('p.vendedor_id', $vendedorId);
                }
            });

        $qtd = (int) (clone $base)->count();
        $valor = (float) (clone $base)->sum('cr.saldo');
        $vencidosQtd = (int) (clone $base)->whereDate('cr.vencimento', '<', $hoje)->count();
        $vencidosValor = (float) (clone $base)->whereDate('cr.vencimento', '<', $hoje)->sum('cr.saldo');

        return [
            'qtd' => $qtd,
            'valor' => round($valor, 2),
            'vencidos_qtd' => $vencidosQtd,
            'vencidos_valor' => round($vencidosValor, 2),
        ];
    }
}
