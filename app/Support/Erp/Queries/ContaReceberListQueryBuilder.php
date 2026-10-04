<?php

namespace App\Support\Erp\Queries;

use App\Models\ContaReceber;
use App\Support\Erp\ContaReceberPedidoExibicao;
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
        public array $searchFieldsActive = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        $allowedSituacao = ['todos', 'a_receber', 'atrasadas', 'recebidas'];
        $allowedForma = array_merge(['todos'], array_keys(ContaReceber::formaLabels()));
        $allowedCampo = [
            'emissao', 'historico', 'documento', 'cliente', 'vencimento',
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
            "{$table}.multa",
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

    public function sumSaldoAtrasado(): float
    {
        $anterior = $this->situacaoFilter;
        $this->situacaoFilter = 'todos';

        try {
            $hoje = ErpTimezone::toLocal()->toDateString();

            return (float) $this->buildFilteredQuery()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<', $hoje)
                ->sum('saldo');
        } finally {
            $this->situacaoFilter = $anterior;
        }
    }

    /**
     * Quantidade de títulos em cada situação, respeitando cliente, período, forma e busca.
     *
     * @return array{todos: int, a_receber: int, atrasadas: int, recebidas: int}
     */
    public function contarPorSituacao(): array
    {
        $anterior = $this->situacaoFilter;
        $this->situacaoFilter = 'todos';

        try {
            $hoje = ErpTimezone::toLocal()->toDateString();
            $row = $this->buildFilteredQuery()->selectRaw(
                'COUNT(*) as todos,
                 COALESCE(SUM(CASE WHEN saldo > 0 AND DATE(vencimento) >= ? THEN 1 ELSE 0 END), 0) as a_receber,
                 COALESCE(SUM(CASE WHEN saldo > 0 AND DATE(vencimento) < ? THEN 1 ELSE 0 END), 0) as atrasadas,
                 COALESCE(SUM(CASE WHEN saldo <= 0 THEN 1 ELSE 0 END), 0) as recebidas',
                [$hoje, $hoje],
            )->first();
        } finally {
            $this->situacaoFilter = $anterior;
        }

        return [
            'todos' => (int) ($row->todos ?? 0),
            'a_receber' => (int) ($row->a_receber ?? 0),
            'atrasadas' => (int) ($row->atrasadas ?? 0),
            'recebidas' => (int) ($row->recebidas ?? 0),
        ];
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

        $columns = $this->activeLocalSearchColumns();

        if (count($columns) === 1) {
            $this->applyLocalSearchOnColumn($query, $columns[0], $term);

            return;
        }

        $query->where(function (Builder $outer) use ($columns, $term): void {
            foreach ($columns as $index => $column) {
                $apply = function (Builder $inner) use ($column, $term): void {
                    $this->applyLocalSearchOnColumn($inner, $column, $term);
                };

                if ($index === 0) {
                    $outer->where($apply);
                } else {
                    $outer->orWhere($apply);
                }
            }
        });
    }

    /**
     * @return list<string>
     */
    protected function activeLocalSearchColumns(): array
    {
        $allowed = $this->localSearchColumns();
        $active = array_values(array_unique(array_filter(
            $this->searchFieldsActive,
            fn (mixed $column): bool => is_string($column) && in_array($column, $allowed, true),
        )));

        if ($active !== []) {
            return $active;
        }

        return [in_array($this->searchColumn, $allowed, true) ? $this->searchColumn : 'cliente'];
    }

    protected function applyLocalSearchOnColumn(Builder $query, string $column, string $term): void
    {
        $prefixLike = $term.'%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $prefixLike),
            'historico' => $query->where('historico', 'like', $prefixLike),
            'documento' => $this->applyLocalSearchByDocumento($query, $term),
            'numero_cheque' => $query->where('numero_cheque', 'like', $prefixLike),
            'cliente' => $query->whereHas(
                'cliente',
                function (Builder $clienteQuery) use ($term): Builder {
                    $like = '%'.$term.'%';

                    return $clienteQuery->where(function (Builder $inner) use ($like): void {
                        $inner->where('nome_razao', 'like', $like)
                            ->orWhere('apelido_fantasia', 'like', $like);
                    });
                },
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
            'emissao', 'historico', 'documento', 'cliente', 'vencimento',
            'valor', 'numero_cheque', 'desconto', 'juros', 'valor_recebido', 'recebido_em', 'saldo',
        ];
    }

    /**
     * Número puro encontra o documento sem o prefixo (FV-83, PDV-000083, OS-83/2).
     * Texto com letras continua no começo do documento (FV, PDV, FV-83).
     */
    protected function applyLocalSearchByDocumento(Builder $query, string $term): void
    {
        $numero = preg_match('/^\d+$/', $term) === 1 ? (int) $term : null;

        $query->where(function (Builder $inner) use ($term, $numero): void {
            $inner->where('documento', 'like', $term.'%');

            if ($numero === null) {
                return;
            }

            $documento = 'documento';

            if ($this->databaseDriver($inner) === 'sqlite') {
                $inner->orWhereRaw(
                    "CAST(
                        CASE
                            WHEN instr(substr({$documento}, instr({$documento}, '-') + 1), '/') > 0
                            THEN substr(
                                substr({$documento}, instr({$documento}, '-') + 1),
                                1,
                                instr(substr({$documento}, instr({$documento}, '-') + 1), '/') - 1
                            )
                            ELSE substr({$documento}, instr({$documento}, '-') + 1)
                        END AS INTEGER
                    ) = ?",
                    [$numero],
                );

            } else {
                $inner->orWhereRaw(
                    "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX({$documento}, '-', -1), '/', 1) AS UNSIGNED) = ?",
                    [$numero],
                );
            }

            $this->applyLocalSearchByNumeroMonitor($inner, $numero);
        });
    }

    /**
     * O número digitado é o Nº Pedido do Monitor (vendas.numero), não o id FV-.
     */
    protected function applyLocalSearchByNumeroMonitor(Builder $query, int $numero): void
    {
        foreach (ContaReceberPedidoExibicao::orderIdsDoNumero($numero) as $orderId) {
            $query->orWhere('documento', 'FV-'.$orderId)
                ->orWhere('documento', 'like', 'FV-'.$orderId.'/%');
        }
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
