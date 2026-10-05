<?php

namespace App\Support\Erp;

use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo físico por depósito (product_estoque_saldos) com espelho em products.estoque.
 *
 * Quando o vendedor tem estoque_id (ex.: 2 — ALENCAR), a FV reserva/baixa nesse depósito.
 * Estoque ainda não lançado em depósitos fica no depósito principal da empresa (legado).
 *
 * Extrato: cada incrementar/decrementar grava estoque_movimentacoes na mesma transaction.
 * Pendência (fora do hub): {@see EstoqueMovimentacaoBypassesPendencia}.
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
        if ($estoqueId === null) {
            return (float) (Product::query()->whereKey($productId)->value('estoque') ?? 0);
        }

        return $this->fisicoPorEstoques($productId, [$estoqueId])[$estoqueId] ?? 0.0;
    }

    /**
     * Saldo físico de vários depósitos com uma leitura do produto e uma dos saldos.
     *
     * @param  list<int>  $estoqueIds
     * @return array<int, float>
     */
    public function fisicoPorEstoques(int $productId, array $estoqueIds): array
    {
        $estoqueIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $estoqueIds)));
        $global = (float) (Product::query()->whereKey($productId)->value('estoque') ?? 0);

        if ($estoqueIds === []) {
            return [];
        }

        if (! $this->tabelaDisponivel()) {
            return array_fill_keys($estoqueIds, $global);
        }

        /** @var array<int, float> $saldos */
        $saldos = ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->pluck('quantidade', 'estoque_id')
            ->map(fn ($q): float => (float) $q)
            ->all();

        $principalId = $this->estoquePrincipalId();
        $sumDepots = array_sum($saldos);
        $naoDistribuido = max(0.0, round($global - $sumDepots, 3));
        $out = [];

        foreach ($estoqueIds as $estoqueId) {
            $out[$estoqueId] = $this->fisicoDeposito($estoqueId, $global, $saldos, $principalId, $sumDepots, $naoDistribuido);
        }

        return $out;
    }

    /**
     * @param  array<int, float>  $saldos
     */
    private function fisicoDeposito(
        int $estoqueId,
        float $global,
        array $saldos,
        ?int $principalId,
        float $sumDepots,
        float $naoDistribuido,
    ): float {
        $saldoDeposito = $saldos[$estoqueId] ?? null;

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
    public function decrementar(
        int $productId,
        float $quantidade,
        ?int $estoqueId = null,
        ?Empresa $empresa = null,
        ?EstoqueMovimentacaoContext $movimentacao = null,
    ): void {
        if ($quantidade == 0.0) {
            return;
        }

        $this->ajustar($productId, -$quantidade, $estoqueId, $empresa, $movimentacao);
    }

    /**
     * Incrementa o depósito (se informado) e o estoque global do produto.
     */
    public function incrementar(
        int $productId,
        float $quantidade,
        ?int $estoqueId = null,
        ?Empresa $empresa = null,
        ?EstoqueMovimentacaoContext $movimentacao = null,
    ): void {
        if ($quantidade == 0.0) {
            return;
        }

        $this->ajustar($productId, $quantidade, $estoqueId, $empresa, $movimentacao);
    }

    private function ajustar(
        int $productId,
        float $delta,
        ?int $estoqueId,
        ?Empresa $empresa = null,
        ?EstoqueMovimentacaoContext $movimentacao = null,
    ): void {
        DB::transaction(function () use ($productId, $delta, $estoqueId, $empresa, $movimentacao): void {
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

            // Saldo global do produto (extrato) — captura antes de alterar.
            $saldoAnterior = $this->estoqueDecimal3Attribute($product);

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

            $saldoAtual = $this->estoqueDecimal3Attribute($product);
            $this->registrarMovimentacao(
                $productId,
                $estoqueId,
                $delta,
                $saldoAnterior,
                $saldoAtual,
                $empresa,
                $movimentacao,
            );
        });
    }

    /**
     * Normaliza o atributo estoque do produto para decimal(12,3) sem float intermediário no log.
     */
    private function estoqueDecimal3Attribute(Product $product): string
    {
        $raw = $product->getAttributes()['estoque'] ?? '0';

        return $this->normalizeDecimal3($raw);
    }

    private function normalizeDecimal3(mixed $value): string
    {
        $raw = trim((string) ($value ?? '0'));
        if ($raw === '' || ! is_numeric($raw)) {
            $raw = '0';
        }

        if (function_exists('bcadd')) {
            return bcadd($raw, '0', 3);
        }

        // Fallback raro: mesma precisão do hub (3 casas).
        return sprintf('%.3f', round((float) $raw, 3));
    }

    private function registrarMovimentacao(
        int $productId,
        ?int $estoqueId,
        float $delta,
        string $saldoAnterior,
        string $saldoAtual,
        ?Empresa $empresa,
        ?EstoqueMovimentacaoContext $movimentacao,
    ): void {
        if (! Schema::hasTable('estoque_movimentacoes')) {
            return;
        }

        $quantidade = $this->normalizeDecimal3(sprintf('%.3f', round($delta, 3)));
        $ctx = $movimentacao ?? new EstoqueMovimentacaoContext();
        $empresaId = $this->resolveEmpresaIdParaMovimentacao($ctx, $empresa, $estoqueId);

        $usuarioId = $ctx->usuarioId;
        if ($usuarioId === null || $usuarioId <= 0) {
            $usuarioId = Auth::id() !== null ? (int) Auth::id() : null;
        }

        $tipo = $ctx->tipo !== '' ? $ctx->tipo : EstoqueMovimentacao::TIPO_SISTEMA;

        $payload = [
            'empresa_id' => $empresaId,
            'produto_id' => $productId,
            'estoque_id' => $estoqueId,
            'data_movimentacao' => now(),
            'tipo' => $tipo,
            'quantidade' => $quantidade,
            'saldo_anterior' => $saldoAnterior,
            'saldo_atual' => $saldoAtual,
            'origem_tipo' => $ctx->origemTipo,
            'origem_id' => $ctx->origemId,
            'origem_numero' => $ctx->origemNumero,
            'usuario_id' => $usuarioId,
            'observacao' => $ctx->observacao,
        ];

        if (Schema::hasColumn('estoque_movimentacoes', 'doc_fiscal_tipo')) {
            $payload['doc_fiscal_tipo'] = $ctx->docFiscalTipo;
            $payload['doc_fiscal_numero'] = $ctx->docFiscalNumero;
        }

        EstoqueMovimentacao::query()->create($payload);
    }

    /**
     * Resolve a empresa da movimentação (metadado do log). Não altera cálculo de saldo.
     *
     * Ordem: contexto → objeto Empresa → depósito → empresa ativa no ERP.
     */
    private function resolveEmpresaIdParaMovimentacao(
        EstoqueMovimentacaoContext $ctx,
        ?Empresa $empresa,
        ?int $estoqueId,
    ): ?int {
        if ($ctx->empresaId !== null && $ctx->empresaId > 0) {
            return (int) $ctx->empresaId;
        }

        if ($empresa?->id !== null && (int) $empresa->id > 0) {
            return (int) $empresa->id;
        }

        if ($estoqueId !== null && $estoqueId > 0 && Schema::hasTable('estoques')) {
            $fromEstoque = Estoque::query()->whereKey($estoqueId)->value('empresa_id');
            if ($fromEstoque !== null && (int) $fromEstoque > 0) {
                return (int) $fromEstoque;
            }
        }

        $fromContext = (int) (ErpContext::currentEmpresaId() ?? session('erp_empresa_id') ?? 0);

        return $fromContext > 0 ? $fromContext : null;
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
