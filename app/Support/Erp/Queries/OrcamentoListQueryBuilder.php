<?php

namespace App\Support\Erp\Queries;

use App\Models\Orcamento;
use Illuminate\Database\Eloquent\Builder;

class OrcamentoListQueryBuilder
{
    public function __construct(
        public string $statusFilter = 'todos',
        public string $searchColumn = 'cliente',
        public string $localSearch = '',
        public string $periodoDeApplied = '',
        public string $periodoAteApplied = '',
        public string $orderBy = 'numero',
        public string $orderDirection = 'desc',
        public bool $applyDefaultOrder = true,
    ) {}

    /**
     * Query completa para relatórios / impressão.
     */
    public function build(): Builder
    {
        $query = $this->buildFilteredQuery();
        $query->with(['cliente', 'vendedor', 'forcaVendasOrder', 'vendasInternasOrder']);

        return $this->applyDefaultOrder($query);
    }

    /**
     * Query leve para a grade — colunas visíveis + relacionamentos mínimos.
     */
    public function buildForList(): Builder
    {
        $table = (new Orcamento)->getTable();

        $query = $this->buildFilteredQuery()->select([
            "{$table}.id",
            "{$table}.numero",
            "{$table}.data",
            "{$table}.hora",
            "{$table}.cliente_id",
            "{$table}.cliente_nome",
            "{$table}.cliente_cidade",
            "{$table}.cliente_uf",
            "{$table}.vendedor_id",
            "{$table}.plataforma",
            "{$table}.total",
            "{$table}.status",
            "{$table}.created_at",
        ]);

        $query->with([
            'cliente:id,nome_razao,cidade_nome,uf',
            'vendedor:id,nome',
            'forcaVendasOrder:id,orcamento_id',
            'vendasInternasOrder:id,orcamento_id',
        ]);

        if (! $this->applyDefaultOrder) {
            return $query;
        }

        return $this->applyDefaultOrder($query);
    }

    public function sumFilteredTotal(): float
    {
        return (float) $this->buildFilteredQuery()->sum('total');
    }

    public function countFiltered(): int
    {
        return (int) $this->buildFilteredQuery()->count();
    }

    protected function buildFilteredQuery(): Builder
    {
        $query = Orcamento::query()->visivelNaListaOrcamentos();

        if ($this->statusFilter !== 'todos') {
            $query->where('status', $this->statusFilter);
        }

        if ($de = $this->normalizePeriodDate($this->periodoDeApplied)) {
            $query->whereDate('data', '>=', $de);
        }

        if ($ate = $this->normalizePeriodDate($this->periodoAteApplied)) {
            $query->whereDate('data', '<=', $ate);
        }

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyDefaultOrder(Builder $query): Builder
    {
        $allowedOrder = ['numero', 'data'];
        $orderBy = in_array($this->orderBy, $allowedOrder, true) ? $this->orderBy : 'numero';
        $direction = $this->orderDirection === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $direction);
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, ['numero', 'cliente', 'vendedor', 'cidade', 'uf'], true)
            ? $this->searchColumn
            : 'cliente';

        $prefixLike = $term.'%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $prefixLike),
            'cliente' => $query->where(function (Builder $outer) use ($prefixLike): void {
                $outer->where('cliente_nome', 'like', $prefixLike)
                    ->orWhereHas('cliente', fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $prefixLike));
            }),
            'vendedor' => $query->whereHas('vendedor', fn (Builder $vendedorQuery): Builder => $vendedorQuery->where('nome', 'like', $prefixLike)),
            'cidade' => $query->where(function (Builder $outer) use ($prefixLike): void {
                $outer->where('cliente_cidade', 'like', $prefixLike)
                    ->orWhereHas('cliente', fn (Builder $clienteQuery): Builder => $clienteQuery->where('cidade_nome', 'like', $prefixLike));
            }),
            'uf' => $query->where(function (Builder $outer) use ($prefixLike): void {
                $outer->where('cliente_uf', 'like', $prefixLike)
                    ->orWhereHas('cliente', fn (Builder $clienteQuery): Builder => $clienteQuery->where('uf', 'like', $prefixLike));
            }),
        };
    }

    protected function normalizePeriodDate(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $this->isValidPeriodIsoDate($value) ? $value : null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches) === 1) {
            $iso = sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);

            return $this->isValidPeriodIsoDate($iso) ? $iso : null;
        }

        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $matches) === 1) {
            $iso = sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);

            return $this->isValidPeriodIsoDate($iso) ? $iso : null;
        }

        return null;
    }

    protected function isValidPeriodIsoDate(string $iso): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
