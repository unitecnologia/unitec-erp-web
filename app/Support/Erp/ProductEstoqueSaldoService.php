<?php

namespace App\Support\Erp;

use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo físico por depósito (product_estoque_saldos) com espelho em products.estoque.
 *
 * Quando o vendedor tem estoque_id (ex.: 2 — ALENCAR), a FV reserva/baixa nesse depósito.
 * Se ainda não existir linha no depósito, usa o estoque global como ponto de partida (legado).
 */
final class ProductEstoqueSaldoService
{
    public function tabelaDisponivel(): bool
    {
        return Schema::hasTable('product_estoque_saldos');
    }

    public function fisico(int $productId, ?int $estoqueId = null): float
    {
        $global = (float) (Product::query()->whereKey($productId)->value('estoque') ?? 0);

        if ($estoqueId === null || ! $this->tabelaDisponivel()) {
            return $global;
        }

        $saldo = ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->where('estoque_id', $estoqueId)
            ->value('quantidade');

        if ($saldo === null) {
            return $global;
        }

        return (float) $saldo;
    }

    /**
     * Decrementa o depósito (se informado) e o estoque global do produto.
     */
    public function decrementar(int $productId, float $quantidade, ?int $estoqueId = null): void
    {
        if ($quantidade == 0.0) {
            return;
        }

        $this->ajustar($productId, -$quantidade, $estoqueId);
    }

    /**
     * Incrementa o depósito (se informado) e o estoque global do produto.
     */
    public function incrementar(int $productId, float $quantidade, ?int $estoqueId = null): void
    {
        if ($quantidade == 0.0) {
            return;
        }

        $this->ajustar($productId, $quantidade, $estoqueId);
    }

    private function ajustar(int $productId, float $delta, ?int $estoqueId): void
    {
        DB::transaction(function () use ($productId, $delta, $estoqueId): void {
            $product = Product::query()->whereKey($productId)->lockForUpdate()->first();

            if ($product === null) {
                return;
            }

            if ($estoqueId !== null && $this->tabelaDisponivel()) {
                $saldo = ProductEstoqueSaldo::query()
                    ->where('product_id', $productId)
                    ->where('estoque_id', $estoqueId)
                    ->lockForUpdate()
                    ->first();

                if ($saldo === null) {
                    $saldo = ProductEstoqueSaldo::query()->create([
                        'product_id' => $productId,
                        'estoque_id' => $estoqueId,
                        'quantidade' => (float) $product->estoque,
                    ]);

                    $saldo = ProductEstoqueSaldo::query()
                        ->whereKey($saldo->id)
                        ->lockForUpdate()
                        ->first();
                }

                if ($saldo !== null) {
                    $saldo->quantidade = round((float) $saldo->quantidade + $delta, 3);
                    $saldo->save();
                }
            }

            $product->estoque = round((float) $product->estoque + $delta, 3);
            $product->save();
        });
    }
}
