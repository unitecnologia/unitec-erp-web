<?php

namespace App\Support\Erp\Queries;

use App\Models\Compra;
use App\Models\DevolucaoCompra;
use App\Models\DevolucaoCompraItem;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpSearchFieldSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CompraListQueryBuilder
{
    /**
     * @param  list<string>  $searchFieldsActive
     * @param  array<string, string>  $localSearchByField
     */
    public function __construct(
        public string $statusFilter = 'todas',
        public string $searchColumn = 'fornecedor',
        public string $localSearch = '',
        public string $localSearchDe = '',
        public string $localSearchAte = '',
        public array $searchFieldsActive = [],
        public array $localSearchByField = [],
        public string $orderBy = 'numero',
        public string $orderDirection = 'desc',
        public bool $applyDefaultOrder = true,
    ) {}

    public function build(): Builder
    {
        $query = $this->buildFilteredQuery();
        $query->with(['fornecedor']);

        return $this->applyDefaultOrder($query);
    }

    public function buildForList(): Builder
    {
        $table = (new Compra)->getTable();

        $query = $this->buildFilteredQuery()->select([
            "{$table}.id",
            "{$table}.numero",
            "{$table}.data_emissao",
            "{$table}.data_entrada",
            "{$table}.numero_nota",
            "{$table}.fornecedor_id",
            "{$table}.chave_nfe",
            "{$table}.total",
            "{$table}.status",
        ]);

        $query->with(['fornecedor:id,nome_razao']);

        if (! $this->applyDefaultOrder) {
            return $query;
        }

        return $this->applyDefaultOrder($query);
    }

    public function sumFilteredTotal(): float
    {
        $table = (new Compra)->getTable();

        return (float) $this->buildFilteredQuery()->sum("{$table}.total");
    }

    /**
     * Soma do valor devolvido (itens de devoluções finalizadas) das compras filtradas.
     */
    public function sumFilteredValorDevolvido(): float
    {
        $compraTable = (new Compra)->getTable();
        $prefix = DB::getTablePrefix();
        $item = $prefix.(new DevolucaoCompraItem)->getTable();

        $compraIds = $this->buildFilteredQuery()->select("{$compraTable}.id");

        return (float) DevolucaoCompraItem::query()
            ->whereHas('devolucao', function (Builder $q) use ($compraIds): void {
                $q->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA)
                    ->whereIn('compra_id', $compraIds);
            })
            ->sum(DB::raw("COALESCE({$item}.total, ROUND({$item}.qtd * {$item}.preco, 2), 0)"));
    }

    protected function buildFilteredQuery(): Builder
    {
        $query = Compra::query();
        $table = (new Compra)->getTable();

        $empresaId = ErpContext::currentEmpresaId();

        if ($empresaId !== null) {
            $query->where(function (Builder $empresaQuery) use ($empresaId, $table): void {
                $empresaQuery
                    ->where("{$table}.empresa_id", $empresaId)
                    ->orWhereNull("{$table}.empresa_id");
            });
        }

        if ($this->statusFilter !== 'todas') {
            $query->where("{$table}.status", $this->statusFilter);
        }

        $this->applySearchFilters($query);

        return $query;
    }

    protected function applySearchFilters(Builder $query): void
    {
        foreach ($this->normalizedSearchFieldsActive() as $column) {
            if ($this->isDateColumn($column)) {
                $this->applyLocalSearchDateRange($query, $column);

                continue;
            }

            $term = trim((string) ($this->localSearchByField[$column] ?? ''));

            if ($term === '' && filled($this->localSearch) && $column === $this->searchColumn) {
                $term = trim($this->localSearch);
            }

            if ($term === '') {
                continue;
            }

            $this->applyLocalSearchForColumn($query, $term, $column);
        }
    }

    protected function applyDefaultOrder(Builder $query): Builder
    {
        $table = (new Compra)->getTable();
        $allowedOrder = ['numero', 'data_emissao', 'data_entrada'];
        $orderBy = in_array($this->orderBy, $allowedOrder, true) ? $this->orderBy : 'numero';
        $direction = $this->orderDirection === 'asc' ? 'asc' : 'desc';

        return $query->orderBy("{$table}.{$orderBy}", $direction);
    }

    /**
     * @return list<string>
     */
    public function normalizedSearchFieldsActive(): array
    {
        return ErpSearchFieldSelection::normalize(
            $this->searchFieldsActive !== [] ? $this->searchFieldsActive : [$this->searchColumn],
            $this->localSearchColumns(),
            'fornecedor',
        );
    }

    public function hasDateSearchField(): bool
    {
        foreach ($this->normalizedSearchFieldsActive() as $column) {
            if ($this->isDateColumn($column)) {
                return true;
            }
        }

        return false;
    }

    public function isDateSearchColumn(): bool
    {
        return $this->hasDateSearchField();
    }

    public function isDateColumn(string $column): bool
    {
        return in_array($column, ['data_emissao', 'data_entrada'], true);
    }

    protected function applyLocalSearchDateRange(Builder $query, string $column): void
    {
        if (! filled($this->localSearchDe) && ! filled($this->localSearchAte)) {
            return;
        }

        $column = $column === 'data_entrada' ? 'data_entrada' : 'data_emissao';
        $table = (new Compra)->getTable();

        if (filled($this->localSearchDe)) {
            $query->whereDate("{$table}.{$column}", '>=', $this->localSearchDe);
        }

        if (filled($this->localSearchAte)) {
            $query->whereDate("{$table}.{$column}", '<=', $this->localSearchAte);
        }
    }

    /**
     * @return list<string>
     */
    protected function localSearchColumns(): array
    {
        return ['numero', 'data_emissao', 'data_entrada', 'numero_nota', 'fornecedor', 'chave', 'total'];
    }

    protected function applyLocalSearchForColumn(Builder $query, string $term, string $column): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $prefixLike = $term.'%';
        $table = (new Compra)->getTable();

        match ($column) {
            'numero' => $query->where("{$table}.numero", 'like', $prefixLike),
            'numero_nota' => $query->where("{$table}.numero_nota", 'like', $prefixLike),
            'fornecedor' => $query->whereHas(
                'fornecedor',
                fn (Builder $fornecedorQuery): Builder => $fornecedorQuery->where('nome_razao', 'like', $prefixLike),
            ),
            'chave' => $query->where("{$table}.chave_nfe", 'like', $prefixLike),
            'total' => $this->applyLocalSearchByTotal($query, $term),
            default => null,
        };
    }

    protected function applyLocalSearchByTotal(Builder $query, string $term): void
    {
        $table = (new Compra)->getTable();
        $normalized = str_replace(['R$', ' '], '', $term);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            if ($this->databaseDriver($query) === 'sqlite') {
                $query->whereRaw("CAST({$table}.total AS TEXT) LIKE ?", ['%'.$normalized.'%']);

                return;
            }

            $query->where("{$table}.total", 'like', '%'.$normalized.'%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', {$table}.total), '.', ',') LIKE ?", ['%'.$term.'%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT({$table}.total, 2), '.', ',') LIKE ?", ['%'.$term.'%']);
    }

    protected function databaseDriver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
    }
}
