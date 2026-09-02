<?php

namespace App\Support\Erp\Queries;

use App\Models\ContaPagar;
use App\Support\Erp\ErpTimezone;
use Illuminate\Database\Eloquent\Builder;

class ContaPagarListQueryBuilder
{
    /**
     * @param  list<string>  $searchFieldsActive
     * @param  array<string, string>  $localSearchByField
     */
    public function __construct(
        public string $situacaoFilter = 'todos',
        public string $fornecedorFilter = 'todos',
        public array $searchFieldsActive = ['fornecedor', 'vencimento'],
        public array $localSearchByField = [],
        public string $localSearchDe = '',
        public string $localSearchAte = '',
        public bool $skipFornecedorSearch = false,
        public string $orderBy = 'vencimento',
        public string $orderDirection = 'asc',
        public bool $applyDefaultOrder = true,
    ) {}

    public function build(): Builder
    {
        $query = $this->buildFilteredQuery()->with(['fornecedor']);

        return $this->applyDefaultOrder($query);
    }

    public function buildForList(): Builder
    {
        $table = (new ContaPagar)->getTable();

        $query = $this->buildFilteredQuery()->select([
            "{$table}.id",
            "{$table}.numero",
            "{$table}.emissao",
            "{$table}.documento",
            "{$table}.fornecedor_id",
            "{$table}.vencimento",
            "{$table}.valor",
            "{$table}.desconto",
            "{$table}.juros",
            "{$table}.valor_pago",
            "{$table}.pago_em",
            "{$table}.saldo",
        ]);

        $query->with(['fornecedor:id,nome_razao']);

        if (! $this->applyDefaultOrder) {
            return $query;
        }

        return $this->applyDefaultOrder($query);
    }

    public function sumSaldoFiltered(): float
    {
        return (float) $this->buildFilteredQuery()->sum('saldo');
    }

    public function sumValorPagoFiltered(): float
    {
        return (float) $this->buildFilteredQuery()->sum('valor_pago');
    }

    protected function buildFilteredQuery(): Builder
    {
        $query = ContaPagar::query();

        $hoje = ErpTimezone::toLocal()->toDateString();

        match ($this->situacaoFilter) {
            'a_pagar' => $query->where('saldo', '>', 0)->whereDate('vencimento', '>=', $hoje),
            'atrasadas' => $query->where('saldo', '>', 0)->whereDate('vencimento', '<', $hoje),
            'pagas' => $query->where('saldo', '<=', 0),
            default => $query,
        };

        foreach ($this->normalizedSearchFieldsActive() as $column) {
            if ($this->isDateSearchColumn($column)) {
                $this->applyLocalSearchDateRange($query, $column);

                continue;
            }

            $term = trim((string) ($this->localSearchByField[$column] ?? ''));

            if ($term === '') {
                continue;
            }

            if ($column === 'fornecedor') {
                if ($this->fornecedorFilter !== 'todos' && is_numeric($this->fornecedorFilter)) {
                    $query->where('fornecedor_id', (int) $this->fornecedorFilter);

                    continue;
                }

                if ($this->skipFornecedorSearch) {
                    continue;
                }

                $this->applyFornecedorLocalSearch($query, $term);

                continue;
            }

            $this->applyLocalSearchForColumn($query, $term, $column);
        }

        return $query;
    }

    protected function applyDefaultOrder(Builder $query): Builder
    {
        $direction = $this->orderDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy('vencimento', $direction);
    }

    /**
     * @return list<string>
     */
    public function normalizedSearchFieldsActive(): array
    {
        $allowed = $this->localSearchColumns();
        $active = array_values(array_filter(
            $this->searchFieldsActive,
            fn (mixed $column): bool => is_string($column) && in_array($column, $allowed, true),
        ));

        if ($active === []) {
            return ['vencimento'];
        }

        return array_values(array_unique($active));
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return [
            'numero', 'emissao', 'documento', 'fornecedor', 'vencimento',
            'valor', 'desconto', 'juros', 'valor_pago', 'pago_em', 'saldo',
        ];
    }

    public function isDateSearchColumn(string $column): bool
    {
        return in_array($column, ['emissao', 'vencimento', 'pago_em'], true);
    }

    protected function applyLocalSearchDateRange(Builder $query, string $column): void
    {
        if (filled($this->localSearchDe)) {
            $query->whereDate($column, '>=', $this->localSearchDe);
        }

        if (filled($this->localSearchAte)) {
            $query->whereDate($column, '<=', $this->localSearchAte);
        }
    }

    protected function applyFornecedorLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $prefixLike = $term.'%';

        $query->whereHas(
            'fornecedor',
            fn (Builder $fornecedorQuery): Builder => $fornecedorQuery
                ->where('nome_razao', 'like', $prefixLike)
                ->orWhere('apelido_fantasia', 'like', $prefixLike),
        );
    }

    protected function applyLocalSearchForColumn(Builder $query, string $term, string $column): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $prefixLike = $term.'%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $prefixLike),
            'emissao', 'vencimento', 'pago_em' => $this->applyLocalSearchByDate($query, $term, $column),
            'documento' => $query->where('documento', 'like', $prefixLike),
            'fornecedor' => $this->applyFornecedorLocalSearch($query, $term),
            'valor', 'desconto', 'juros', 'valor_pago', 'saldo' => $this->applyLocalSearchByMoney($query, $term, $column),
            default => null,
        };
    }

    protected function applyLocalSearchByDate(Builder $query, string $term, string $column): void
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $term, $matches)) {
            $query->whereDate($column, "{$matches[3]}-{$matches[2]}-{$matches[1]}");

            return;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $term)) {
            $query->whereDate($column, $term);

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("strftime('%d/%m/%Y', {$column}) LIKE ?", ['%'.$term.'%']);

            return;
        }

        $query->whereRaw("DATE_FORMAT({$column}, '%d/%m/%Y') LIKE ?", ['%'.$term.'%']);
    }

    protected function applyLocalSearchByMoney(Builder $query, string $term, string $column): void
    {
        $normalized = str_replace(['R$', ' '], '', $term);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            if ($this->databaseDriver($query) === 'sqlite') {
                $query->whereRaw("CAST({$column} AS TEXT) LIKE ?", ['%'.$normalized.'%']);

                return;
            }

            $query->where($column, 'like', '%'.$normalized.'%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', {$column}), '.', ',') LIKE ?", ['%'.$term.'%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT({$column}, 2), '.', ',') LIKE ?", ['%'.$term.'%']);
    }

    protected function databaseDriver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
    }
}
