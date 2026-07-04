<?php

namespace App\Support\Erp\Queries;

use App\Models\Empresa;
use App\Models\EstoqueReserva;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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

        $allowedOrder = ['codigo', 'descricao', 'grupo', 'preco_venda', 'estoque'];
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
        );
    }

    public function build(): Builder
    {
        $query = Product::query()
            ->with('ultFornecedor')
            ->withSum(
                ['estoqueReservas as estoque_reservado_sum' => fn (Builder $reservaQuery): Builder => $reservaQuery
                    ->where('status', EstoqueReserva::STATUS_ATIVA)],
                'quantidade',
            );

        match ($this->statusFilter) {
            'ativos' => $query->where('ativo', true),
            'inativos' => $query->where('ativo', false),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applySearch($query);
        }

        if (filled($this->grupoFilter)) {
            $this->applyGrupoFilter($query);
        }

        match ($this->estoqueFilter) {
            'positivo' => $query->where('estoque', '>', 0),
            'negativo' => $query->where('estoque', '<', 0),
            'zero' => $query->where('estoque', '=', 0),
            'critico' => $query->estoqueCritico(),
            default => null,
        };

        $allowedOrder = ['codigo', 'descricao', 'grupo', 'preco_venda', 'estoque'];
        $orderBy = in_array($this->orderBy, $allowedOrder, true) ? $this->orderBy : 'codigo';

        return $query->orderBy($orderBy);
    }

    protected function applySearch(Builder $query): void
    {
        $term = trim($this->localSearch);
        $parte = $this->pesquisaPorParte() ? '%' : '';

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
        ];
    }
}
