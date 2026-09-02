<?php

namespace App\Support\Erp\Queries;

use App\Models\ContaReceber;
use App\Support\Erp\ErpTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ContaReceberListQueryBuilder
{
    public function __construct(
        public string $situacaoFilter = 'todos',
        public string $formaFilter = 'todos',
        public string $clienteFilter = 'todos',
        public string $searchColumn = 'cliente',
        public string $localSearch = '',
        public string $periodoDe = '',
        public string $periodoAte = '',
        public bool $skipLocalSearch = false,
        public string $orderBy = 'emissao',
        public string $orderDirection = 'desc',
        public bool $applyDefaultOrder = true,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $allowedSituacao = ['todos', 'a_receber', 'atrasadas', 'recebidas'];
        $allowedForma = array_merge(['todos'], array_keys(ContaReceber::formaLabels()));
        $allowedCampo = [
            'numero', 'emissao', 'historico', 'documento', 'cliente', 'vencimento',
            'valor', 'numero_cheque', 'desconto', 'juros', 'valor_recebido', 'recebido_em', 'saldo',
        ];

        $situacao = (string) $request->query('situacao', 'todos');
        $forma = (string) $request->query('forma', 'todos');
        $campo = (string) $request->query('campo', 'cliente');
        $cliente = (string) $request->query('cliente', 'todos');

        return new self(
            situacaoFilter: in_array($situacao, $allowedSituacao, true) ? $situacao : 'todos',
            formaFilter: in_array($forma, $allowedForma, true) ? $forma : 'todos',
            clienteFilter: $cliente !== '' ? $cliente : 'todos',
            searchColumn: in_array($campo, $allowedCampo, true) ? $campo : 'cliente',
            localSearch: trim((string) $request->query('q', '')),
            periodoDe: trim((string) $request->query('de', '')),
            periodoAte: trim((string) $request->query('ate', '')),
        );
    }

    public function build(): Builder
    {
        $query = $this->buildFilteredQuery()->with(['cliente']);

        return $this->applyDefaultOrder($query);
    }

    public function buildForList(): Builder
    {
        $table = (new ContaReceber)->getTable();

        $query = $this->buildFilteredQuery()->select([
            "{$table}.id",
            "{$table}.numero",
            "{$table}.emissao",
            "{$table}.historico",
            "{$table}.documento",
            "{$table}.cartao_maquininha",
            "{$table}.cartao_bandeira",
            "{$table}.cliente_id",
            "{$table}.vencimento",
            "{$table}.valor",
            "{$table}.numero_cheque",
            "{$table}.desconto",
            "{$table}.juros",
            "{$table}.valor_recebido",
            "{$table}.recebido_em",
            "{$table}.saldo",
            "{$table}.forma",
        ]);

        $query->with(['cliente:id,nome_razao']);

        if (! $this->applyDefaultOrder) {
            return $query;
        }

        return $this->applyDefaultOrder($query);
    }

    public function sumSaldoFiltered(): float
    {
        return (float) $this->buildFilteredQuery()->sum('saldo');
    }

    public function sumValorRecebidoFiltered(): float
    {
        return (float) $this->buildFilteredQuery()->sum('valor_recebido');
    }

    protected function buildFilteredQuery(): Builder
    {
        $query = ContaReceber::query();

        if ($this->clienteFilter !== 'todos' && is_numeric($this->clienteFilter)) {
            $query->where('cliente_id', (int) $this->clienteFilter);
        }

        if (filled($this->periodoDe)) {
            $query->whereDate('vencimento', '>=', $this->periodoDe);
        }

        if (filled($this->periodoAte)) {
            $query->whereDate('vencimento', '<=', $this->periodoAte);
        }

        $hoje = ErpTimezone::toLocal()->toDateString();

        match ($this->situacaoFilter) {
            'a_receber' => $query->where('saldo', '>', 0)->whereDate('vencimento', '>=', $hoje),
            'atrasadas' => $query->where('saldo', '>', 0)->whereDate('vencimento', '<', $hoje),
            'recebidas' => $query->where('saldo', '<=', 0),
            default => $query,
        };

        if ($this->formaFilter !== 'todos' && array_key_exists($this->formaFilter, ContaReceber::formaLabels())) {
            $query->where('forma', $this->formaFilter);
        }

        if (filled($this->localSearch) && ! $this->skipLocalSearch) {
            $this->applyLocalSearch($query);
        }

        return $query;
    }

    protected function applyDefaultOrder(Builder $query): Builder
    {
        $direction = $this->orderDirection === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy('emissao', $direction)
            ->orderBy('numero', $direction);
    }

    protected function applyLocalSearch(Builder $query): void
    {
        $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)
            ? $this->searchColumn
            : 'cliente';

        $prefixLike = $term.'%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $prefixLike),
            'historico' => $query->where('historico', 'like', $prefixLike),
            'documento' => $query->where('documento', 'like', $prefixLike),
            'numero_cheque' => $query->where('numero_cheque', 'like', $prefixLike),
            'cliente' => $query->whereHas(
                'cliente',
                fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $prefixLike),
            ),
            'emissao', 'vencimento', 'recebido_em' => $this->applyLocalSearchByDate($query, $term, $column),
            'valor', 'desconto', 'juros', 'valor_recebido', 'saldo' => $this->applyLocalSearchByMoney($query, $term, $column),
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return [
            'numero', 'emissao', 'historico', 'documento', 'cliente', 'vencimento',
            'valor', 'numero_cheque', 'desconto', 'juros', 'valor_recebido', 'recebido_em', 'saldo',
        ];
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

    /**
     * @return array<string, string|null>
     */
    public function reportFilters(): array
    {
        return [
            'situacao' => $this->situacaoFilter !== 'todos' ? $this->situacaoFilter : null,
            'forma' => $this->formaFilter !== 'todos' ? $this->formaFilter : null,
            'cliente' => $this->clienteFilter !== 'todos' ? $this->clienteFilter : null,
            'campo' => $this->searchColumn !== 'cliente' ? $this->searchColumn : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'de' => filled($this->periodoDe) ? $this->periodoDe : null,
            'ate' => filled($this->periodoAte) ? $this->periodoAte : null,
        ];
    }
}
