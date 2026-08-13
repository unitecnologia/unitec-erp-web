<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Product;
use App\Models\ProductGrade;
use App\Support\Erp\ErpMoney;

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

        $products = Product::query()
            ->whereIn('id', array_values(array_unique($productIds)))
            ->get()
            ->keyBy('id');

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
}
