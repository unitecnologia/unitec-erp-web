<?php

namespace App\Support\Erp;

use App\Models\Estoque;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
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

    /** @var array<int, int|null> */
    private array $estoqueIdPorEmpresaCache = [];

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
            if ($sumDepots > 0) {
                return 0.0;
            }

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

    public function estoqueIdParaEmpresa(?int $empresaId): ?int
    {
        if ($empresaId === null || $empresaId <= 0 || ! Schema::hasTable('estoques')) {
            return null;
        }

        if (array_key_exists($empresaId, $this->estoqueIdPorEmpresaCache)) {
            return $this->estoqueIdPorEmpresaCache[$empresaId];
        }

        $id = Estoque::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->orderBy('codigo')
            ->value('id');

        $this->estoqueIdPorEmpresaCache[$empresaId] = $id !== null ? (int) $id : null;

        return $this->estoqueIdPorEmpresaCache[$empresaId];
    }

    public function suportaEstoquePorEmpresa(?int $empresaId): bool
    {
        return $this->tabelaDisponivel()
            && $this->estoqueIdParaEmpresa($empresaId) !== null;
    }

    /**
     * Saldo físico do depósito principal da empresa (mesmo critério da grade Estoque no cadastro).
     */
    public function fisicoEmpresa(int $productId, ?int $empresaId = null): float
    {
        $empresaId ??= (int) (ErpContext::currentEmpresa()?->id ?? session('erp_empresa_id') ?? 0);

        if (! $this->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null)) {
            return (float) (Product::query()->whereKey($productId)->value('estoque') ?? 0);
        }

        return $this->fisico($productId, $this->estoqueIdParaEmpresa($empresaId));
    }

    public function sqlEstoqueEmpresaExpression(?int $estoqueId = null): string
    {
        $tables = $this->tabelasSql();
        $products = $tables['products'];
        $saldos = $tables['saldos'];

        if ($estoqueId === null || $estoqueId <= 0) {
            return "{$products}.estoque";
        }

        $id = (int) $estoqueId;
        $saldoLoja = "(SELECT pes.quantidade FROM {$saldos} pes WHERE pes.product_id = {$products}.id AND pes.estoque_id = {$id} LIMIT 1)";
        $somaDepositos = "(SELECT COALESCE(SUM(s.quantidade), 0) FROM {$saldos} s WHERE s.product_id = {$products}.id)";
        $naoDistribuido = "(CASE WHEN {$products}.estoque > {$somaDepositos} THEN {$products}.estoque - {$somaDepositos} ELSE 0 END)";

        return "(CASE
  WHEN {$saldoLoja} IS NOT NULL THEN {$saldoLoja} + {$naoDistribuido}
  WHEN {$somaDepositos} > 0 THEN 0
  ELSE {$products}.estoque
END)";
    }

    public function applyEstoqueEmpresaSelect(Builder $query, ?int $empresaId): Builder
    {
        if (! $this->suportaEstoquePorEmpresa($empresaId)) {
            return $query;
        }

        if ($this->queryJaTemEstoqueEmpresa($query)) {
            return $query;
        }

        $expr = $this->sqlEstoqueEmpresaExpression($this->estoqueIdParaEmpresa($empresaId));
        $query->addSelect(DB::raw("{$expr} as estoque_empresa_atual"));

        return $query;
    }

    /**
     * @return array{products: string, saldos: string}
     */
    private function tabelasSql(): array
    {
        $connection = Product::query()->getConnection();
        $prefix = $connection->getTablePrefix();

        return [
            'products' => $prefix.(new Product)->getTable(),
            'saldos' => $prefix.(new ProductEstoqueSaldo)->getTable(),
        ];
    }

    public function tabelaProductsSql(): string
    {
        return $this->tabelasSql()['products'];
    }

    private function queryJaTemEstoqueEmpresa(Builder $query): bool
    {
        return str_contains($query->toSql(), 'estoque_empresa_atual');
    }

    private function estoquePrincipalId(): ?int
    {
        if ($this->estoquePrincipalIdCache !== null) {
            return $this->estoquePrincipalIdCache > 0 ? $this->estoquePrincipalIdCache : null;
        }

        $empresaId = (int) (ErpContext::currentEmpresa()?->id ?? 0);
        $id = $this->estoqueIdParaEmpresa($empresaId > 0 ? $empresaId : null);

        if ($id !== null) {
            $this->estoquePrincipalIdCache = $id;

            return $id;
        }

        if (! Schema::hasTable('estoques')) {
            $this->estoquePrincipalIdCache = 0;

            return null;
        }

        $id = Estoque::query()
            ->where('ativo', true)
            ->orderBy('id')
            ->value('id');

        $this->estoquePrincipalIdCache = $id !== null ? (int) $id : 0;

        return $id !== null ? (int) $id : null;
    }
}
