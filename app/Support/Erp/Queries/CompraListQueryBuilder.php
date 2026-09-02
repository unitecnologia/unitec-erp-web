<?php

namespace App\Support\Erp\Queries;

use App\Models\Compra;
use App\Models\DevolucaoCompra;
use App\Support\Erp\ErpContext;
use Illuminate\Database\Eloquent\Builder;

class CompraListQueryBuilder
{
    public function __construct(
        public string $statusFilter = 'todas',
        public string $searchColumn = 'fornecedor',
        public string $localSearch = '',
        public string $localSearchDe = '',
        public string $localSearchAte = '',
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
        return (float) $this->buildFilteredQuery()->sum('total');
    }

    protected function buildFilteredQuery(): Builder
    {
        $query = Compra::query()->withExists([
            'devolucoes as has_devolucao_finalizada' => fn (Builder $devolucaoQuery): Builder => $devolucaoQuery
                ->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA),
        ]);

        $empresaId = ErpContext::currentEmpresaId();

        if ($empresaId !== null) {
            $query->where(function (Builder $empresaQuery) use ($empresaId): void {
                $empresaQuery
                    ->where('empresa_id', $empresaId)
                    ->orWhereNull('empresa_id');
            });
        }

        if ($this->statusFilter !== 'todas') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->isDateSearchColumn()) {
            $this->applyLocalSearchByDateRange($query);
        } elseif (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyDefaultOrder(Builder $query): Builder
    {
        $allowedOrder = ['numero', 'data_emissao', 'data_entrada'];
        $orderBy = in_array($this->orderBy, $allowedOrder, true) ? $this->orderBy : 'numero';
        $direction = $this->orderDirection === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $direction);
    }

    public function isDateSearchColumn(): bool
    {
        return in_array($this->searchColumn, ['data_emissao', 'data_entrada'], true);
    }

    protected function applyLocalSearchByDateRange(Builder $query): void
    {
        if (! filled($this->localSearchDe) && ! filled($this->localSearchAte)) {
            return;
        }

        $column = $this->searchColumn === 'data_entrada' ? 'data_entrada' : 'data_emissao';

        if (filled($this->localSearchDe)) {
            $query->whereDate($column, '>=', $this->localSearchDe);
        }

        if (filled($this->localSearchAte)) {
            $query->whereDate($column, '<=', $this->localSearchAte);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return ['numero', 'data_emissao', 'data_entrada', 'numero_nota', 'fornecedor', 'chave', 'total'];
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)
            ? $this->searchColumn
            : 'fornecedor';

        $prefixLike = $term.'%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $prefixLike),
            'numero_nota' => $query->where('numero_nota', 'like', $prefixLike),
            'fornecedor' => $query->whereHas('fornecedor', fn (Builder $fornecedorQuery): Builder => $fornecedorQuery->where('nome_razao', 'like', $prefixLike)),
            'chave' => $query->where('chave_nfe', 'like', $prefixLike),
            'total' => $this->applyLocalSearchByTotal($query, $term),
            default => null,
        };
    }

    protected function applyLocalSearchByTotal(Builder $query, string $term): void
    {
        $normalized = str_replace(['R$', ' '], '', $term);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            if ($this->databaseDriver($query) === 'sqlite') {
                $query->whereRaw('CAST(total AS TEXT) LIKE ?', ['%'.$normalized.'%']);

                return;
            }

            $query->where('total', 'like', '%'.$normalized.'%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', total), '.', ',') LIKE ?", ['%'.$term.'%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT(total, 2), '.', ',') LIKE ?", ['%'.$term.'%']);
    }

    protected function databaseDriver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
    }
}
