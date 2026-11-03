<?php

namespace App\Livewire\Erp;

use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Nfce\NfceEmpresaEscopo;
use App\Support\Erp\Nfce\NfceListDavNumero;
use App\Support\Erp\Nfce\NfceListPeriodo;
use App\Support\Erp\Nfce\NfceListRowFormatter;
use App\Support\Fiscal\NfceNumeracao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class NfceListTable extends Component
{
    use WithPagination;

    private const PARENT_NAME = 'app.filament.resources.nfce-resource.pages.list-nfces';

    public string $statusFilter = PdvVendaNfce::TAB_TRANSMITIDOS;

    public string $searchColumn = 'serie';

    public string $localSearch = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public string $chaveFilterApplied = '';

    public int $perPage = 50;

    public ?string $sortColumn = 'numero';

    public string $sortDirection = 'desc';

    /** @var list<string> */
    public array $selectedTransmitIds = [];

    public ?int $highlightedRecordId = null;

    /**
     * Troca de aba: 1 request só na grade. Sem re-render do Filament pai.
     * Rodapé (totais do período) é responsabilidade exclusiva do ListNfces — não sobrescrever via JS.
     */
    public function setStatusFilter(string $filter): void
    {
        $normalized = PdvVendaNfce::normalizeTabFilter($filter);

        if ($normalized === $this->statusFilter) {
            $this->skipRender();

            return;
        }

        $this->statusFilter = $normalized;
        $this->selectedTransmitIds = [];
        $this->highlightedRecordId = null;
        $this->resetPage();

        $this->js(sprintf(
            '(() => {
                const status = %s;
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) {
                    parent.set("statusFilter", status, false);
                    parent.set("nfceSelecionadosTransmitir", [], false);
                    try { parent.set("highlightedRecordId", null, false); } catch (e) {}
                }
                try {
                    const url = new URL(window.location.href);
                    if (status === "transmitidos") {
                        url.searchParams.delete("status");
                    } else {
                        url.searchParams.set("status", status);
                    }
                    window.history.replaceState({}, "", url);
                } catch (e) {}
                window.dispatchEvent(new CustomEvent("erp-nfce-status", { detail: status }));
                document.querySelectorAll(".erp-nfce-page .fi-ta-row.erp-row-selected").forEach((row) => {
                    row.classList.remove("erp-row-selected");
                });
                const aviso = document.querySelector(".erp-nfce-page .erp-nfce__contingencia-aviso");
                if (aviso) aviso.remove();
            })()',
            json_encode($normalized, JSON_UNESCAPED_UNICODE),
            json_encode(self::PARENT_NAME, JSON_UNESCAPED_UNICODE),
        ));
    }

    /**
     * @param  list<string>  $selectedTransmitIds
     */
    #[On('erp-nfce-list-refresh')]
    public function refreshFromParent(
        string $statusFilter,
        string $searchColumn,
        string $localSearch,
        string $periodoDeApplied,
        string $periodoAteApplied,
        string $chaveFilterApplied,
        ?int $perPage = null,
        bool $resetSort = false,
        array $selectedTransmitIds = [],
        ?int $highlightedRecordId = null,
    ): void {
        $this->statusFilter = PdvVendaNfce::normalizeTabFilter($statusFilter);
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
        $this->periodoDeApplied = $periodoDeApplied;
        $this->periodoAteApplied = $periodoAteApplied;
        $this->chaveFilterApplied = $chaveFilterApplied;
        $this->selectedTransmitIds = array_values(array_map('strval', $selectedTransmitIds));
        $this->highlightedRecordId = $highlightedRecordId;

        if ($perPage !== null && $perPage > 0) {
            $this->perPage = $perPage;
        }

        if ($resetSort) {
            $this->sortColumn = 'numero';
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['numero', 'fechado_em'], true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = $column === 'numero' ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function toggleTransmit(int $id): void
    {
        $this->dispatch('erp-nfce-toggle-transmit', id: $id);
    }

    public function openHistorico(int $id): void
    {
        $this->dispatch('erp-nfce-open-historico', id: $id);
    }

    public function baixarXmlInutilizacao(int $id): void
    {
        $this->js(sprintf(
            '(() => {
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) parent.call("baixarXmlInutilizacao", %d);
            })()',
            json_encode(self::PARENT_NAME, JSON_UNESCAPED_UNICODE),
            $id,
        ));
    }

    public function render(): View
    {
        $records = $this->records();

        return view('livewire.erp.nfce-list-table', [
            'records' => $records,
            'formatter' => app(NfceListRowFormatter::class),
            'davComercialPorPdvVendaId' => NfceListDavNumero::aquecer($records->items()),
            'showSituacao' => $this->showsSituacaoColumn(),
            'isContingenciaTab' => $this->statusFilter === PdvVendaNfce::TAB_CONTINGENCIA,
            'inutilizacoesFaixas' => $this->inutilizacoesFaixas(),
        ]);
    }

    protected function records(): LengthAwarePaginator
    {
        $query = $this->buildListQuery();
        $this->applySort($query);

        // Um único COUNT: se zerado, não dispara o SELECT ordenado do período vazio.
        $total = (clone $query)->toBase()->getCountForPagination();
        $page = max(1, (int) $this->getPage());

        if ($total === 0) {
            return new Paginator([], 0, $this->perPage, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
        }

        $items = $query
            ->forPage($page, $this->perPage)
            ->get();

        return new Paginator($items, $total, $this->perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function inutilizacoesFaixas(): array
    {
        if ($this->statusFilter !== PdvVendaNfce::TAB_INUTILIZADOS) {
            return [];
        }

        $empresaId = NfceEmpresaEscopo::empresaIdAtiva();

        if (! $empresaId || ! DbSchema::hasTable('nfce_inutilizacoes')) {
            return [];
        }

        $query = DB::table('nfce_inutilizacoes as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.user_id')
            ->where('i.empresa_id', $empresaId)
            ->where('i.modelo', NfceNumeracao::MODELO)
            ->select([
                'i.id', 'i.serie', 'i.ambiente', 'i.numero_inicial', 'i.numero_final', 'i.protocolo',
                'i.status_codigo', 'i.justificativa', 'i.created_at', 'u.name as usuario',
            ])
            ->selectRaw(sprintf(
                "CASE WHEN %1\$s IS NULL OR %1\$s = '' THEN 0 ELSE 1 END as tem_xml",
                DB::getQueryGrammar()->wrap('i.xml'),
            ))
            ->orderByDesc('i.created_at')
            ->orderByDesc('i.id');

        if (filled($this->periodoDeApplied)) {
            $query->whereDate('i.created_at', '>=', $this->periodoDeApplied);
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereDate('i.created_at', '<=', $this->periodoAteApplied);
        }

        return $query->limit(200)->get()->map(fn ($row): array => (array) $row)->all();
    }

    /**
     * @return Builder<PdvVendaNfce>
     */
    protected function buildFilterQuery(): Builder
    {
        $table = (new PdvVendaNfce)->getTable();
        $query = PdvVendaNfce::query();

        // Período primeiro: FROM parte de pdv_vendas (STRAIGHT_JOIN) + fechado_em sargável.
        NfceListPeriodo::aplicar(
            $query,
            $table,
            $this->periodoDeApplied,
            $this->periodoAteApplied,
        );
        NfceEmpresaEscopo::aplicarNaListagem($query, NfceEmpresaEscopo::empresaIdAtiva());

        $query->whereIn("{$table}.status", PdvVendaNfce::statusesForTab($this->statusFilter));
        PdvVendaNfce::excluirSimuladasForaTransmitidos($query, $this->statusFilter);

        if (filled($this->chaveFilterApplied)) {
            $digits = preg_replace('/\D/', '', $this->chaveFilterApplied) ?? '';
            $query->where("{$table}.chave", 'like', '%'.$digits.'%');
        }

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    /**
     * @return Builder<PdvVendaNfce>
     */
    protected function buildListQuery(): Builder
    {
        $table = (new PdvVendaNfce)->getTable();

        return $this->buildFilterQuery()
            ->select([
                "{$table}.id",
                "{$table}.pdv_venda_id",
                "{$table}.empresa_id",
                "{$table}.serie",
                "{$table}.numero",
                "{$table}.chave",
                "{$table}.protocolo",
                "{$table}.status",
                "{$table}.simulada",
                "{$table}.operacao",
                "{$table}.motivo_rejeicao",
                "{$table}.motivo_contingencia",
            ])
            ->with([
                'pdvVenda' => static function ($q): void {
                    $q->select([
                        'id',
                        'pdv_caixa_sessao_id',
                        'user_id',
                        'vendedor_id',
                        'venda_id',
                        'person_id',
                        'fechado_em',
                        'cpf_nota',
                        'nome_nota',
                        'subtotal',
                        'acrescimo',
                        'desconto',
                        'numero',
                        'vendedor_nome',
                        'total',
                    ]);
                },
                'pdvVenda.sessao' => static fn ($q) => $q->select(['id', 'terminal_id', 'empresa_id']),
                'pdvVenda.sessao.terminal' => static fn ($q) => $q->select(['id', 'nome']),
                'pdvVenda.user' => static fn ($q) => $q->select(['id', 'name']),
                'pdvVenda.vendedor' => static fn ($q) => $q->select(['id', 'nome']),
                'pdvVenda.venda' => static fn ($q) => $q->select(['id', 'numero']),
                'pdvVenda.person' => static fn ($q) => $q->select(['id', 'nome_razao', 'cpf_cnpj']),
            ]);
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, [
            'serie', 'numero', 'chave', 'protocolo', 'cpf', 'caixa', 'usuario', 'vendedor', 'pedido',
        ], true) ? $this->searchColumn : 'serie';

        $like = '%'.$term.'%';
        $table = $query->getModel()->getTable();

        match ($column) {
            'serie' => $query->where("{$table}.serie", 'like', $like),
            'numero' => $query->where("{$table}.numero", 'like', $like),
            'chave' => $query->where("{$table}.chave", 'like', $like),
            'protocolo' => $query->where("{$table}.protocolo", 'like', $like),
            'cpf' => $query->where(function (Builder $outer) use ($like): void {
                $outer->whereHas('pdvVenda', fn (Builder $venda): Builder => $venda->where('cpf_nota', 'like', $like))
                    ->orWhereHas('pdvVenda.person', fn (Builder $person): Builder => $person->where('cpf_cnpj', 'like', $like));
            }),
            'caixa' => $query->where(function (Builder $outer) use ($like, $table): void {
                $termCaixa = mb_strtoupper(trim($like, '%'), 'UTF-8');
                $outer->whereHas('pdvVenda.sessao.terminal', fn (Builder $terminal): Builder => $terminal->where('nome', 'like', $like));
                if ($termCaixa !== '' && str_contains('CAIXA GERAL', $termCaixa)) {
                    $outer->orWhere("{$table}.operacao", NfceListDavNumero::OPERACAO_SCV_HIST);
                }
            }),
            'usuario' => $query->whereHas('pdvVenda.user', fn (Builder $user): Builder => $user->where('name', 'like', $like)),
            'vendedor' => $query->where(function (Builder $outer) use ($like): void {
                $outer->whereHas('pdvVenda.vendedor', fn (Builder $vendedor): Builder => $vendedor->where('nome', 'like', $like))
                    ->orWhereHas('pdvVenda', fn (Builder $venda): Builder => $venda->where('vendedor_nome', 'like', $like));
            }),
            'pedido' => $query->whereHas('pdvVenda.venda', fn (Builder $venda): Builder => $venda->where('numero', 'like', $like)),
        };
    }

    /**
     * @param  Builder<PdvVendaNfce>  $query
     */
    protected function applySort(Builder $query): void
    {
        $dir = $this->sortDirection === 'asc' ? 'asc' : 'desc';
        $table = $query->getModel()->getTable();

        if ($this->sortColumn === 'fechado_em') {
            $query
                ->orderBy(
                    PdvVenda::query()
                        ->select('fechado_em')
                        ->whereColumn(
                            (new PdvVenda)->getTable().'.id',
                            $table.'.pdv_venda_id'
                        )
                        ->limit(1),
                    $dir
                )
                ->orderBy("{$table}.id", $dir);

            return;
        }

        $query->orderBy("{$table}.numero", $dir)->orderBy("{$table}.id", $dir);
    }

    protected function showsSituacaoColumn(): bool
    {
        return in_array($this->statusFilter, [
            PdvVendaNfce::TAB_GRAVADOS,
            PdvVendaNfce::TAB_DUPLICIDADE,
            PdvVendaNfce::TAB_INUTILIZADOS,
            PdvVendaNfce::TAB_DENEGADO,
        ], true);
    }
}
