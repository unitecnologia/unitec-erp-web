<?php

namespace App\Support\Erp\Queries;

use App\Models\CaixaLancamento;
use App\Support\Erp\ContaReceberPedidoExibicao;
use App\Support\Erp\ErpContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class CaixaListQueryBuilder
{
    public function __construct(
        public string $contaFilter = 'todas',
        public string $searchColumn = 'codigo',
        public string $localSearch = '',
        public string $periodoDeApplied = '',
        public string $periodoAteApplied = '',
        public string $orderBy = 'codigo',
        public string $orderDirection = 'desc',
        public bool $applyDefaultOrder = true,
    ) {}

    private ?bool $contaPdv = null;

    public function buildForList(): Builder
    {
        $table = (new CaixaLancamento)->getTable();

        $columns = [
            "{$table}.id",
            "{$table}.codigo",
            "{$table}.emissao",
            "{$table}.documento",
            "{$table}.historico",
            "{$table}.plano_contas",
            "{$table}.caixa_conta_id",
            "{$table}.entrada",
            "{$table}.saida",
        ];

        if ($this->isContaPdv()) {
            array_push(
                $columns,
                "{$table}.plano_conta_id",
                "{$table}.pdv_sessao_id",
                "{$table}.pdv_operador",
                "{$table}.pdv_terminal",
            );
        }

        $query = $this->buildFilteredQuery()->select($columns);

        $query->with(['conta:id,nome']);

        if (! $this->applyDefaultOrder) {
            return $query;
        }

        return $this->applyDefaultOrder($query);
    }

    public function sumEntradaFiltered(): float
    {
        return (float) $this->buildFilteredQuery()->sum('entrada');
    }

    public function sumSaidaFiltered(): float
    {
        return (float) $this->buildFilteredQuery()->sum('saida');
    }

    public function sumSaldoAnterior(): float
    {
        if (! filled($this->periodoDeApplied)) {
            return 0.0;
        }

        $query = $this->buildBaseScopeQuery()
            ->whereDate('emissao', '<', $this->periodoDeApplied);

        return (float) $query->sum('entrada') - (float) $query->sum('saida');
    }

    protected function buildFilteredQuery(): Builder
    {
        $query = $this->buildBaseScopeQuery();

        if (filled($this->periodoDeApplied)) {
            $query->whereDate('emissao', '>=', $this->periodoDeApplied);
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereDate('emissao', '<=', $this->periodoAteApplied);
        }

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function buildBaseScopeQuery(): Builder
    {
        if ($this->isContaPdv()) {
            $query = CaixaLancamento::query()->fromSub(
                (new CaixaPdvMovimentacaoQuery((int) $this->contaFilter))->build(),
                (new CaixaLancamento)->getTable(),
            );

            $query->where(fn (Builder $valores): Builder => $valores
                ->where('entrada', '<>', 0)
                ->orWhere('saida', '<>', 0));

            $this->applyEmpresaScope($query);

            return $query;
        }

        $query = CaixaLancamento::query();

        $this->applyEmpresaScope($query);
        $this->applyContaFilter($query);

        return $query;
    }

    /**
     * Conta tipo PDV: movimentação da sessão PDV + lançamentos da conta (leitura).
     */
    public function isContaPdv(): bool
    {
        if ($this->contaPdv === null) {
            $this->contaPdv = is_numeric($this->contaFilter)
                && CaixaPdvMovimentacaoQuery::isContaPdv((int) $this->contaFilter);
        }

        return $this->contaPdv;
    }

    /**
     * Ordem padrão da grade. No caixa PDV a ordem é cronológica (data/hora do movimento),
     * já que os movimentos da sessão não têm código do Livro Caixa.
     */
    public function applyListDefaultOrder(Builder $query, string $direction = 'desc'): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        if ($this->isContaPdv()) {
            return $query->orderBy('ordem', $direction)->orderBy('id', $direction);
        }

        return $query->orderBy('codigo', $direction);
    }

    protected function applyEmpresaScope(Builder $query): void
    {
        $empresaId = ErpContext::currentEmpresaId();

        if (! $empresaId || ! Schema::hasColumn((new CaixaLancamento)->getTable(), 'empresa_id')) {
            return;
        }

        $query->where('empresa_id', $empresaId);
    }

    protected function applyContaFilter(Builder $query): void
    {
        if ($this->contaFilter === 'todas') {
            return;
        }

        if (is_numeric($this->contaFilter)) {
            $query->where('caixa_conta_id', (int) $this->contaFilter);
        }
    }

    protected function applyDefaultOrder(Builder $query): Builder
    {
        return $this->applyListDefaultOrder($query, $this->orderDirection);
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return ['emissao', 'documento', 'historico', 'plano_contas', 'conta', 'entrada', 'saida'];
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)
            ? $this->searchColumn
            : 'documento';

        $prefixLike = $term.'%';

        match ($column) {
            'codigo' => $this->applyLocalSearchByCodigo($query, $term),
            'emissao' => $this->applyLocalSearchByEmissao($query, $term),
            'documento' => $this->applyLocalSearchByDocumento($query, $term),
            'historico' => $query->where('historico', 'like', $prefixLike),
            'plano_contas' => $query->where('plano_contas', 'like', $prefixLike),
            'conta' => $query->whereHas('conta', fn (Builder $contaQuery): Builder => $contaQuery->where('nome', 'like', $prefixLike)),
            'entrada' => $this->applyLocalSearchByMoney($query, $term, 'entrada'),
            'saida' => $this->applyLocalSearchByMoney($query, $term, 'saida'),
            default => null,
        };
    }

    protected function applyLocalSearchByDocumento(Builder $query, string $term): void
    {
        $query->where(function (Builder $inner) use ($term): void {
            $inner->where('documento', 'like', $term.'%');

            if (preg_match('/^\d+$/', $term) !== 1) {
                return;
            }

            foreach (ContaReceberPedidoExibicao::orderIdsDoNumero((int) $term) as $orderId) {
                $inner->orWhere('documento', 'FV-'.$orderId)
                    ->orWhere('documento', 'like', 'FV-'.$orderId.'/%');
            }
        });
    }

    protected function applyLocalSearchByCodigo(Builder $query, string $term): void
    {
        $digits = preg_replace('/\D/', '', $term) ?? '';

        if ($digits !== '' && is_numeric($digits)) {
            $query->where('codigo', 'like', $digits.'%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw('CAST(codigo AS TEXT) LIKE ?', [$term.'%']);

            return;
        }

        $query->whereRaw('CAST(codigo AS CHAR) LIKE ?', [$term.'%']);
    }

    protected function applyLocalSearchByEmissao(Builder $query, string $term): void
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $term, $matches)) {
            $query->whereDate('emissao', "{$matches[3]}-{$matches[2]}-{$matches[1]}");

            return;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $term)) {
            $query->whereDate('emissao', $term);

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("strftime('%d/%m/%Y', emissao) LIKE ?", ['%'.$term.'%']);

            return;
        }

        $query->whereRaw("DATE_FORMAT(emissao, '%d/%m/%Y') LIKE ?", ['%'.$term.'%']);
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
