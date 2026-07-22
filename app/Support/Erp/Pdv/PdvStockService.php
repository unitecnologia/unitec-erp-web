<?php

namespace App\Support\Erp\Pdv;

use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\ProductGrade;
use App\Models\ProductSerial;
use App\Support\Erp\ProductEstoqueSaldoService;

final class PdvStockService
{
    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
    ) {}

    public function baixaItemVenda(
        Product $product,
        float $quantidade,
        ?int $productGradeId = null,
        ?int $productSerialId = null,
        ?string $docSaida = null,
        ?int $estoqueId = null,
    ): void {
        if ($product->is_servico) {
            if ($productSerialId) {
                $this->baixaSerial($productSerialId, $docSaida);
            }

            return;
        }

        if ($product->is_composicao) {
            $this->baixaComposicao($product, $quantidade, $docSaida, $estoqueId);

            return;
        }

        $this->decrementarEstoqueProduto($product, $quantidade, $estoqueId);

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

    public function validaEstoqueComposicao(Product $product, float $quantidade, ?int $estoqueId = null): ?string
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
                $erro = $this->validaEstoqueComposicao($comp, $qtdNecessaria, $estoqueId);

                if ($erro) {
                    return $erro;
                }

                continue;
            }

            if ($this->saldos->fisico((int) $comp->id, $estoqueId) < $qtdNecessaria) {
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

    private function baixaComposicao(Product $product, float $quantidade, ?string $docSaida, ?int $estoqueId = null): void
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
            $this->baixaItemVenda($comp, $qtd, null, null, $docSaida, $estoqueId);
        }
    }

    private function decrementarEstoqueProduto(Product $product, float $quantidade, ?int $estoqueId = null): void
    {
        $this->saldos->decrementar((int) $product->id, $quantidade, $estoqueId);
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
        ?int $estoqueId = null,
    ): void {
        if ($product->is_servico) {
            if ($productSerialId) {
                $this->estornoSerial($productSerialId);
            }

            return;
        }

        if ($product->is_composicao) {
            $this->estornoComposicao($product, $quantidade, $estoqueId);

            return;
        }

        $this->saldos->incrementar((int) $product->id, $quantidade, $estoqueId);

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

    private function estornoComposicao(Product $product, float $quantidade, ?int $estoqueId = null): void
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
            $this->estornoItemVenda($comp, $qtd, null, null, $estoqueId);
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
