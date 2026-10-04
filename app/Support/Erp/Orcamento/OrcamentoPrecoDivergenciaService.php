<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Product;
use App\Models\ProductGrade;
use App\Models\PromocaoItem;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvConfig;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class OrcamentoPrecoDivergenciaService
{
    public function __construct(
        private readonly OrcamentoPrecoService $precoService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $itens
     * @return list<array{
     *     index: int,
     *     key: string,
     *     codigo: string,
     *     descricao: string,
     *     preco_orcamento: float,
     *     preco_atual: float
     * }>
     */
    public function detectar(array $itens): array
    {
        if ($itens === []) {
            return [];
        }

        $productIds = [];
        $gradeIds = [];

        foreach ($itens as $row) {
            $productId = (int) ($row['product_id'] ?? 0);

            if ($productId > 0) {
                $productIds[] = $productId;
            }

            $gradeId = (int) ($row['product_grade_id'] ?? 0);

            if ($gradeId > 0) {
                $gradeIds[] = $gradeId;
            }
        }

        if ($productIds === []) {
            return [];
        }

        $productIds = array_values(array_unique($productIds));

        $products = Product::query()
            ->with('empresaPrecos')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $this->carregarPrecosAgrupados($products);

        $grades = $gradeIds === []
            ? collect()
            : ProductGrade::query()
                ->whereIn('id', array_values(array_unique($gradeIds)))
                ->get()
                ->keyBy('id');

        $divergencias = [];

        foreach ($itens as $index => $row) {
            $productId = (int) ($row['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $product = $products->get($productId);

            if (! $product) {
                continue;
            }

            if ($product->preco_variavel) {
                continue;
            }

            $gradeId = (int) ($row['product_grade_id'] ?? 0);
            $grade = $gradeId > 0 ? $grades->get($gradeId) : null;

            $quantidade = ErpMoney::parseBr($row['quantidade'] ?? 0, 3);
            $precoOrcamento = round(ErpMoney::parseBr($row['preco_unitario'] ?? 0), 2);
            $precoAtual = round($this->precoService->resolvePreco($product, $quantidade, $grade), 2);

            if (abs($precoOrcamento - $precoAtual) < 0.01) {
                continue;
            }

            $divergencias[] = [
                'index' => (int) $index,
                'key' => (string) ($row['key'] ?? $index),
                'codigo' => (string) ($row['product_codigo'] ?? $product->codigo ?? ''),
                'descricao' => (string) ($row['descricao'] ?? $product->descricao ?? ''),
                'preco_orcamento' => $precoOrcamento,
                'preco_atual' => $precoAtual,
            ];
        }

        return $divergencias;
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function carregarPrecosAgrupados(Collection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $config = new PdvConfig;
        $tableId = $config->habilitarTabelaPreco() ? $config->priceTableId() : null;

        if ($tableId) {
            $products->load([
                'priceTableItems' => fn ($query) => $query->where('price_table_id', $tableId),
            ]);
        }

        $empresaId = (int) (session('erp_empresa_id') ?? 0);

        if ($empresaId <= 0) {
            return;
        }

        $hoje = Carbon::today()->toDateString();
        $mins = PromocaoItem::query()
            ->selectRaw('product_id, MIN(preco_promocao) as preco_promocao')
            ->whereIn('product_id', $products->modelKeys())
            ->whereHas('promocao', function ($query) use ($empresaId, $hoje): void {
                $query->where('empresa_id', $empresaId)
                    ->where('ativa', true)
                    ->whereDate('data_inicio', '<=', $hoje)
                    ->whereDate('data_fim', '>=', $hoje);
            })
            ->groupBy('product_id')
            ->pluck('preco_promocao', 'product_id');

        foreach ($products as $product) {
            $min = $mins->get($product->id);
            $product->setRelation('precoPromocaoLote', $min === null ? null : (float) $min);
        }
    }
}
