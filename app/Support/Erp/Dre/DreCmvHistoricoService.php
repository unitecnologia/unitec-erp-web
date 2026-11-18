<?php

namespace App\Support\Erp\Dre;

use App\Models\Venda;
use App\Support\Erp\Tef\TefMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D2.6 — CMV a partir de custo histórico capturado nos itens.
 *
 * Só libera cmv_disponivel quando a cobertura dos itens elegíveis no período
 * for completa (sem mistura silenciosa de custo conhecido + desconhecido).
 * Vendas canceladas não entram. Serviços (nao_aplicavel) não contam na cobertura.
 */
final class DreCmvHistoricoService
{
    /**
     * @return array{
     *   cmv: ?string,
     *   cmv_disponivel: bool,
     *   cmv_devolucoes: string,
     *   cmv_liquido: ?string,
     *   cobertura_pct: float,
     *   itens_ok: int,
     *   itens_indisponivel: int,
     *   itens_sem_captura: int,
     *   itens_nao_aplicavel: int,
     *   vendas_fechadas: int,
     *   pendencia: string
     * }
     */
    public function calcular(int $empresaId, string $periodoDe, string $periodoAte): array
    {
        $periodoDe = substr(trim($periodoDe), 0, 10);
        $periodoAte = substr(trim($periodoAte), 0, 10);
        $vazio = [
            'cmv' => null,
            'cmv_disponivel' => false,
            'cmv_devolucoes' => '0.00',
            'cmv_liquido' => null,
            'cobertura_pct' => 0.0,
            'itens_ok' => 0,
            'itens_indisponivel' => 0,
            'itens_sem_captura' => 0,
            'itens_nao_aplicavel' => 0,
            'vendas_fechadas' => 0,
            'pendencia' => 'Sem período/empresa válidos.',
        ];

        if ($empresaId <= 0 || $periodoDe === '' || $periodoAte === '' || $periodoDe > $periodoAte) {
            return $vazio;
        }

        if (! Schema::hasTable('venda_itens')
            || ! Schema::hasColumn('venda_itens', 'custo_situacao')
            || ! Schema::hasTable('vendas')
        ) {
            $vazio['pendencia'] = 'Schema de custo histórico indisponível.';

            return $vazio;
        }

        // Sem alias curto: com DB_PREFIX, "as i" vira unitec_i e quebra i.custo_*.
        $vi = 'venda_itens';
        $v = 'vendas';
        $rows = DB::table($vi)
            ->join($v, "{$v}.id", '=', "{$vi}.venda_id")
            ->where("{$v}.empresa_id", $empresaId)
            ->where("{$v}.status", Venda::STATUS_FECHADO)
            ->whereDate("{$v}.data", '>=', $periodoDe)
            ->whereDate("{$v}.data", '<=', $periodoAte)
            ->select([
                "{$vi}.custo_total",
                "{$vi}.custo_situacao",
                "{$v}.id as venda_id",
            ])
            ->get();

        $ok = 0;
        $indisp = 0;
        $sem = 0;
        $naoApl = 0;
        $cmv = '0.00';
        $vendas = [];

        foreach ($rows as $row) {
            $vendas[(int) $row->venda_id] = true;
            $sit = $row->custo_situacao;
            if ($sit === null || $sit === '') {
                $sem++;

                continue;
            }
            if ($sit === VendaItemCustoHistorico::SITUACAO_NAO_APLICAVEL) {
                $naoApl++;

                continue;
            }
            if ($sit === VendaItemCustoHistorico::SITUACAO_OK && $row->custo_total !== null) {
                $ok++;
                $cmv = TefMoney::add($cmv, (string) $row->custo_total);

                continue;
            }
            $indisp++;
        }

        $elegiveis = $ok + $indisp + $sem;
        $cobertura = $elegiveis > 0 ? round(100.0 * $ok / $elegiveis, 2) : 0.0;

        // Completo = todos os itens elegíveis têm custo ok (ou só serviços).
        $completo = $elegiveis === 0
            ? false // período sem itens de mercadoria com captura — não libera CMV “zero falso”
            : ($indisp === 0 && $sem === 0 && $ok > 0);

        // Se só há serviços (nao_aplicavel) e nenhuma mercadoria: CMV = 0.00 disponível.
        if ($elegiveis === 0 && $naoApl > 0 && count($vendas) > 0) {
            $completo = true;
            $cmv = '0.00';
        }

        $dev = $this->cmvDevolucoes($empresaId, $periodoDe, $periodoAte);
        $liquido = $completo ? TefMoney::sub($cmv, $dev) : null;
        if ($liquido !== null && TefMoney::cmp($liquido, '0') === -1) {
            $liquido = '0.00';
        }

        $pendencia = $completo
            ? ''
            : ($sem > 0
                ? 'Há itens sem captura de custo (vendas anteriores à D2.6).'
                : ($indisp > 0
                    ? 'Há itens com custo indisponível no fechamento.'
                    : 'Sem itens de mercadoria com custo histórico no período.'));

        return [
            'cmv' => $completo ? $cmv : null,
            'cmv_disponivel' => $completo,
            'cmv_devolucoes' => $dev,
            'cmv_liquido' => $liquido,
            'cobertura_pct' => $cobertura,
            'itens_ok' => $ok,
            'itens_indisponivel' => $indisp,
            'itens_sem_captura' => $sem,
            'itens_nao_aplicavel' => $naoApl,
            'vendas_fechadas' => count($vendas),
            'pendencia' => $pendencia,
        ];
    }

    private function cmvDevolucoes(int $empresaId, string $de, string $ate): string
    {
        if (! Schema::hasTable('devolucao_venda_itens')
            || ! Schema::hasColumn('devolucao_venda_itens', 'custo_situacao')
            || ! Schema::hasColumn('devolucao_venda_itens', 'custo_total')
            || ! Schema::hasTable('devolucoes_venda')
        ) {
            return '0.00';
        }

        $di = 'devolucao_venda_itens';
        $d = 'devolucoes_venda';
        $diSql = DB::connection()->getQueryGrammar()->wrapTable($di);
        $row = DB::table($di)
            ->join($d, "{$d}.id", '=', "{$di}.devolucao_venda_id")
            ->where("{$d}.empresa_id", $empresaId)
            ->where("{$d}.situacao", 'finalizada')
            ->whereDate("{$d}.data", '>=', $de)
            ->whereDate("{$d}.data", '<=', $ate)
            ->where("{$di}.custo_situacao", VendaItemCustoHistorico::SITUACAO_OK)
            ->whereNotNull("{$di}.custo_total")
            ->selectRaw("COALESCE(SUM({$diSql}.`custo_total`), 0) as total")
            ->first();

        return TefMoney::normalize($row->total ?? 0);
    }
}
