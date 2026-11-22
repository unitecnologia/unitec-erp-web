<?php

namespace App\Support\Erp\Reports\LucratividadeProdutos;

use App\Models\DevolucaoVenda;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaCustoResolver;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaReceitaRateio;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Tef\TefMoney;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lucratividade por produto — mesmas regras da Lucratividade por Venda (D2.6).
 *
 * Fonte: vendas fechadas (espelho PDV/TV/FV). Sem custo cadastral atual.
 * Rateio de cabeçalho por venda → soma por produto. Devoluções finalizadas abatem.
 */
final class LucratividadeProdutosAggregator
{
    public const VENDA_LIMIT = 5000;

    /**
     * @return array{
     *   rows: Collection<int, object>,
     *   truncated: bool,
     *   total_vendas: int,
     *   vendas_processadas: int,
     *   venda_limit: int
     * }
     */
    public function aggregate(CarbonInterface $de, CarbonInterface $ate, ?Request $request = null): array
    {
        $vazio = [
            'rows' => collect(),
            'truncated' => false,
            'total_vendas' => 0,
            'vendas_processadas' => 0,
            'venda_limit' => self::VENDA_LIMIT,
        ];

        if (! Schema::hasTable('venda_itens') || ! Schema::hasTable('vendas')) {
            return $vazio;
        }

        $hasCusto = Schema::hasColumn('venda_itens', 'custo_situacao')
            && Schema::hasColumn('venda_itens', 'custo_total');

        /** @var Builder<\Illuminate\Database\Eloquent\Model> $baseVendas */
        $baseVendas = Venda::query()
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereBetween('vendas.data', [$de->toDateString(), $ate->toDateString()]);

        if ($request && Schema::hasColumn((new Venda)->getTable(), 'empresa_id')) {
            ReportEmpresaScope::applyToQuery($baseVendas, $request, 'vendas.empresa_id');
        }

        $totalVendas = (int) (clone $baseVendas)->count();
        if ($totalVendas === 0) {
            return $vazio;
        }

        $truncated = $totalVendas > self::VENDA_LIMIT;

        $vendaIds = (clone $baseVendas)
            ->orderByDesc('vendas.data')
            ->orderByDesc('vendas.id')
            ->limit(self::VENDA_LIMIT)
            ->pluck('vendas.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $select = [
            'venda_itens.id',
            'venda_itens.venda_id',
            'venda_itens.product_id',
            'venda_itens.quantidade',
            'venda_itens.total',
            'vendas.total as venda_total',
            'products.codigo',
            'products.descricao',
            'products.grupo',
        ];
        if ($hasCusto) {
            $select[] = 'venda_itens.custo_situacao';
            $select[] = 'venda_itens.custo_total';
        }

        /** @var array<int, list<object>> $porVenda */
        $porVenda = [];
        foreach (array_chunk($vendaIds, 1000) as $chunk) {
            // Todos os itens da venda (incl. sem product_id) — rateio alinhado a vendas.total.
            $itens = VendaItem::query()
                ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
                ->leftJoin('products', 'products.id', '=', 'venda_itens.product_id')
                ->whereIn('venda_itens.venda_id', $chunk)
                ->orderBy('venda_itens.venda_id')
                ->orderBy('venda_itens.id')
                ->get($select);

            foreach ($itens as $row) {
                $porVenda[(int) $row->venda_id][] = $row;
            }
        }

        if ($porVenda === []) {
            return [
                'rows' => collect(),
                'truncated' => $truncated,
                'total_vendas' => $totalVendas,
                'vendas_processadas' => count($vendaIds),
                'venda_limit' => self::VENDA_LIMIT,
            ];
        }

        /** @var array<int, array{
         *   product_id: int,
         *   codigo: string,
         *   descricao: string,
         *   grupo: string,
         *   qtd: float,
         *   receita: float,
         *   linhas_venda: list<array{custo_situacao: mixed, custo_total: mixed}>,
         *   linhas_dev: list<array{custo_situacao: mixed, custo_total: mixed}>,
         * }> $acc
         */
        $acc = [];

        foreach ($porVenda as $linhas) {
            $vendaTotal = (float) ($linhas[0]->venda_total ?? 0);
            $totais = array_map(static fn ($r) => $r->total ?? 0, $linhas);
            $rateados = LucratividadeVendaReceitaRateio::totaisRateados($vendaTotal, $totais);

            foreach ($linhas as $i => $row) {
                $pid = (int) ($row->product_id ?? 0);
                if ($pid <= 0) {
                    continue;
                }

                if (! isset($acc[$pid])) {
                    $acc[$pid] = [
                        'product_id' => $pid,
                        'codigo' => (string) ($row->codigo ?? ''),
                        'descricao' => (string) ($row->descricao ?? ''),
                        'grupo' => (string) ($row->grupo ?? ''),
                        'qtd' => 0.0,
                        'receita' => 0.0,
                        'linhas_venda' => [],
                        'linhas_dev' => [],
                    ];
                }

                $receitaLinha = (float) ($rateados[$i] ?? TefMoney::normalize($row->total ?? 0));
                $acc[$pid]['qtd'] += (float) ($row->quantidade ?? 0);
                $acc[$pid]['receita'] += $receitaLinha;
                $acc[$pid]['linhas_venda'][] = [
                    'custo_situacao' => $hasCusto ? ($row->custo_situacao ?? null) : null,
                    'custo_total' => $hasCusto ? ($row->custo_total ?? null) : null,
                ];
            }
        }

        $this->aplicarDevolucoes($acc, array_keys($porVenda));

        $out = [];
        foreach ($acc as $pid => $row) {
            $resolved = LucratividadeVendaCustoResolver::resolver(
                $row['linhas_venda'],
                $row['linhas_dev'],
            );

            $receita = max(0.0, round((float) $row['receita'], 2));
            $qtd = round((float) $row['qtd'], 3);
            $disponivel = $resolved['disponivel'] && $resolved['custo_liquido'] !== null;
            $custo = $disponivel ? (float) $resolved['custo_liquido'] : null;
            $lucro = null;
            $margem = null;
            if ($disponivel && $custo !== null) {
                $lucro = round($receita - $custo, 2);
                $margem = $receita > 0.0000001 ? ($lucro / $receita) * 100 : 0.0;
            }

            if (abs($qtd) < 0.0005 && abs($receita) < 0.005 && ($custo === null || abs($custo) < 0.005)) {
                continue;
            }

            $out[] = (object) [
                'product_id' => $pid,
                'codigo' => $row['codigo'],
                'descricao' => $row['descricao'],
                'grupo' => $row['grupo'],
                'qtd' => $qtd,
                'receita' => $receita,
                'custo' => $custo,
                'lucro' => $lucro,
                'margem' => $margem,
                'custo_disponivel' => $disponivel,
            ];
        }

        return [
            'rows' => collect($out)->sortByDesc(static fn (object $r): float => (float) $r->receita)->values(),
            'truncated' => $truncated,
            'total_vendas' => $totalVendas,
            'vendas_processadas' => count($vendaIds),
            'venda_limit' => self::VENDA_LIMIT,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $acc
     * @param  list<int>  $vendaIds
     */
    private function aplicarDevolucoes(array &$acc, array $vendaIds): void
    {
        if ($vendaIds === []
            || ! Schema::hasTable('devolucoes_venda')
            || ! Schema::hasTable('devolucao_venda_itens')
        ) {
            return;
        }

        $hasCustoDev = Schema::hasColumn('devolucao_venda_itens', 'custo_situacao')
            && Schema::hasColumn('devolucao_venda_itens', 'custo_total');

        $select = [
            'devolucao_venda_itens.product_id',
            'devolucao_venda_itens.qtd',
            'devolucao_venda_itens.total',
        ];
        if ($hasCustoDev) {
            $select[] = 'devolucao_venda_itens.custo_situacao';
            $select[] = 'devolucao_venda_itens.custo_total';
        }

        foreach (array_chunk($vendaIds, 1000) as $chunk) {
            $rows = DB::table('devolucao_venda_itens')
                ->join('devolucoes_venda', 'devolucoes_venda.id', '=', 'devolucao_venda_itens.devolucao_venda_id')
                ->where('devolucoes_venda.situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
                ->whereIn('devolucoes_venda.venda_id', $chunk)
                ->whereNotNull('devolucao_venda_itens.product_id')
                ->get($select);

            foreach ($rows as $row) {
                $pid = (int) $row->product_id;
                if ($pid <= 0 || ! isset($acc[$pid])) {
                    continue;
                }

                $acc[$pid]['qtd'] -= (float) ($row->qtd ?? 0);
                $acc[$pid]['receita'] -= (float) ($row->total ?? 0);

                $acc[$pid]['linhas_dev'][] = [
                    'custo_situacao' => $hasCustoDev ? ($row->custo_situacao ?? null) : null,
                    'custo_total' => $hasCustoDev ? ($row->custo_total ?? null) : null,
                ];
            }
        }
    }
}
