<?php

namespace App\Support\Erp\Pdv;

use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\ProductGrade;
use App\Models\ProductSerial;
use Illuminate\Support\Facades\DB;

final class PdvStockService
{
    public function baixaItemVenda(
        Product $product,
        float $quantidade,
        ?int $productGradeId = null,
        ?int $productSerialId = null,
        ?string $docSaida = null,
    ): void {
        if ($product->is_servico) {
            if ($productSerialId) {
                $this->baixaSerial($productSerialId, $docSaida);
            }

            return;
        }

        if ($product->is_composicao) {
            $this->baixaComposicao($product, $quantidade, $docSaida);

            return;
        }

        $this->decrementarEstoqueProduto($product, $quantidade);

        if ($productGradeId && $product->contr_est_grade) {
            ProductGrade::query()
                ->whereKey($productGradeId)
                ->where('product_id', $product->id)
                ->decrement('qtd', $quantidade);
        }

        if ($productSerialId) {
            $this->baixaSerial($productSerialId, $docSaida);
        }
    }

    public function validaEstoqueComposicao(Product $product, float $quantidade): ?string
    {
        if (! $product->is_composicao) {
            return null;
        }

        $componentes = ProductComposition::query()
            ->where('product_id', $product->id)
            ->with('componentProduct')
            ->get();

        foreach ($componentes as $componente) {
            $comp = $componente->componentProduct;

            if (! $comp || $comp->is_servico) {
                continue;
            }

            $qtdNecessaria = $quantidade * (float) $componente->quantidade;

            if ($comp->is_composicao) {
                $erro = $this->validaEstoqueComposicao($comp, $qtdNecessaria);

                if ($erro) {
                    return $erro;
                }

                continue;
            }

            if ((float) $comp->estoque < $qtdNecessaria) {
                return 'Estoque insuficiente do componente: ' . $comp->descricao;
            }
        }

        return null;
    }

    public function validaEstoqueGrade(Product $product, ?int $productGradeId, float $quantidade): ?string
    {
        if (! $product->is_grade || ! $product->contr_est_grade || ! $productGradeId) {
            return null;
        }

        $grade = ProductGrade::query()
            ->whereKey($productGradeId)
            ->where('product_id', $product->id)
            ->first();

        if (! $grade) {
            return 'Grade não encontrada.';
        }

        if ((float) $grade->qtd < $quantidade) {
            return 'Quantidade grade insuficiente.';
        }

        return null;
    }

    private function baixaComposicao(Product $product, float $quantidade, ?string $docSaida): void
    {
        $componentes = ProductComposition::query()
            ->where('product_id', $product->id)
            ->with('componentProduct')
            ->get();

        foreach ($componentes as $componente) {
            $comp = $componente->componentProduct;

            if (! $comp) {
                continue;
            }

            $qtd = $quantidade * (float) $componente->quantidade;
            $this->baixaItemVenda($comp, $qtd, null, null, $docSaida);
        }
    }

    private function decrementarEstoqueProduto(Product $product, float $quantidade): void
    {
        Product::query()
            ->whereKey($product->id)
            ->decrement('estoque', $quantidade);
    }

    private function baixaSerial(int $productSerialId, ?string $docSaida): void
    {
        ProductSerial::query()
            ->whereKey($productSerialId)
            ->where('situacao', 'DISPONIVEL')
            ->update([
                'situacao' => 'VENDIDO',
                'doc_saida' => $docSaida,
                'data_baixa' => now()->toDateString(),
            ]);
    }

    public function estornoItemVenda(
        Product $product,
        float $quantidade,
        ?int $productGradeId = null,
        ?int $productSerialId = null,
    ): void {
        if ($product->is_servico) {
            if ($productSerialId) {
                $this->estornoSerial($productSerialId);
            }

            return;
        }

        if ($product->is_composicao) {
            $this->estornoComposicao($product, $quantidade);

            return;
        }

        Product::query()
            ->whereKey($product->id)
            ->increment('estoque', $quantidade);

        if ($productGradeId && $product->contr_est_grade) {
            ProductGrade::query()
                ->whereKey($productGradeId)
                ->where('product_id', $product->id)
                ->increment('qtd', $quantidade);
        }

        if ($productSerialId) {
            $this->estornoSerial($productSerialId);
        }
    }

    private function estornoComposicao(Product $product, float $quantidade): void
    {
        $componentes = ProductComposition::query()
            ->where('product_id', $product->id)
            ->with('componentProduct')
            ->get();

        foreach ($componentes as $componente) {
            $comp = $componente->componentProduct;

            if (! $comp) {
                continue;
            }

            $qtd = $quantidade * (float) $componente->quantidade;
            $this->estornoItemVenda($comp, $qtd);
        }
    }

    private function estornoSerial(int $productSerialId): void
    {
        ProductSerial::query()
            ->whereKey($productSerialId)
            ->update([
                'situacao' => 'DISPONIVEL',
                'doc_saida' => null,
                'data_baixa' => null,
            ]);
    }
}
