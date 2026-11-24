<?php

namespace App\Support\Erp\Reports\LucratividadePorVenda;

use App\Models\DevolucaoVenda;
use App\Support\Erp\Tef\TefMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * % LUCRO do Relatório Geral de Vendas.
 *
 * Fórmula: {@see LucratividadeVendaLucroPercentual::sobreCusto}
 * (mesma do Relatório Lucratividade por Venda — lucro sobre o custo).
 *
 * Custo: snapshot histórico D2.6 ({@see LucratividadeVendaCustoResolver}).
 * Receita líquida: vendas.total − devoluções finalizadas (desc./acr. já no total).
 * Sem custo confiável → Indisponível (nunca inventa lucro).
 */
final class LucratividadeVendaMargemBatch
{
    /**
     * @param  array<int, float|string>  $totaisBrutosPorVenda  venda_id => total bruto (vendas.total)
     * @return array<int, string> venda_id => "12,34%" | Indisponível
     */
    public static function rotulosPorVenda(array $totaisBrutosPorVenda): array
    {
        $out = [];
        $vendaIds = array_values(array_filter(
            array_map('intval', array_keys($totaisBrutosPorVenda)),
            static fn (int $id): bool => $id > 0,
        ));

        foreach ($vendaIds as $vid) {
            $out[$vid] = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
        }

        if ($vendaIds === []) {
            return $out;
        }

        $itensByVenda = self::loadItensCustoByVendaIds($vendaIds);
        $devReceitaByVenda = self::loadDevolucaoReceitaByVendaIds($vendaIds);
        $devItensByVenda = self::loadDevolucaoItensCustoByVendaIds($vendaIds, $devReceitaByVenda);

        foreach ($vendaIds as $vid) {
            $resolved = LucratividadeVendaCustoResolver::resolver(
                $itensByVenda[$vid] ?? [],
                $devItensByVenda[$vid] ?? [],
            );

            if (! $resolved['disponivel'] || $resolved['custo_liquido'] === null) {
                continue;
            }

            // Total comercial da venda já incorpora descontos/acréscimos de cabeçalho e itens.
            $bruto = TefMoney::normalize($totaisBrutosPorVenda[$vid] ?? 0);
            $devRec = TefMoney::normalize($devReceitaByVenda[$vid] ?? 0);
            $vendaLiquida = TefMoney::sub($bruto, $devRec);
            if (TefMoney::cmp($vendaLiquida, '0') === -1) {
                $vendaLiquida = '0.00';
            }

            $out[$vid] = LucratividadeVendaLucroPercentual::rotulo(
                $vendaLiquida,
                $resolved['custo_liquido'],
            );
        }

        return $out;
    }

    /**
     * @param  list<int>  $vendaIds
     * @return array<int, list<object>>
     */
    private static function loadItensCustoByVendaIds(array $vendaIds): array
    {
        $map = [];
        foreach ($vendaIds as $id) {
            $map[$id] = [];
        }

        if ($vendaIds === [] || ! Schema::hasTable('venda_itens')) {
            return $map;
        }

        $hasCusto = Schema::hasColumn('venda_itens', 'custo_situacao')
            && Schema::hasColumn('venda_itens', 'custo_total');

        $select = ['venda_itens.venda_id', 'venda_itens.id'];
        if ($hasCusto) {
            $select[] = 'venda_itens.custo_situacao';
            $select[] = 'venda_itens.custo_total';
        }

        foreach (DB::table('venda_itens')->whereIn('venda_itens.venda_id', $vendaIds)->orderBy('venda_itens.id')->get($select) as $row) {
            $vid = (int) $row->venda_id;
            if (! $hasCusto) {
                $map[$vid][] = (object) [
                    'custo_situacao' => null,
                    'custo_total' => null,
                ];

                continue;
            }
            $map[$vid][] = $row;
        }

        return $map;
    }

    /**
     * @param  list<int>  $vendaIds
     * @return array<int, float|string>
     */
    private static function loadDevolucaoReceitaByVendaIds(array $vendaIds): array
    {
        if ($vendaIds === [] || ! Schema::hasTable('devolucoes_venda')) {
            return [];
        }

        $out = [];
        DB::table('devolucoes_venda')
            ->where('situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
            ->whereIn('venda_id', $vendaIds)
            ->selectRaw('venda_id, COALESCE(SUM(total), 0) as total_dev')
            ->groupBy('venda_id')
            ->get()
            ->each(function ($row) use (&$out): void {
                $out[(int) $row->venda_id] = $row->total_dev;
            });

        return $out;
    }

    /**
     * @param  list<int>  $vendaIds
     * @param  array<int, float|string>  $devsComReceita
     * @return array<int, list<object>>
     */
    private static function loadDevolucaoItensCustoByVendaIds(array $vendaIds, array $devsComReceita): array
    {
        $map = [];
        foreach ($vendaIds as $id) {
            $map[$id] = [];
        }

        if ($vendaIds === []
            || ! Schema::hasTable('devolucoes_venda')
            || ! Schema::hasTable('devolucao_venda_itens')
        ) {
            return $map;
        }

        $hasCusto = Schema::hasColumn('devolucao_venda_itens', 'custo_situacao')
            && Schema::hasColumn('devolucao_venda_itens', 'custo_total');

        $select = [
            'devolucoes_venda.venda_id',
            'devolucao_venda_itens.id',
        ];
        if ($hasCusto) {
            $select[] = 'devolucao_venda_itens.custo_situacao';
            $select[] = 'devolucao_venda_itens.custo_total';
        }

        $rows = DB::table('devolucao_venda_itens')
            ->join('devolucoes_venda', 'devolucoes_venda.id', '=', 'devolucao_venda_itens.devolucao_venda_id')
            ->where('devolucoes_venda.situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
            ->whereIn('devolucoes_venda.venda_id', $vendaIds)
            ->get($select);

        foreach ($rows as $row) {
            $vid = (int) $row->venda_id;
            if (! $hasCusto) {
                $map[$vid][] = (object) [
                    'custo_situacao' => null,
                    'custo_total' => null,
                ];

                continue;
            }
            $map[$vid][] = $row;
        }

        foreach ($devsComReceita as $vid => $total) {
            $vid = (int) $vid;
            if (TefMoney::cmp(TefMoney::normalize($total), '0') === 1 && ($map[$vid] ?? []) === []) {
                $map[$vid][] = (object) [
                    'custo_situacao' => null,
                    'custo_total' => null,
                ];
            }
        }

        return $map;
    }
}
