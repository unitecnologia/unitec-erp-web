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
    ) {}

    public static function fromRequest(Request $request): self
    {
        $allowedSituacao = ['todos', 'a_receber', 'atrasadas', 'recebidas'];
        $allowedForma = array_merge(['todos'], array_keys(ContaReceber::formaLabels()));
        $allowedCampo = [
            'numero', 'emissao', 'historico', 'documento', 'cliente', 'vencimento',
            'valor', 'desconto', 'juros', 'valor_recebido', 'recebido_em', 'saldo',
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
        $query = ContaReceber::query()->with(['cliente']);

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

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query);
        }

        return $query
            ->orderByDesc('emissao')
            ->orderByDesc('numero');
    }

    protected function applyLocalSearch(Builder $query): void
    {
        $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = $this->searchColumn;
        $like = '%' . $term . '%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $like),
            'historico' => $query->where('historico', 'like', $like),
            'documento' => $query->where('documento', 'like', $like),
            'cliente' => $query->whereHas(
                'cliente',
                fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $like),
            ),
            'emissao', 'vencimento', 'recebido_em' => $this->applyLocalSearchByDate($query, $term, $column),
            'valor', 'desconto', 'juros', 'valor_recebido', 'saldo' => $this->applyLocalSearchByMoney($query, $term, $column),
            default => $query->whereHas(
                'cliente',
                fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $like),
            ),
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
        }
    }

    protected function applyLocalSearchByMoney(Builder $query, string $term, string $column): void
    {
        $normalized = str_replace(['R$', ' '], '', $term);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            $query->where($column, 'like', '%' . $normalized . '%');
        }
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
