<?php

namespace App\Support\Erp;

use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Zeragem em lote via {@see AjusteEstoqueService} (modo substituir → 0).
 * Não altera saldo com UPDATE direto.
 */
final class ZeraEstoqueService
{
    public const MOTIVO_PADRAO = 'Balanço de Estoque';

    public const SELECTION_LIMIT = 5000;

    public const PAGE_SIZE = 50;

    public function __construct(
        private readonly AjusteEstoqueService $ajustes = new AjusteEstoqueService(),
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
    ) {}

    public function empresaIdAtual(): int
    {
        $id = (int) (session('erp_empresa_id') ?? Auth::user()?->empresa_id ?? 0);

        if ($id <= 0) {
            throw new \DomainException('Empresa não selecionada.');
        }

        return $id;
    }

    public function resolverEstoqueId(int $empresaId, ?int $estoqueId = null): int
    {
        if ($estoqueId !== null && $estoqueId > 0) {
            $ok = Estoque::query()
                ->whereKey($estoqueId)
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->exists();

            if (! $ok) {
                throw new \DomainException('Depósito inválido para a empresa atual.');
            }

            return $estoqueId;
        }

        $principal = $this->saldos->estoqueIdParaEmpresa($empresaId);

        if ($principal === null || $principal <= 0) {
            throw new \DomainException('Nenhum depósito ativo para a empresa.');
        }

        return $principal;
    }

    /**
     * @return array<int, string> id => label
     */
    public function depositosOptions(int $empresaId): array
    {
        return Estoque::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome'])
            ->mapWithKeys(fn (Estoque $e): array => [
                (int) $e->id => trim((string) $e->codigo.' — '.$e->nome),
            ])
            ->all();
    }

    /**
     * @param  array{
     *   pesquisa?: string,
     *   grupo?: string,
     *   marca?: string,
     *   fornecedor_id?: int|string|null,
     *   saldo?: string,
     *   estoque_id?: int|null,
     *   apenas_ativos?: bool
     * }  $filtros
     */
    public function queryProdutos(int $empresaId, array $filtros): Builder
    {
        $estoqueId = $this->resolverEstoqueId($empresaId, isset($filtros['estoque_id']) ? (int) $filtros['estoque_id'] : null);
        $expr = $this->saldos->sqlEstoqueEmpresaExpression($estoqueId);

        $query = Product::query()
            ->select([
                'products.id',
                'products.codigo',
                'products.descricao',
                'products.grupo',
                'products.marca',
                'products.preco_custo',
                'products.preco_compra',
                'products.estoque',
                'products.ult_fornecedor_id',
                'products.ativo',
            ])
            ->selectRaw("{$expr} as estoque_atual");

        if (($filtros['apenas_ativos'] ?? true) !== false) {
            $query->where('products.ativo', true);
        }

        $pesquisa = trim((string) ($filtros['pesquisa'] ?? ''));
        if ($pesquisa !== '') {
            $term = mb_strtoupper($pesquisa, 'UTF-8');
            $query->where(function (Builder $q) use ($term, $pesquisa): void {
                $q->whereRaw('UPPER(products.codigo) LIKE ?', ['%'.$term.'%'])
                    ->orWhereRaw('UPPER(products.descricao) LIKE ?', ['%'.$term.'%'])
                    ->orWhere('products.codigo_barras', 'like', '%'.$pesquisa.'%')
                    ->orWhere('products.referencia', 'like', '%'.$pesquisa.'%');
            });
        }

        $grupo = trim((string) ($filtros['grupo'] ?? ''));
        if ($grupo !== '' && $grupo !== 'todos') {
            $query->where('products.grupo', $grupo);
        }

        $marca = trim((string) ($filtros['marca'] ?? ''));
        if ($marca !== '' && $marca !== 'todos') {
            $query->where('products.marca', $marca);
        }

        $fornecedor = $filtros['fornecedor_id'] ?? null;
        if ($fornecedor !== null && $fornecedor !== '' && $fornecedor !== 'todos' && ctype_digit((string) $fornecedor)) {
            $query->where('products.ult_fornecedor_id', (int) $fornecedor);
        }

        $saldo = (string) ($filtros['saldo'] ?? 'todos');
        match ($saldo) {
            'positivo' => $query->whereRaw("{$expr} > 0.0005"),
            'negativo' => $query->whereRaw("{$expr} < -0.0005"),
            // "todos" = positivos e negativos (saldo ≠ 0) — produtos zerados não entram na grade
            default => $query->whereRaw("ABS({$expr}) > 0.0005"),
        };

        return $query->orderBy('products.descricao')->orderBy('products.id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int, estoque_id: int}
     */
    public function listar(int $empresaId, array $filtros, int $page = 1, int $perPage = self::PAGE_SIZE): array
    {
        $estoqueId = $this->resolverEstoqueId($empresaId, isset($filtros['estoque_id']) ? (int) $filtros['estoque_id'] : null);
        $filtros['estoque_id'] = $estoqueId;

        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));

        $paginator = $this->queryProdutos($empresaId, $filtros)
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = [];
        foreach ($paginator->items() as $product) {
            /** @var Product $product */
            $saldo = round((float) ($product->estoque_atual ?? 0), 3);
            $custo = (float) ($product->preco_custo ?: $product->preco_compra ?: 0);

            $rows[] = [
                'id' => (int) $product->id,
                'codigo' => (string) ($product->codigo ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'grupo' => (string) ($product->grupo ?? ''),
                'marca' => (string) ($product->marca ?? ''),
                'estoque_atual' => $saldo,
                'qtd_zerar' => round(-1 * $saldo, 3),
                'valor_estimado' => round(abs($saldo) * $custo, 2),
            ];
        }

        return [
            'rows' => $rows,
            'total' => (int) $paginator->total(),
            'page' => (int) $paginator->currentPage(),
            'per_page' => (int) $paginator->perPage(),
            'last_page' => (int) $paginator->lastPage(),
            'estoque_id' => $estoqueId,
        ];
    }

    /**
     * IDs do conjunto filtrado (cap SELECTION_LIMIT).
     *
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    public function idsFiltrados(int $empresaId, array $filtros, int $limit = self::SELECTION_LIMIT): array
    {
        $estoqueId = $this->resolverEstoqueId($empresaId, isset($filtros['estoque_id']) ? (int) $filtros['estoque_id'] : null);
        $filtros['estoque_id'] = $estoqueId;

        return $this->queryProdutos($empresaId, $filtros)
            ->limit(max(1, $limit))
            ->pluck('products.id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $productIds
     * @return array{
     *   produtos: int,
     *   unidades: float,
     *   negativos: int,
     *   valor_estimado: float,
     *   estoque_id: int,
     *   deposito_label: string
     * }
     */
    public function resumo(int $empresaId, int $estoqueId, array $productIds): array
    {
        $estoqueId = $this->resolverEstoqueId($empresaId, $estoqueId);
        $ids = $this->normalizarIds($productIds);

        $deposito = Estoque::query()->whereKey($estoqueId)->first();
        $label = $deposito ? trim((string) $deposito->codigo.' — '.$deposito->nome) : (string) $estoqueId;

        if ($ids === []) {
            return [
                'produtos' => 0,
                'unidades' => 0.0,
                'negativos' => 0,
                'valor_estimado' => 0.0,
                'estoque_id' => $estoqueId,
                'deposito_label' => $label,
            ];
        }

        $produtos = Product::query()
            ->whereIn('id', $ids)
            ->get(['id', 'preco_custo', 'preco_compra', 'estoque']);

        $saldos = $this->saldos->fisicoParaProdutos(
            $ids,
            $estoqueId,
            $produtos->pluck('estoque', 'id')->map(static fn ($v): float => (float) $v)->all(),
        );

        $unidades = 0.0;
        $negativos = 0;
        $valor = 0.0;
        $afetados = 0;

        foreach ($produtos as $product) {
            $pid = (int) $product->id;
            $saldo = round((float) ($saldos[$pid] ?? 0), 3);

            if (abs($saldo) < 0.0005) {
                continue;
            }

            $afetados++;
            $unidades += abs($saldo);
            if ($saldo < 0) {
                $negativos++;
            }
            $custo = (float) ($product->preco_custo ?: $product->preco_compra ?: 0);
            $valor += abs($saldo) * $custo;
        }

        return [
            'produtos' => $afetados,
            'unidades' => round($unidades, 3),
            'negativos' => $negativos,
            'valor_estimado' => round($valor, 2),
            'estoque_id' => $estoqueId,
            'deposito_label' => $label,
        ];
    }

    /**
     * @param  list<int>  $productIds
     * @return array{
     *   ajustados: int,
     *   ignorados: int,
     *   falhas: list<array{id: int, mensagem: string}>,
     *   estoque_id: int
     * }
     */
    public function executar(
        int $empresaId,
        int $estoqueId,
        array $productIds,
        string $motivo,
        ?string $executionToken = null,
    ): array {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \DomainException('Informe o motivo da zeragem.');
        }

        $estoqueId = $this->resolverEstoqueId($empresaId, $estoqueId);
        $ids = $this->normalizarIds($productIds);

        if ($ids === []) {
            throw new \DomainException('Nenhum produto selecionado.');
        }

        if (count($ids) > self::SELECTION_LIMIT) {
            throw new \DomainException('Limite de '.self::SELECTION_LIMIT.' produtos por execução.');
        }

        $lockKey = $this->lockKey($empresaId, $estoqueId, $executionToken);
        $lock = Cache::lock($lockKey, 120);

        if (! $lock->get()) {
            throw new \DomainException('Já existe uma zeragem em andamento. Aguarde.');
        }

        try {
            if ($executionToken) {
                $doneKey = 'zera_estoque_done:'.$executionToken;
                if (Cache::has($doneKey)) {
                    throw new \DomainException('Esta confirmação já foi processada.');
                }
            }

            $ajustados = 0;
            $ignorados = 0;
            /** @var list<array{id: int, mensagem: string}> $falhas */
            $falhas = [];
            $data = now()->toDateString();

            foreach (array_chunk($ids, 40) as $chunk) {
                foreach ($chunk as $productId) {
                    try {
                        $this->ajustes->criar(
                            $productId,
                            $data,
                            0.0,
                            'substituir',
                            $estoqueId,
                            $motivo,
                        );
                        $ajustados++;
                    } catch (\DomainException $e) {
                        // Já zerado / sem delta — não é falha operacional
                        if (str_contains($e->getMessage(), 'já está com o valor')) {
                            $ignorados++;
                        } else {
                            $falhas[] = ['id' => $productId, 'mensagem' => $e->getMessage()];
                        }
                    } catch (\Throwable $e) {
                        $falhas[] = [
                            'id' => $productId,
                            'mensagem' => $e->getMessage() !== '' ? $e->getMessage() : 'Falha ao ajustar.',
                        ];
                    }
                }
            }

            if ($executionToken) {
                Cache::put('zera_estoque_done:'.$executionToken, true, now()->addMinutes(30));
            }

            return [
                'ajustados' => $ajustados,
                'ignorados' => $ignorados,
                'falhas' => $falhas,
                'estoque_id' => $estoqueId,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Atalho usado pelo modal da Empresa: zera todos os saldos negativos do depósito da empresa.
     */
    public function zerarNegativosEmpresa(?int $empresaId = null): int
    {
        $empresaId = $empresaId ?? $this->empresaIdAtual();
        $estoqueId = $this->saldos->estoqueIdParaEmpresa($empresaId);

        if ($estoqueId === null || $estoqueId <= 0) {
            return $this->zerarNegativosGlobais();
        }

        $ids = $this->idsFiltrados($empresaId, [
            'estoque_id' => $estoqueId,
            'saldo' => 'negativo',
            'apenas_ativos' => false,
        ]);

        if ($ids === []) {
            return 0;
        }

        $result = $this->executar(
            $empresaId,
            $estoqueId,
            $ids,
            self::MOTIVO_PADRAO.' — estoque negativo',
        );

        return (int) $result['ajustados'];
    }

    public function countNegativos(?int $empresaId = null): int
    {
        $empresaId = $empresaId ?? $this->empresaIdAtual();
        $estoqueId = $this->saldos->estoqueIdParaEmpresa($empresaId);

        if ($estoqueId === null || $estoqueId <= 0) {
            return (int) Product::query()->where('estoque', '<', -0.0005)->count();
        }

        return (int) $this->queryProdutos($empresaId, [
            'estoque_id' => $estoqueId,
            'saldo' => 'negativo',
            'apenas_ativos' => false,
        ])->count();
    }

    /**
     * Fallback quando a empresa ainda não tem depósito cadastrado.
     */
    private function zerarNegativosGlobais(): int
    {
        $ids = Product::query()
            ->where('estoque', '<', -0.0005)
            ->orderBy('id')
            ->limit(self::SELECTION_LIMIT)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return 0;
        }

        $ajustados = 0;
        $data = now()->toDateString();
        $motivo = self::MOTIVO_PADRAO.' — estoque negativo';

        foreach ($ids as $productId) {
            try {
                $this->ajustes->criar($productId, $data, 0.0, 'substituir', null, $motivo);
                $ajustados++;
            } catch (\DomainException $e) {
                if (! str_contains($e->getMessage(), 'já está com o valor')) {
                    // ignora e segue os demais
                }
            } catch (\Throwable) {
                // ignora e segue os demais
            }
        }

        return $ajustados;
    }

    /**
     * @param  list<int|string>  $productIds
     * @return list<int>
     */
    private function normalizarIds(array $productIds): array
    {
        $ids = [];
        foreach ($productIds as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }

        return array_values($ids);
    }

    private function lockKey(int $empresaId, int $estoqueId, ?string $token): string
    {
        $userId = Auth::id() !== null ? (int) Auth::id() : 0;

        return 'zera_estoque_lock:'.$empresaId.':'.$estoqueId.':'.$userId.':'.($token ?: 'batch');
    }
}
