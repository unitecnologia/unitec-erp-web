<?php

namespace App\Support\Erp\Queries;

use App\Models\Empresa;
use App\Models\EstoqueReserva;
use App\Models\Product;
use App\Support\Erp\ProductEstoqueSaldoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProductListQueryBuilder
{
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

        return new self(
            statusFilter: in_array($status, $allowedStatus, true) ? (string) $status : 'ativos',
            searchColumn: in_array($campo, $allowedCampo, true) ? (string) $campo : 'descricao',
            localSearch: trim((string) $request->query('q', '')),
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
        $estoqueService = app(ProductEstoqueSaldoService::class);
        $empresaId = $this->empresa !== null ? (int) $this->empresa->id : 0;
        $usaEstoqueEmpresa = $estoqueService->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null);
        $estoqueId = $usaEstoqueEmpresa ? $estoqueService->estoqueIdParaEmpresa($empresaId) : null;

        $query = Product::query();
        $productsTable = $query->getModel()->getTable();
        $prefix = $query->getConnection()->getTablePrefix();
        $reservasAlias = 'erp_reservas_sum';
        $reservasAliasSql = $prefix.$reservasAlias;

        // Reservas: 1 JOIN agregado (Laravel prefixa o alias do leftJoinSub).
        $reservas = EstoqueReserva::query()
            ->selectRaw('product_id, COALESCE(SUM(quantidade), 0) as estoque_reservado_sum')
            ->where('status', EstoqueReserva::STATUS_ATIVA)
            ->when($estoqueId !== null, fn (Builder $q): Builder => $q->where('estoque_id', $estoqueId))
            ->groupBy('product_id');

        $query->leftJoinSub($reservas, $reservasAlias, function ($join) use ($productsTable, $reservasAlias): void {
            // Alias sem prefixo: o query builder prefixa sozinho no JOIN/ON.
            $join->on("{$reservasAlias}.product_id", '=', "{$productsTable}.id");
        })
            ->addSelect("{$productsTable}.*")
            ->addSelect(\Illuminate\Support\Facades\DB::raw(
                // DB::raw NÃO recebe prefixo automático — usar alias já prefixado.
                "COALESCE({$reservasAliasSql}.estoque_reservado_sum, 0) as estoque_reservado_sum"
            ));

        // empresaPrecos: só no ProductResource::table modifyQueryUsing — evita eager duplo.

        if ($usaEstoqueEmpresa) {
            $estoqueService->applyEstoqueEmpresaSelect($query, $empresaId);
        }

        return $this->applyFiltersAndOrder($query, $estoqueService, $empresaId, $usaEstoqueEmpresa);
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
            $estoqueService->applyEstoqueEmpresaSelect($query, $empresaId);
        }

        return $this->applyFiltersAndOrder($query, $estoqueService, $empresaId, $usaEstoqueEmpresa);
    }

    protected function applyFiltersAndOrder(
        Builder $query,
        ProductEstoqueSaldoService $estoqueService,
        int $empresaId,
        bool $usaEstoqueEmpresa,
    ): Builder {
        match ($this->statusFilter) {
            'ativos' => $query->where('ativo', true),
            'inativos' => $query->where('ativo', false),
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
            return $query->orderBy('codigo');
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

    protected function applyEstoqueFilter(Builder $query, ProductEstoqueSaldoService $estoqueService, int $empresaId): void
    {
        $usaEstoqueEmpresa = $estoqueService->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null);

        if ($usaEstoqueEmpresa) {
            $estoqueId = $estoqueService->estoqueIdParaEmpresa($empresaId);
            $expr = $estoqueService->sqlEstoqueEmpresaExpression($estoqueId);
            $productsTable = $estoqueService->tabelaProductsSql();

            match ($this->estoqueFilter) {
                'positivo' => $query->whereRaw("{$expr} > 0"),
                'negativo' => $query->whereRaw("{$expr} < 0"),
                'zero' => $query->whereRaw("{$expr} = 0"),
                'critico' => $query
                    ->where('ativo', true)
                    ->where('estoque_minimo', '>', 0)
                    ->whereRaw("{$expr} < {$productsTable}.estoque_minimo"),
                default => null,
            };

            return;
        }

        match ($this->estoqueFilter) {
            'positivo' => $query->where('estoque', '>', 0),
            'negativo' => $query->where('estoque', '<', 0),
            'zero' => $query->where('estoque', '=', 0),
            'critico' => $query->estoqueCritico(),
            default => null,
        };
    }

    protected function applySearch(Builder $query, ProductEstoqueSaldoService $estoqueService, int $empresaId): void
    {
        $term = trim($this->localSearch);
        $parte = $this->pesquisaPorParte() ? '%' : '';

        if ($this->searchColumn === 'estoque' && $estoqueService->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null)) {
            $expr = $estoqueService->sqlEstoqueEmpresaExpression($estoqueService->estoqueIdParaEmpresa($empresaId));
            $query->whereRaw("{$expr} >= ?", [$this->parseDecimal($term)]);

            return;
        }

        match ($this->searchColumn) {
            'codigo' => $query->where('codigo', $term),
            'referencia' => $query->where('referencia', 'like', $parte . $term . '%'),
            'codigo_barras' => $query->where('codigo_barras', 'like', $term . '%'),
            'descricao' => $query->where('descricao', 'like', $parte . $term . '%'),
            'grupo' => $this->applyGrupoSearch($query, $term),
            'preco_venda' => $query->where('preco_venda', '>=', $this->parseDecimal($term)),
            'estoque' => $query->where('estoque', '>=', $this->parseDecimal($term)),
            'localizacao' => $query->where('localizacao', 'like', $parte . $term . '%'),
            default => $query->where('descricao', 'like', $parte . $term . '%'),
        };
    }

    protected function applyGrupoFilter(Builder $query): void
    {
        $term = mb_strtoupper(trim($this->grupoFilter), 'UTF-8');

        if ($term === '') {
            return;
        }

        $query->where('grupo', $term);
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

        if ($de !== null) {
            $query->whereDate('validade', '>=', $de);
        }

        if ($ate !== null) {
            $query->whereDate('validade', '<=', $ate);
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

        $query->where('grupo', 'like', $pattern);
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
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'ordenar' => $this->orderBy !== 'descricao' ? $this->orderBy : null,
            'estoque' => $this->estoqueFilter !== 'todos' ? $this->estoqueFilter : null,
            'grupo' => filled($this->grupoFilter) ? $this->grupoFilter : null,
            'validade_de' => $this->validadeDe,
            'validade_ate' => $this->validadeAte,
        ];
    }
}
