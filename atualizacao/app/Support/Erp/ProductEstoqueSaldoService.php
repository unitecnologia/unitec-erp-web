<?php

namespace App\Support\Erp;

use App\Models\Estoque;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo físico por depósito (product_estoque_saldos) com espelho em products.estoque.
 *
 * Quando o vendedor tem estoque_id (ex.: 2 — ALENCAR), a FV reserva/baixa nesse depósito.
 * Estoque ainda não lançado em depósitos fica no depósito principal da empresa (legado).
 */
final class ProductEstoqueSaldoService
{
    private ?int $estoquePrincipalIdCache = null;

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

        /** @var array<int, float> $saldos */
        $saldos = ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->pluck('quantidade', 'estoque_id')
            ->map(fn ($q): float => (float) $q)
            ->all();

        $principalId = $this->estoquePrincipalId();
        $saldoDeposito = $saldos[$estoqueId] ?? null;
        $sumDepots = array_sum($saldos);
        $naoDistribuido = max(0.0, round($global - $sumDepots, 3));

        if ($saldoDeposito === null) {
            return $principalId !== null && $estoqueId === $principalId
                ? $global
                : 0.0;
        }

        $fisico = (float) $saldoDeposito;

        if ($naoDistribuido > 0 && $principalId !== null && $estoqueId === $principalId) {
            $fisico += $naoDistribuido;
        }

        return $fisico;
    }

    /**
     * Decrementa o depósito (se informado) e o estoque global do produto.
     */
    public function decrementar(int $productId, float $quantidade, ?int $estoqueId = null, ?Empresa $empresa = null): void
    {
        if ($quantidade == 0.0) {
            return;
        }

        $this->ajustar($productId, -$quantidade, $estoqueId, $empresa);
    }

    /**
     * Incrementa o depósito (se informado) e o estoque global do produto.
     */
    public function incrementar(int $productId, float $quantidade, ?int $estoqueId = null, ?Empresa $empresa = null): void
    {
        if ($quantidade == 0.0) {
            return;
        }

        $this->ajustar($productId, $quantidade, $estoqueId, $empresa);
    }

    private function ajustar(int $productId, float $delta, ?int $estoqueId, ?Empresa $empresa = null): void
    {
        DB::transaction(function () use ($productId, $delta, $estoqueId, $empresa): void {
            $product = Product::query()->whereKey($productId)->lockForUpdate()->first();

            if ($product === null) {
                return;
            }

            if ($delta < 0 && EstoqueNegativoPolicy::ativo($empresa)) {
                $saida = abs($delta);
                $saldoDeposit = null;

                if ($estoqueId !== null && $this->tabelaDisponivel()) {
                    $this->materializarNaoDistribuido($productId, $estoqueId, $product);
                    $saldoDeposit = $this->fisico($productId, $estoqueId);

                    if ($saldoDeposit < $saida) {
                        throw new \RuntimeException(
                            'Estoque insuficiente para '.trim((string) ($product->descricao ?? $product->codigo ?? 'produto'))
                            .' (saldo: '.number_format($saldoDeposit, 3, ',', '.')
                            .', saída: '.number_format($saida, 3, ',', '.').').'
                            .' Bloqueio de estoque negativo está ativo.'
                        );
                    }
                }

                $saldoGlobal = (float) $product->estoque;

                if ($saldoGlobal < $saida) {
                    throw new \RuntimeException(
                        'Estoque insuficiente para '.trim((string) ($product->descricao ?? $product->codigo ?? 'produto'))
                        .' (saldo: '.number_format($saldoGlobal, 3, ',', '.')
                        .', saída: '.number_format($saida, 3, ',', '.').').'
                        .' Bloqueio de estoque negativo está ativo.'
                    );
                }
            }

            if ($estoqueId !== null && $this->tabelaDisponivel()) {
                $this->materializarNaoDistribuido($productId, $estoqueId, $product);

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

    /**
     * Move para o depósito principal o estoque global ainda não distribuído nos depósitos.
     */
    private function materializarNaoDistribuido(int $productId, int $estoqueId, Product $product): void
    {
        $principalId = $this->estoquePrincipalId();

        if ($principalId === null || $estoqueId !== $principalId) {
            return;
        }

        $sumDepots = (float) ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->sum('quantidade');

        $global = (float) $product->estoque;
        $gap = round($global - $sumDepots, 3);

        if ($gap <= 0) {
            return;
        }

        $saldo = ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->where('estoque_id', $estoqueId)
            ->lockForUpdate()
            ->first();

        if ($saldo === null) {
            ProductEstoqueSaldo::query()->create([
                'product_id' => $productId,
                'estoque_id' => $estoqueId,
                'quantidade' => $global,
            ]);

            return;
        }

        $saldo->quantidade = round((float) $saldo->quantidade + $gap, 3);
        $saldo->save();
    }

    private function estoquePrincipalId(): ?int
    {
        if ($this->estoquePrincipalIdCache !== null) {
            return $this->estoquePrincipalIdCache > 0 ? $this->estoquePrincipalIdCache : null;
        }

        if (! Schema::hasTable('estoques')) {
            $this->estoquePrincipalIdCache = 0;

            return null;
        }

        $empresaId = (int) (ErpContext::currentEmpresa()?->id ?? 0);

        if ($empresaId > 0) {
            $id = Estoque::query()
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->orderByRaw('CAST(codigo AS UNSIGNED)')
                ->orderBy('codigo')
                ->value('id');

            if ($id !== null) {
                $this->estoquePrincipalIdCache = (int) $id;

                return (int) $id;
            }
        }

        $id = Estoque::query()
            ->where('ativo', true)
            ->orderBy('id')
            ->value('id');

        $this->estoquePrincipalIdCache = $id !== null ? (int) $id : 0;

        return $id !== null ? (int) $id : null;
    }
}
