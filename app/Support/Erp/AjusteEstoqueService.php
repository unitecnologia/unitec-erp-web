<?php

namespace App\Support\Erp;

use App\Models\AjusteEstoque;
use App\Models\Empresa;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final class AjusteEstoqueService
{
    public function criar(int $productId, string $data, float $qtdAjust): AjusteEstoque
    {
        return DB::transaction(function () use ($productId, $data, $qtdAjust): AjusteEstoque {
            $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();

            $this->garantirEstoquePermitido($product, $qtdAjust);

            $ajuste = AjusteEstoque::query()->create([
                'data' => $data,
                'product_id' => $productId,
                'qtd_ajust' => $qtdAjust,
            ]);

            $product->update([
                'estoque' => round((float) $product->estoque + $qtdAjust, 3),
            ]);

            return $ajuste;
        });
    }

    public function atualizar(AjusteEstoque $ajuste, string $data, float $qtdAjust): AjusteEstoque
    {
        return DB::transaction(function () use ($ajuste, $data, $qtdAjust): AjusteEstoque {
            $ajuste = AjusteEstoque::query()->whereKey($ajuste->getKey())->lockForUpdate()->firstOrFail();
            $product = Product::query()->whereKey($ajuste->product_id)->lockForUpdate()->firstOrFail();

            $deltaLiquido = $qtdAjust - (float) $ajuste->qtd_ajust;
            $estoqueAposReversao = round((float) $product->estoque - (float) $ajuste->qtd_ajust, 3);

            if ($this->bloquearEstoqueNegativo() && ($estoqueAposReversao + $qtdAjust) < 0) {
                throw new \RuntimeException('Ajuste deixaria o estoque negativo.');
            }

            $product->update(['estoque' => round($estoqueAposReversao + $qtdAjust, 3)]);

            $ajuste->update([
                'data' => $data,
                'qtd_ajust' => $qtdAjust,
            ]);

            return $ajuste->fresh();
        });
    }

    public function excluir(AjusteEstoque $ajuste): void
    {
        DB::transaction(function () use ($ajuste): void {
            $ajuste = AjusteEstoque::query()->whereKey($ajuste->getKey())->lockForUpdate()->firstOrFail();
            $product = Product::query()->whereKey($ajuste->product_id)->lockForUpdate()->first();

            if ($product) {
                $product->update([
                    'estoque' => round((float) $product->estoque - (float) $ajuste->qtd_ajust, 3),
                ]);
            }

            $ajuste->delete();
        });
    }

    public function proximoCodigoExibicao(): int
    {
        return (int) (AjusteEstoque::query()->max('id') ?? 0) + 1;
    }

    private function garantirEstoquePermitido(Product $product, float $qtdAjust): void
    {
        if (! $this->bloquearEstoqueNegativo()) {
            return;
        }

        if (((float) $product->estoque + $qtdAjust) < 0) {
            throw new \RuntimeException('Ajuste deixaria o estoque negativo.');
        }
    }

    private function bloquearEstoqueNegativo(): bool
    {
        $empresaId = session('erp_empresa_id', auth()->user()?->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        return (bool) ($empresa?->param_geral_bloquear_estoque_negativo ?? false);
    }
}
