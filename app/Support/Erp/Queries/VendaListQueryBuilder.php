<?php

namespace App\Support\Erp\Queries;

use App\Models\Venda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class VendaListQueryBuilder
{
    public function __construct(
        public string $statusFilter = 'todos',
        public string $tipoFilter = 'todos',
        public string $searchColumn = 'cliente',
        public string $localSearch = '',
        public string $localSearchDe = '',
        public string $localSearchAte = '',
        public string $localSearchHoraDe = '',
        public string $localSearchHoraAte = '',
        public string $orderBy = 'numero',
        public string $orderDirection = 'desc',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $allowedStatus = [
            'todos',
            Venda::STATUS_ABERTO,
            Venda::STATUS_GRAVADO,
            Venda::STATUS_FECHADO,
            Venda::STATUS_CANCELADO,
        ];
        $allowedTipo = ['todos', Venda::TIPO_PEDIDO, Venda::TIPO_CUPOM];
        $allowedCampo = ['numero', 'data', 'cliente', 'vendedor', 'plataforma', 'meio_pagamento', 'total', 'situacao', 'tipo', 'hora'];
        $allowedOrder = ['numero', 'data', 'total', 'hora'];
        $allowedDir = ['asc', 'desc'];

        $status = (string) $request->query('status', 'todos');
        $tipo = (string) $request->query('tipo', 'todos');
        $campo = (string) $request->query('campo', 'cliente');
        $ordenar = (string) $request->query('ordenar', 'numero');
        $dir = (string) $request->query('dir', 'desc');

        return new self(
            statusFilter: in_array($status, $allowedStatus, true) ? $status : 'todos',
            tipoFilter: in_array($tipo, $allowedTipo, true) ? $tipo : 'todos',
            searchColumn: in_array($campo, $allowedCampo, true) ? $campo : 'cliente',
            localSearch: trim((string) $request->query('q', '')),
            localSearchDe: trim((string) $request->query('de', '')),
            localSearchAte: trim((string) $request->query('ate', '')),
            localSearchHoraDe: trim((string) $request->query('hora_de', '')),
            localSearchHoraAte: trim((string) $request->query('hora_ate', '')),
            orderBy: in_array($ordenar, $allowedOrder, true) ? $ordenar : 'numero',
            orderDirection: in_array($dir, $allowedDir, true) ? $dir : 'desc',
        );
    }

    public function build(): Builder
    {
        $query = Venda::query()->with(['cliente', 'vendedor', 'pdvVenda.nfce', 'forcaVendasOrder']);

        if ($this->statusFilter !== 'todos') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->tipoFilter !== 'todos') {
            $query->where('tipo', $this->tipoFilter);
        }

        if ($this->isDateSearchColumn()) {
            $this->applyLocalSearchByDateRange($query);
        } elseif ($this->isTimeSearchColumn()) {
            $this->applyLocalSearchByTimeRange($query);
        } elseif (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        $allowedOrder = ['numero', 'data', 'total', 'hora'];
        $orderBy = in_array($this->orderBy, $allowedOrder, true) ? $this->orderBy : 'numero';
        $direction = $this->orderDirection === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $direction);
    }

    public function isDateSearchColumn(): bool
    {
        return $this->searchColumn === 'data';
    }

    public function isTimeSearchColumn(): bool
    {
        return $this->searchColumn === 'hora';
    }

    protected function applyLocalSearchByDateRange(Builder $query): void
    {
        if (! filled($this->localSearchDe) && ! filled($this->localSearchAte)) {
            return;
        }

        if (filled($this->localSearchDe)) {
            $query->whereDate('data', '>=', $this->localSearchDe);
        }

        if (filled($this->localSearchAte)) {
            $query->whereDate('data', '<=', $this->localSearchAte);
        }
    }

    protected function applyLocalSearchByTimeRange(Builder $query): void
    {
        if (! filled($this->localSearchHoraDe) && ! filled($this->localSearchHoraAte)) {
            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            if (filled($this->localSearchHoraDe)) {
                $query->whereRaw("strftime('%H:%M', hora) >= ?", [$this->localSearchHoraDe]);
            }

            if (filled($this->localSearchHoraAte)) {
                $query->whereRaw("strftime('%H:%M', hora) <= ?", [$this->localSearchHoraAte]);
            }

            return;
        }

        if (filled($this->localSearchHoraDe)) {
            $query->whereRaw("TIME_FORMAT(hora, '%H:%i') >= ?", [$this->localSearchHoraDe]);
        }

        if (filled($this->localSearchHoraAte)) {
            $query->whereRaw("TIME_FORMAT(hora, '%H:%i') <= ?", [$this->localSearchHoraAte]);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return ['numero', 'data', 'cliente', 'vendedor', 'plataforma', 'meio_pagamento', 'total', 'situacao', 'tipo', 'hora'];
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $trimmed = trim($term);

        if ($trimmed === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)
            ? $this->searchColumn
            : 'cliente';

        if ($column === 'meio_pagamento') {
            $query->where('forma_pagamento', mb_strtoupper($trimmed, 'UTF-8'));

            return;
        }

        if ($column === 'plataforma') {
            $this->applyLocalSearchByPlataforma($query, mb_strtolower($trimmed, 'UTF-8'));

            return;
        }

        if ($column === 'situacao' && array_key_exists($trimmed, Venda::statusLabels())) {
            $query->where('status', $trimmed);

            return;
        }

        if ($column === 'tipo' && array_key_exists($trimmed, Venda::tipoLabels())) {
            $query->where('tipo', $trimmed);

            return;
        }

        $term = mb_strtoupper($trimmed, 'UTF-8');
        $like = '%' . $term . '%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $like),
            'cliente' => $query->whereHas('cliente', fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $like)),
            'vendedor' => $query->where(fn (Builder $q): Builder => $q
                ->where('vendedor_nome', 'like', $like)
                ->orWhereHas('vendedor', fn (Builder $vendedorQuery): Builder => $vendedorQuery->where('nome', 'like', $like))),
            'total' => $this->applyLocalSearchByTotal($query, $term),
            'situacao' => $this->applyLocalSearchBySituacao($query, $term),
            'tipo' => $this->applyLocalSearchByTipo($query, $term),
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
                $query->whereRaw('CAST(total AS TEXT) LIKE ?', ['%' . $normalized . '%']);

                return;
            }

            $query->where('total', 'like', '%' . $normalized . '%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', total), '.', ',') LIKE ?", ['%' . $term . '%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT(total, 2), '.', ',') LIKE ?", ['%' . $term . '%']);
    }

    protected function applyLocalSearchBySituacao(Builder $query, string $term): void
    {
        $statuses = collect(Venda::statusLabels())
            ->filter(fn (string $label, string $status): bool => str_contains(mb_strtoupper($label, 'UTF-8'), $term) || str_contains(mb_strtoupper($status, 'UTF-8'), $term))
            ->keys()
            ->all();

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);

            return;
        }

        $query->where('status', 'like', '%' . mb_strtolower($term, 'UTF-8') . '%');
    }

    protected function applyLocalSearchByTipo(Builder $query, string $term): void
    {
        $tipos = collect(Venda::tipoLabels())
            ->filter(fn (string $label, string $tipo): bool => str_contains(mb_strtoupper($label, 'UTF-8'), $term) || str_contains(mb_strtoupper($tipo, 'UTF-8'), $term))
            ->keys()
            ->all();

        if ($tipos !== []) {
            $query->whereIn('tipo', $tipos);

            return;
        }

        $query->where('tipo', 'like', '%' . mb_strtolower($term, 'UTF-8') . '%');
    }

    protected function applyLocalSearchByPlataforma(Builder $query, string $term): void
    {
        $plataforma = $term;

        if (! array_key_exists($plataforma, Venda::plataformaLabels())) {
            $plataforma = collect(Venda::plataformaLabels())
                ->filter(fn (string $label, string $key): bool => str_contains(mb_strtoupper($label, 'UTF-8'), mb_strtoupper($term, 'UTF-8'))
                    || str_contains(mb_strtoupper($key, 'UTF-8'), mb_strtoupper($term, 'UTF-8')))
                ->keys()
                ->first();

            if ($plataforma === null) {
                $query->whereRaw('1 = 0');

                return;
            }
        }

        $hasPlataformaColumn = Schema::hasColumn((new Venda)->getTable(), 'plataforma');

        match ($plataforma) {
            Venda::PLATAFORMA_MOBILE => $query->where(function (Builder $q) use ($hasPlataformaColumn): void {
                if ($hasPlataformaColumn) {
                    $q->where('plataforma', Venda::PLATAFORMA_MOBILE);
                }

                $q->orWhereHas('forcaVendasOrder');
            }),
            Venda::PLATAFORMA_PDV => $query->where(function (Builder $q) use ($hasPlataformaColumn): void {
                if ($hasPlataformaColumn) {
                    $q->where('plataforma', Venda::PLATAFORMA_PDV);
                }

                $q->orWhereHas('pdvVenda');
            }),
            default => $query
                ->when($hasPlataformaColumn, fn (Builder $q): Builder => $q->where(
                    fn (Builder $inner): Builder => $inner
                        ->where('plataforma', Venda::PLATAFORMA_ERP)
                        ->orWhereNull('plataforma'),
                ))
                ->whereDoesntHave('forcaVendasOrder')
                ->whereDoesntHave('pdvVenda'),
        };
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
            'status' => $this->statusFilter !== 'todos' ? $this->statusFilter : null,
            'tipo' => $this->tipoFilter !== 'todos' ? $this->tipoFilter : null,
            'campo' => $this->searchColumn !== 'cliente' ? $this->searchColumn : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'de' => filled($this->localSearchDe) ? $this->localSearchDe : null,
            'ate' => filled($this->localSearchAte) ? $this->localSearchAte : null,
            'hora_de' => filled($this->localSearchHoraDe) ? $this->localSearchHoraDe : null,
            'hora_ate' => filled($this->localSearchHoraAte) ? $this->localSearchHoraAte : null,
            'ordenar' => $this->orderBy !== 'numero' ? $this->orderBy : null,
            'dir' => $this->orderDirection !== 'desc' ? $this->orderDirection : null,
        ];
    }
}
