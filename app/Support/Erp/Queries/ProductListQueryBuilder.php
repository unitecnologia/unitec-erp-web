<?php

namespace App\Support\Erp\Queries;

use App\Models\Empresa;
use App\Models\EstoqueReserva;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Support\Erp\ErpSearchFieldSelection;
use App\Support\Erp\ProductEstoqueSaldoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductListQueryBuilder
{
    public const ESTOQUE_AGG_ALIAS = 'erp_est_agg';

    /**
     * Colunas da grade / filtros / ordenação da listagem (sem SELECT *).
     *
     * @var list<string>
     */
    public const LIST_COLUMNS = [
        'id',
        'codigo',
        'referencia',
        'codigo_barras',
        'descricao',
        'grupo',
        'preco_venda',
        'estoque',
        'estoque_minimo',
        'localizacao',
        'validade',
        'lote',
        'ativo',
    ];

    protected bool $estoqueAggJoined = false;

    protected ?int $estoqueAggEstoqueId = null;

    public function __construct(
        public string $statusFilter = 'ativos',
        public string $searchColumn = 'descricao',
        public string $localSearch = '',
        public ?Empresa $empresa = null,
        public string $orderBy = 'codigo',
        public string $estoqueFilter = 'todos',
        public string $grupoFilter = '',
        public ?string $validadeDe = null,
        public ?string $validadeAte = null,
        public bool $applyDefaultOrder = true,
        public array $searchFieldsActive = [],
        public bool $deferReservaSum = false,
    ) {}

    public static function fromRequest(Request $request, ?Empresa $empresa = null): self
    {
        $status = $request->query('status', 'ativos');
        $campo = $request->query('campo', 'descricao');
        $allowedStatus = ['ativos', 'inativos', 'todos'];
        $allowedCampo = [
            'codigo', 'referencia', 'codigo_barras', 'descricao', 'grupo',
            'preco_venda', 'estoque', 'localizacao',
        ];

        $allowedOrder = ['codigo', 'descricao', 'grupo', 'preco_venda', 'estoque', 'validade'];
        $allowedEstoque = ['todos', 'positivo', 'negativo', 'zero', 'critico'];
        $ordenar = (string) $request->query('ordenar', 'descricao');
        $searchColumn = in_array($campo, $allowedCampo, true) ? (string) $campo : 'descricao';
        $campos = $request->query('campos');
        $searchFieldsActive = is_string($campos) && $campos !== ''
            ? ErpSearchFieldSelection::normalize(array_map('trim', explode(',', $campos)), $allowedCampo, $searchColumn)
            : [];

        if ($searchFieldsActive !== []) {
            $searchColumn = $searchFieldsActive[array_key_last($searchFieldsActive)];
        }

        return new self(
            statusFilter: in_array($status, $allowedStatus, true) ? (string) $status : 'ativos',
            searchColumn: $searchColumn,
            localSearch: trim((string) $request->query('q', '')),
            searchFieldsActive: $searchFieldsActive,
            empresa: $empresa,
            orderBy: in_array($ordenar, $allowedOrder, true) ? $ordenar : 'descricao',
            estoqueFilter: in_array($request->query('estoque'), $allowedEstoque, true)
                ? (string) $request->query('estoque')
                : 'todos',
            grupoFilter: trim((string) $request->query('grupo', '')),
            validadeDe: self::parseDateQuery($request->query('validade_de')),
            validadeAte: self::parseDateQuery($request->query('validade_ate')),
        );
    }

    public function build(): Builder
    {
        return $this->buildListQuery(includeEstoqueAggForDisplay: true);
    }

    /**
     * Contagens Ativos / Inativos / Todos em uma única agregação (mesmos filtros de busca).
     *
     * @return array{ativos: int, inativos: int, todos: int}
     */
    public function countByStatus(): array
    {
        $previousStatus = $this->statusFilter;
        $this->statusFilter = 'todos';

        $query = $this->buildFilteredBaseQuery(includeEstoqueAgg: $this->needsEstoqueEmpresaAggForFilters());
        $productsTable = $query->getModel()->getTable();
        $prefix = $query->getConnection()->getTablePrefix();
        $ativoCol = $prefix.$productsTable.'.ativo';

        $this->statusFilter = $previousStatus;

        $base = $query->toBase();
        $base->orders = null;
        $base->unionOrders = null;
        $base->columns = null;
        $base->bindings['select'] = [];

        $row = $base->selectRaw(
            "COALESCE(SUM(CASE WHEN {$ativoCol} = 1 THEN 1 ELSE 0 END), 0) as ativos, "
            ."COALESCE(SUM(CASE WHEN {$ativoCol} = 0 THEN 1 ELSE 0 END), 0) as inativos, "
            .'COUNT(*) as todos'
        )->first();

        return [
            'ativos' => (int) ($row->ativos ?? 0),
            'inativos' => (int) ($row->inativos ?? 0),
            'todos' => (int) ($row->todos ?? 0),
        ];
    }

    /**
     * COUNT da paginação sem JOIN de estoque de display.
     * Só agrega saldos quando a busca/filtro realmente depende de estoque por empresa.
     */
    public function countForPagination(): int
    {
        $query = $this->buildFilteredBaseQuery(
            includeEstoqueAgg: $this->needsEstoqueEmpresaAggForFilters()
        );

        $base = $query->toBase();
        $base->orders = null;
        $base->unionOrders = null;

        return (int) $base->count();
    }

    /**
     * Query só com filtros (sem colunas da grade / sem JOIN de estoque para exibição).
     */
    protected function buildFilteredBaseQuery(bool $includeEstoqueAgg): Builder
    {
        $estoqueService = app(ProductEstoqueSaldoService::class);
        $empresaId = $this->empresa !== null ? (int) $this->empresa->id : 0;
        $usaEstoqueEmpresa = $estoqueService->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null);

        $query = Product::query();
        $productsTable = $query->getModel()->getTable();
        $this->estoqueAggJoined = false;
        $this->estoqueAggEstoqueId = null;

        $query->select("{$productsTable}.id");

        if ($includeEstoqueAgg && $usaEstoqueEmpresa) {
            $estoqueId = $estoqueService->estoqueIdParaEmpresa($empresaId);
            if ($estoqueId !== null) {
                $this->applyEstoqueEmpresaAggJoin($query, $estoqueId);
            }
        }

        return $this->applyFiltersAndOrder(
            $query,
            $estoqueService,
            $empresaId,
            $usaEstoqueEmpresa && $this->estoqueAggJoined,
        );
    }

    protected function buildListQuery(bool $includeEstoqueAggForDisplay): Builder
    {
        $estoqueService = app(ProductEstoqueSaldoService::class);
        $empresaId = $this->empresa !== null ? (int) $this->empresa->id : 0;
        $usaEstoqueEmpresa = $estoqueService->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null);
        $estoqueId = $usaEstoqueEmpresa ? $estoqueService->estoqueIdParaEmpresa($empresaId) : null;

        $query = Product::query();
        $productsTable = $query->getModel()->getTable();
        $this->estoqueAggJoined = false;
        $this->estoqueAggEstoqueId = null;

        $listSelect = $this->qualifiedListColumns($productsTable);

        // A grade pagina primeiro e soma reservas só dos IDs da página.
        // O JOIN agregado varre todas as reservas em todo COUNT/SELECT da pesquisa.
        if (! $this->deferReservaSum) {
            $prefix = $query->getConnection()->getTablePrefix();
            $reservasAlias = 'erp_reservas_sum';
            $reservasAliasSql = $prefix.$reservasAlias;

            $query->leftJoinSub($this->reservaSumQuery($estoqueId), $reservasAlias, function ($join) use ($productsTable, $reservasAlias): void {
                $join->on("{$reservasAlias}.product_id", '=', "{$productsTable}.id");
            })
                ->select($listSelect)
                ->addSelect(DB::raw(
                    "COALESCE({$reservasAliasSql}.estoque_reservado_sum, 0) as estoque_reservado_sum"
                ));
        } else {
            $query->select($listSelect);
        }

        // empresaPrecos: só no ProductListTable / ProductResource — evita eager duplo.

        if ($usaEstoqueEmpresa && $estoqueId !== null
            && ($includeEstoqueAggForDisplay || $this->needsEstoqueEmpresaAggForFilters())) {
            $this->applyEstoqueEmpresaAggJoin($query, $estoqueId);
        }

        return $this->applyFiltersAndOrder($query, $estoqueService, $empresaId, $usaEstoqueEmpresa);
    }

    /**
     * @return list<string>
     */
    protected function qualifiedListColumns(string $productsTable): array
    {
        return array_map(
            static fn (string $column): string => "{$productsTable}.{$column}",
            self::LIST_COLUMNS,
        );
    }

    /**
     * LEFT JOIN agregado 1:1 (GROUP BY product_id) — mesma semântica de
     * {@see ProductEstoqueSaldoService::sqlEstoqueEmpresaExpression()} sem subqueries correlacionadas.
     */
    protected function applyEstoqueEmpresaAggJoin(Builder $query, int $estoqueId): void
    {
        if ($this->estoqueAggJoined) {
            return;
        }

        $productsTable = $query->getModel()->getTable();
        $prefix = $query->getConnection()->getTablePrefix();
        $alias = self::ESTOQUE_AGG_ALIAS;
        $aliasSql = $prefix.$alias;
        $productsSql = $prefix.$productsTable;

        $query->leftJoinSub(
            $this->estoqueEmpresaAggQuery($estoqueId),
            $alias,
            function ($join) use ($productsTable, $alias): void {
                $join->on("{$alias}.product_id", '=', "{$productsTable}.id");
            }
        );

        $expr = $this->estoqueEmpresaAtualSqlFromAgg($productsSql, $aliasSql);
        $query->addSelect(DB::raw("{$expr} as estoque_empresa_atual"));

        $this->estoqueAggJoined = true;
        $this->estoqueAggEstoqueId = $estoqueId;
    }

    protected function estoqueEmpresaAggQuery(int $estoqueId): QueryBuilder
    {
        $saldosTable = (new ProductEstoqueSaldo)->getTable();

        return DB::table($saldosTable)
            ->selectRaw(
                'product_id, '
                .'SUM(CASE WHEN estoque_id = ? THEN quantidade ELSE NULL END) as saldo_loja, '
                .'COALESCE(SUM(quantidade), 0) as soma_depositos',
                [$estoqueId]
            )
            ->groupBy('product_id');
    }

    /**
     * Expressão equivalente à correlacionada, usando colunas do JOIN agregado.
     */
    protected function estoqueEmpresaAtualSqlFromAgg(string $productsSql, string $aggAliasSql): string
    {
        $saldoLoja = "{$aggAliasSql}.saldo_loja";
        $somaDepositos = "COALESCE({$aggAliasSql}.soma_depositos, 0)";
        $naoDistribuido = "(CASE WHEN {$productsSql}.estoque > {$somaDepositos} THEN {$productsSql}.estoque - {$somaDepositos} ELSE 0 END)";

        return "(CASE
  WHEN {$saldoLoja} IS NOT NULL THEN {$saldoLoja} + {$naoDistribuido}
  WHEN {$somaDepositos} > 0 THEN 0
  ELSE {$productsSql}.estoque
END)";
    }

    /**
     * SQL de estoque da empresa para WHERE/ORDER — prioriza JOIN; fallback correlacionado.
     */
    protected function estoqueEmpresaExpressionForFilters(
        ProductEstoqueSaldoService $estoqueService,
        int $empresaId,
    ): string {
        if ($this->estoqueAggJoined) {
            $prefix = Product::query()->getConnection()->getTablePrefix();
            $productsSql = $prefix.(new Product)->getTable();
            $aggAliasSql = $prefix.self::ESTOQUE_AGG_ALIAS;

            return $this->estoqueEmpresaAtualSqlFromAgg($productsSql, $aggAliasSql);
        }

        return $estoqueService->sqlEstoqueEmpresaExpression(
            $estoqueService->estoqueIdParaEmpresa($empresaId)
        );
    }

    protected function needsEstoqueEmpresaAggForFilters(): bool
    {
        if ($this->estoqueFilter !== 'todos') {
            return true;
        }

        if (! filled($this->localSearch)) {
            return false;
        }

        return in_array('estoque', $this->activeSearchColumns(), true);
    }

    /**
     * Soma de reservas ativas só dos produtos já paginados (não de todo o catálogo).
     *
     * @param  iterable<int, Product>  $products
     */
    public function hydrateReservaSums(iterable $products): void
    {
        $ids = [];

        foreach ($products as $product) {
            $id = (int) $product->getKey();

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return;
        }

        $empresaId = $this->empresa !== null ? (int) $this->empresa->id : 0;
        $estoqueService = app(ProductEstoqueSaldoService::class);
        $estoqueId = $estoqueService->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null)
            ? $estoqueService->estoqueIdParaEmpresa($empresaId)
            : null;

        $sums = $this->reservaSumQuery($estoqueId)
            ->whereIn('product_id', $ids)
            ->pluck('estoque_reservado_sum', 'product_id');

        foreach ($products as $product) {
            $id = (int) $product->getKey();
            $product->setAttribute('estoque_reservado_sum', (float) ($sums[$id] ?? $sums[(string) $id] ?? 0));
        }
    }

    protected function reservaSumQuery(?int $estoqueId): Builder
    {
        return EstoqueReserva::query()
            ->selectRaw('product_id, COALESCE(SUM(quantidade), 0) as estoque_reservado_sum')
            ->where('status', EstoqueReserva::STATUS_ATIVA)
            ->when($estoqueId !== null, fn (Builder $q): Builder => $q->where('estoque_id', $estoqueId))
            ->groupBy('product_id');
    }

    /**
     * Query leve para impressão/PDF/CSV — sem eager loads pesados.
     */
    public function buildForReport(): Builder
    {
        $estoqueService = app(ProductEstoqueSaldoService::class);
        $empresaId = $this->empresa !== null ? (int) $this->empresa->id : 0;
        $usaEstoqueEmpresa = $estoqueService->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null);
        $productsTable = (new Product)->getTable();
        $this->estoqueAggJoined = false;
        $this->estoqueAggEstoqueId = null;

        $query = Product::query()->select([
            "{$productsTable}.id",
            "{$productsTable}.codigo",
            "{$productsTable}.codigo_barras",
            "{$productsTable}.referencia",
            "{$productsTable}.descricao",
            "{$productsTable}.grupo",
            "{$productsTable}.unidade",
            "{$productsTable}.preco_venda",
            "{$productsTable}.preco_compra",
            "{$productsTable}.estoque",
            "{$productsTable}.estoque_minimo",
            "{$productsTable}.validade",
            "{$productsTable}.ativo",
        ]);

        if ($usaEstoqueEmpresa) {
            $estoqueId = $estoqueService->estoqueIdParaEmpresa($empresaId);
            if ($estoqueId !== null) {
                $this->applyEstoqueEmpresaAggJoin($query, $estoqueId);
            }
        }

        return $this->applyFiltersAndOrder($query, $estoqueService, $empresaId, $usaEstoqueEmpresa);
    }

    protected function applyFiltersAndOrder(
        Builder $query,
        ProductEstoqueSaldoService $estoqueService,
        int $empresaId,
        bool $usaEstoqueEmpresa,
    ): Builder {
        $productsTable = $query->getModel()->getTable();

        match ($this->statusFilter) {
            'ativos' => $query->where("{$productsTable}.ativo", true),
            'inativos' => $query->where("{$productsTable}.ativo", false),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applySearch($query, $estoqueService, $empresaId);
        }

        if (filled($this->grupoFilter)) {
            $this->applyGrupoFilter($query);
        }

        $this->applyValidadePeriodo($query);

        $this->applyEstoqueFilter($query, $estoqueService, $empresaId);

        $allowedOrder = ['codigo', 'descricao', 'grupo', 'preco_venda', 'estoque', 'validade'];
        $orderBy = in_array($this->orderBy, $allowedOrder, true) ? $this->orderBy : 'codigo';

        if (! $this->applyDefaultOrder) {
            return $query;
        }

        if ($orderBy === 'codigo') {
            return self::orderByCodigoNumerico($query);
        }

        if ($orderBy === 'validade') {
            // Sem validade no final; mais próximas do vencimento primeiro.
            return $query->orderByRaw('validade IS NULL ASC, validade ASC');
        }

        if ($orderBy === 'estoque' && $usaEstoqueEmpresa) {
            return $query->orderBy('estoque_empresa_atual');
        }

        return $query->orderBy($orderBy);
    }

    public static function orderByCodigoNumerico(Builder $query, string $direction = 'asc'): Builder
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $bare = $query->getModel()->getTable();
        $qualified = $query->getConnection()->getTablePrefix().$bare;

        return $query
            ->orderByRaw("CAST({$qualified}.codigo AS UNSIGNED) {$dir}")
            ->orderBy("{$bare}.codigo", $dir);
    }

    protected function applyEstoqueFilter(Builder $query, ProductEstoqueSaldoService $estoqueService, int $empresaId): void
    {
        $usaEstoqueEmpresa = $estoqueService->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null);

        $productsTable = $query->getModel()->getTable();

        if ($usaEstoqueEmpresa) {
            $expr = $this->estoqueEmpresaExpressionForFilters($estoqueService, $empresaId);
            $productsTableSql = $estoqueService->tabelaProductsSql();

            match ($this->estoqueFilter) {
                'positivo' => $query->whereRaw("{$expr} > 0"),
                'negativo' => $query->whereRaw("{$expr} < 0"),
                'zero' => $query->whereRaw("{$expr} = 0"),
                'critico' => $query
                    ->where("{$productsTable}.ativo", true)
                    ->where("{$productsTable}.estoque_minimo", '>', 0)
                    ->whereRaw("{$expr} < {$productsTableSql}.estoque_minimo"),
                default => null,
            };

            return;
        }

        match ($this->estoqueFilter) {
            'positivo' => $query->where("{$productsTable}.estoque", '>', 0),
            'negativo' => $query->where("{$productsTable}.estoque", '<', 0),
            'zero' => $query->where("{$productsTable}.estoque", '=', 0),
            'critico' => $query->estoqueCritico(),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    protected function activeSearchColumns(): array
    {
        return ErpSearchFieldSelection::normalize(
            $this->searchFieldsActive,
            ['codigo', 'referencia', 'codigo_barras', 'descricao', 'grupo', 'preco_venda', 'estoque', 'localizacao'],
            $this->searchColumn,
        );
    }

    protected function applySearch(Builder $query, ProductEstoqueSaldoService $estoqueService, int $empresaId): void
    {
        $term = trim($this->localSearch);

        ErpSearchFieldSelection::applyOr(
            $query,
            $this->activeSearchColumns(),
            function (Builder $inner, string $column) use ($term, $estoqueService, $empresaId): void {
                $this->applySearchOnColumn($inner, $column, $term, $estoqueService, $empresaId);
            },
        );
    }

    protected function applySearchOnColumn(
        Builder $query,
        string $column,
        string $term,
        ProductEstoqueSaldoService $estoqueService,
        int $empresaId,
    ): void {
        $parte = $this->pesquisaPorParte() ? '%' : '';

        if ($column === 'estoque' && $estoqueService->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null)) {
            $expr = $this->estoqueEmpresaExpressionForFilters($estoqueService, $empresaId);
            $query->whereRaw("{$expr} >= ?", [$this->parseDecimal($term)]);

            return;
        }

        $productsTable = $query->getModel()->getTable();

        match ($column) {
            'codigo' => $query->where("{$productsTable}.codigo", $term),
            'referencia' => $query->where("{$productsTable}.referencia", 'like', $parte . $term . '%'),
            'codigo_barras' => $query->where("{$productsTable}.codigo_barras", 'like', $term . '%'),
            'descricao' => self::whereDescricaoPartes($query, "{$productsTable}.descricao", $term),
            'grupo' => $this->applyGrupoSearch($query, $term),
            'preco_venda' => $query->where("{$productsTable}.preco_venda", '>=', $this->parseDecimal($term)),
            'estoque' => $query->where("{$productsTable}.estoque", '>=', $this->parseDecimal($term)),
            'localizacao' => $query->where("{$productsTable}.localizacao", 'like', $parte . $term . '%'),
            default => $query->where("{$productsTable}.descricao", 'like', $parte . $term . '%'),
        };
    }

    protected function applyGrupoFilter(Builder $query): void
    {
        $term = mb_strtoupper(trim($this->grupoFilter), 'UTF-8');

        if ($term === '') {
            return;
        }

        $productsTable = $query->getModel()->getTable();
        $query->where("{$productsTable}.grupo", $term);
    }

    protected function applyValidadePeriodo(Builder $query): void
    {
        $de = $this->validadeDe;
        $ate = $this->validadeAte;

        if ($de === null && $ate === null) {
            return;
        }

        if ($de !== null && $ate !== null && $de > $ate) {
            [$de, $ate] = [$ate, $de];
        }

        $productsTable = $query->getModel()->getTable();

        if ($de !== null) {
            $query->whereDate("{$productsTable}.validade", '>=', $de);
        }

        if ($ate !== null) {
            $query->whereDate("{$productsTable}.validade", '<=', $ate);
        }
    }

    /**
     * Descrição: cada palavra em qualquer parte do texto, sem diferenciar maiúsculas.
     * Mesmo critério de Nome/Razão em Pessoas. Acentos seguem o collation da coluna.
     * Palavras mais longas primeiro (AND) — mesmos resultados, permite falhar cedo no motor.
     */
    public static function whereDescricaoPartes(Builder $query, string $column, string $searchTerm): void
    {
        $searchTerm = mb_strtoupper(trim($searchTerm), 'UTF-8');
        $terms = preg_split('/\s+/u', $searchTerm, -1, PREG_SPLIT_NO_EMPTY);

        if ($terms === false || $terms === []) {
            return;
        }

        usort(
            $terms,
            static fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8')
        );

        foreach ($terms as $word) {
            $query->where($column, 'like', '%'.$word.'%');
        }
    }

    protected function applyGrupoSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $pattern = $this->pesquisaPorParte()
            ? '%' . $term . '%'
            : $term . '%';

        $productsTable = $query->getModel()->getTable();
        $query->where("{$productsTable}.grupo", 'like', $pattern);
    }

    protected function pesquisaPorParte(): bool
    {
        return (bool) ($this->empresa?->param_pdv_pesquisa_partes_descricao ?? false);
    }

    protected function parseDecimal(string $value): float
    {
        $normalized = str_replace(',', '.', trim($value));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    public static function parseDateQuery(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return $trimmed;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trimmed)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $trimmed)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    public function validadePeriodoLabel(): ?string
    {
        if ($this->validadeDe === null && $this->validadeAte === null) {
            return null;
        }

        $de = $this->validadeDe
            ? Carbon::createFromFormat('Y-m-d', $this->validadeDe)->format('d/m/Y')
            : '…';
        $ate = $this->validadeAte
            ? Carbon::createFromFormat('Y-m-d', $this->validadeAte)->format('d/m/Y')
            : '…';

        return $de.' a '.$ate;
    }

    /**
     * @return array<string, string|null>
     */
    public function reportFilters(): array
    {
        return [
            'status' => $this->statusFilter !== 'ativos' ? $this->statusFilter : null,
            'campo' => $this->searchColumn !== 'descricao' ? $this->searchColumn : null,
            'campos' => count($this->activeSearchColumns()) > 1 ? implode(',', $this->activeSearchColumns()) : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'ordenar' => $this->orderBy !== 'descricao' ? $this->orderBy : null,
            'estoque' => $this->estoqueFilter !== 'todos' ? $this->estoqueFilter : null,
            'grupo' => filled($this->grupoFilter) ? $this->grupoFilter : null,
            'validade_de' => $this->validadeDe,
            'validade_ate' => $this->validadeAte,
        ];
    }
}
