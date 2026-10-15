<?php

namespace App\Support\Erp\Compras;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\DevolucaoCompra;
use App\Models\DevolucaoCompraItem;
use App\Support\Erp\NotaFornecedor\NotaFornecedorDevolucaoFiscalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Classifica devolução parcial/total pelo saldo global da compra
 * e monta indicadores da listagem em lote (sem N+1).
 */
final class CompraDevolucaoIndicadorService
{
    public function __construct(
        private readonly NotaFornecedorDevolucaoFiscalService $fiscal = new NotaFornecedorDevolucaoFiscalService(),
    ) {}

    /**
     * @param  array<int, float>  $qtdPorCompraItemId  quantidades da devolução atual (aberta)
     */
    public function classificarTipo(
        int $compraId,
        array $qtdPorCompraItemId = [],
        ?int $excetoDevolucaoId = null,
    ): string {
        $itens = CompraItem::query()
            ->with('notaFornecedorItem')
            ->where('compra_id', $compraId)
            ->get(['id', 'quantidade', 'nota_fornecedor_item_id']);

        if ($itens->isEmpty()) {
            return DevolucaoCompra::TIPO_PARCIAL;
        }

        $jaPorItem = $this->quantidadesFinalizadasPorItem(
            $itens->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $excetoDevolucaoId,
        );

        $restanteTotal = 0.0;
        $movimentado = 0.0;

        foreach ($itens as $item) {
            $itemId = (int) $item->id;
            $original = $this->fiscal->quantidadeOriginal($item);
            $ja = round((float) ($jaPorItem[$itemId] ?? 0), 4);
            $adicionar = round((float) ($qtdPorCompraItemId[$itemId] ?? 0), 4);
            $restante = round(max(0, $original - $ja - $adicionar), 4);
            $restanteTotal = round($restanteTotal + $restante, 4);
            $movimentado = round($movimentado + $ja + $adicionar, 4);
        }

        if ($movimentado <= 0.00005) {
            return DevolucaoCompra::TIPO_PARCIAL;
        }

        return $restanteTotal <= 0.00005
            ? DevolucaoCompra::TIPO_TOTAL
            : DevolucaoCompra::TIPO_PARCIAL;
    }

    public function compraTemQuantidadeDevolvivel(int $compraId, ?int $excetoDevolucaoId = null): bool
    {
        $itens = CompraItem::query()
            ->with('notaFornecedorItem')
            ->where('compra_id', $compraId)
            ->get(['id', 'quantidade', 'nota_fornecedor_item_id']);

        if ($itens->isEmpty()) {
            return false;
        }

        $jaPorItem = $this->quantidadesFinalizadasPorItem(
            $itens->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $excetoDevolucaoId,
        );

        foreach ($itens as $item) {
            $original = $this->fiscal->quantidadeOriginal($item);
            $ja = round((float) ($jaPorItem[(int) $item->id] ?? 0), 4);

            if (($original - $ja) > 0.00005) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lookup: compras que ainda têm quantidade devolvível (ignora flag tipo_devolucao).
     *
     * @param  Builder<Compra>  $query
     * @return Builder<Compra>
     */
    public function aplicarFiltroComQuantidadeDevolvivel(Builder $query): Builder
    {
        $prefix = $query->getConnection()->getTablePrefix();
        $compraTable = (new Compra)->getTable();
        $itemTable = (new CompraItem)->getTable();
        // whereRaw não recebe prefixo automático
        $devItemTable = $prefix.(new DevolucaoCompraItem)->getTable();
        $devTable = $prefix.(new DevolucaoCompra)->getTable();

        return $query->whereExists(function ($exists) use (
            $compraTable,
            $itemTable,
            $devItemTable,
            $devTable,
        ): void {
            $exists->selectRaw('1')
                ->from("{$itemTable} as ci")
                ->leftJoin('nota_fornecedor_itens as nfi', 'nfi.id', '=', 'ci.nota_fornecedor_item_id')
                ->whereColumn('ci.compra_id', "{$compraTable}.id")
                ->whereRaw(
                    '(COALESCE(nfi.quantidade, ci.quantidade, 0) - COALESCE(('
                    .'SELECT SUM(dci.qtd) FROM '.$devItemTable.' AS dci '
                    .'INNER JOIN '.$devTable.' AS d ON d.id = dci.devolucao_compra_id '
                    .'WHERE dci.compra_item_id = ci.id AND d.situacao = ?'
                    .'), 0)) > 0.00005',
                    [DevolucaoCompra::SITUACAO_FINALIZADA],
                );
        });
    }

    /**
     * Valor total devolvido (itens de devoluções finalizadas) por compra — 1 query.
     *
     * @param  list<int>  $compraIds
     * @return array<int, float> compra_id => valor
     */
    public function valoresDevolvidosEmLote(array $compraIds): array
    {
        $compraIds = array_values(array_unique(array_filter(array_map('intval', $compraIds))));

        if ($compraIds === []) {
            return [];
        }

        $saida = array_fill_keys($compraIds, 0.0);

        // selectRaw não recebe prefixo automático; join/where sim.
        $prefix = DB::getTablePrefix();
        $dev = $prefix.(new DevolucaoCompra)->getTable();
        $item = $prefix.(new DevolucaoCompraItem)->getTable();

        $rows = DevolucaoCompraItem::query()
            ->join('devolucoes_compra', 'devolucoes_compra.id', '=', 'devolucao_compra_itens.devolucao_compra_id')
            ->where('devolucoes_compra.situacao', DevolucaoCompra::SITUACAO_FINALIZADA)
            ->whereIn('devolucoes_compra.compra_id', $compraIds)
            ->groupBy('devolucoes_compra.compra_id')
            ->selectRaw(
                "{$dev}.compra_id as compra_id, "
                ."SUM(COALESCE({$item}.total, ROUND({$item}.qtd * {$item}.preco, 2), 0)) as valor_devolvido"
            )
            ->get();

        foreach ($rows as $row) {
            $saida[(int) $row->compra_id] = round((float) $row->valor_devolvido, 2);
        }

        return $saida;
    }

    /**
     * @param  list<int>  $compraItemIds
     * @return array<int, float>
     */
    private function quantidadesFinalizadasPorItem(array $compraItemIds, ?int $excetoDevolucaoId): array
    {
        if ($compraItemIds === []) {
            return [];
        }

        $query = DevolucaoCompraItem::query()
            ->selectRaw('compra_item_id, SUM(qtd) as total_qtd')
            ->whereIn('compra_item_id', $compraItemIds)
            ->whereHas('devolucao', function ($q) use ($excetoDevolucaoId): void {
                $q->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA);

                if ($excetoDevolucaoId) {
                    $q->where('id', '!=', $excetoDevolucaoId);
                }
            })
            ->groupBy('compra_item_id');

        $mapa = [];
        foreach ($query->get() as $row) {
            $mapa[(int) $row->compra_item_id] = round((float) $row->total_qtd, 4);
        }

        return $mapa;
    }
}
