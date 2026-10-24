<?php

namespace App\Support\Erp\Queries;

use App\Models\Nfe;
use App\Models\NfseItem;
use App\Models\OrdemServico;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\PdvVendaPagamento;
use App\Models\Venda;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpEmpresaScopeFilter;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\ErpSearchFieldSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendaListQueryBuilder
{
    /** Abas inferiores: Pedidos (tipo comercial) + vínculos fiscais. */
    public const DOC_TAB_TODOS = 'todos';

    public const DOC_TAB_PEDIDO = 'pedido';

    public const DOC_TAB_NFCE = 'nfce';

    public const DOC_TAB_NFE = 'nfe';

    public const DOC_TAB_NFSE = 'nfse';

    /**
     * @return list<string>
     */
    public static function documentTabKeys(): array
    {
        return [
            self::DOC_TAB_PEDIDO,
            self::DOC_TAB_NFCE,
            self::DOC_TAB_NFE,
            self::DOC_TAB_NFSE,
            self::DOC_TAB_TODOS,
        ];
    }

    /**
     * @param  list<string>  $searchFieldsActive
     */
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
        public bool $applyDefaultOrder = true,
        public string $searchColumn2 = '',
        public string $localSearch2 = '',
        public array $searchFieldsActive = [],
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
        $allowedTipo = self::documentTabKeys();
        $allowedCampo = ['numero', 'data', 'cliente', 'vendedor', 'plataforma', 'meio_pagamento', 'total', 'tipo', 'hora'];
        $allowedOrder = ['numero', 'data', 'total', 'hora'];
        $allowedDir = ['asc', 'desc'];

        $status = (string) $request->query('status', 'todos');
        $tipo = (string) $request->query('tipo', 'todos');
        // Legado: cupom era tipo comercial PDV, não vínculo NFC-e — ignora na URL.
        if ($tipo === Venda::TIPO_CUPOM) {
            $tipo = self::DOC_TAB_TODOS;
        }
        $campo = (string) $request->query('campo', 'cliente');
        $campo2 = (string) $request->query('campo2', '');
        $ordenar = (string) $request->query('ordenar', 'numero');
        $dir = (string) $request->query('dir', 'desc');
        $searchColumn = in_array($campo, $allowedCampo, true) ? $campo : 'cliente';
        $searchColumn2 = in_array($campo2, $allowedCampo, true) && $campo2 !== $searchColumn ? $campo2 : '';

        $camposCsv = trim((string) $request->query('campos', ''));
        $searchFieldsActive = $camposCsv !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $camposCsv))))
            : array_values(array_filter([$searchColumn, $searchColumn2], fn (string $c): bool => $c !== ''));
        $searchFieldsActive = array_values(array_unique(array_filter(
            $searchFieldsActive,
            fn (mixed $c): bool => is_string($c) && in_array($c, $allowedCampo, true),
        )));

        return new self(
            statusFilter: in_array($status, $allowedStatus, true) ? $status : 'todos',
            tipoFilter: in_array($tipo, $allowedTipo, true) ? $tipo : 'todos',
            searchColumn: $searchColumn,
            localSearch: trim((string) $request->query('q', '')),
            localSearchDe: trim((string) $request->query('de', '')),
            localSearchAte: trim((string) $request->query('ate', '')),
            localSearchHoraDe: trim((string) $request->query('hora_de', '')),
            localSearchHoraAte: trim((string) $request->query('hora_ate', '')),
            orderBy: in_array($ordenar, $allowedOrder, true) ? $ordenar : 'numero',
            orderDirection: in_array($dir, $allowedDir, true) ? $dir : 'desc',
            searchColumn2: $searchColumn2,
            localSearch2: $searchColumn2 !== '' ? trim((string) $request->query('q2', '')) : '',
            searchFieldsActive: $searchFieldsActive,
        );
    }

    /**
     * Query completa para relatórios/impressão.
     */
    public function build(): Builder
    {
        $query = $this->buildFilteredQuery();
        $query->with(['cliente', 'vendedor', 'pdvVenda.nfce', 'forcaVendasOrder', 'ordemServico', 'entrega']);

        return $this->applyDefaultOrder($query);
    }

    /**
     * Query leve para a grade — colunas visíveis + relacionamentos mínimos.
     */
    public function buildForList(): Builder
    {
        $table = (new Venda)->getTable();

        $query = $this->buildFilteredQuery()->select([
            "{$table}.id",
            "{$table}.numero",
            "{$table}.data",
            "{$table}.hora_abertura",
            "{$table}.hora",
            "{$table}.cliente_id",
            "{$table}.vendedor_id",
            "{$table}.vendedor_nome",
            "{$table}.plataforma",
            "{$table}.forma_pagamento",
            "{$table}.total",
            "{$table}.status",
            "{$table}.tipo",
        ]);

        $query->with([
            'cliente:id,nome_razao',
            'vendedor:id,nome',
            'pdvVenda:id,venda_id,numero,origem',
            'pdvVenda.nfce:id,pdv_venda_id,numero,serie',
            'nfes:id,venda_id,numero,serie,modelo,status',
            'entrega:id,venda_id,status',
            'forcaVendasOrder:id,venda_id,device_uuid,meli_order_id,payload,pedido_id',
            'forcaVendasOrder.pedido:id,numero',
            'ordemServico:id,venda_id,numero',
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

    /**
     * Indicadores da listagem (2 agregações SQL, sem hidratar linhas).
     *
     * - status: respeita busca/período/aba inferior; ignora aba de situação
     * - tipo (abas inferiores): respeita busca/período/situação; ignora aba inferior
     * - current: combinação atual (situação + aba inferior + busca)
     *
     * @return array{
     *     status: array<string, array{qtd: int, total: float}>,
     *     tipo: array<string, array{qtd: int, total: float}>,
     *     current: array{qtd: int, total: float}
     * }
     */
    public function indicators(): array
    {
        $statusKeys = [
            'todos',
            Venda::STATUS_ABERTO,
            Venda::STATUS_GRAVADO,
            Venda::STATUS_FECHADO,
            Venda::STATUS_CANCELADO,
        ];
        $tipoKeys = self::documentTabKeys();

        $status = [];
        foreach ($statusKeys as $key) {
            $status[$key] = ['qtd' => 0, 'total' => 0.0];
        }

        $tipo = [];
        foreach ($tipoKeys as $key) {
            $tipo[$key] = ['qtd' => 0, 'total' => 0.0];
        }

        $byStatus = $this->buildFilteredQuery(statusFilter: 'todos')
            ->selectRaw('status, COUNT(*) as qtd, COALESCE(SUM(total), 0) as total')
            ->groupBy('status')
            ->get();

        foreach ($byStatus as $row) {
            $key = (string) ($row->status ?? '');
            if (! array_key_exists($key, $status)) {
                continue;
            }
            $status[$key] = [
                'qtd' => (int) $row->qtd,
                'total' => (float) $row->total,
            ];
        }

        $statusTodosQtd = 0;
        $statusTodosTotal = 0.0;
        foreach ($statusKeys as $key) {
            if ($key === 'todos') {
                continue;
            }
            $statusTodosQtd += $status[$key]['qtd'];
            $statusTodosTotal += $status[$key]['total'];
        }
        $status['todos'] = ['qtd' => $statusTodosQtd, 'total' => $statusTodosTotal];

        $tipo = $this->aggregateDocumentTabIndicators(
            $this->buildFilteredQuery(tipoFilter: 'todos'),
            $tipo,
        );

        $current = $this->resolveCurrentIndicator($status, $tipo);

        return [
            'status' => $status,
            'tipo' => $tipo,
            'current' => $current,
        ];
    }

    /**
     * Payload pronto para DOM (abas wire:ignore + rodapé).
     *
     * @return array{
     *     statusCounts: array<string, string>,
     *     statusValues: array<string, string>,
     *     tipoCounts: array<string, string>,
     *     totalCount: string,
     *     totalValue: string
     * }
     */
    public function indicatorsUi(?array $indicators = null): array
    {
        $indicators ??= $this->indicators();
        $statusKeys = [
            'todos',
            Venda::STATUS_ABERTO,
            Venda::STATUS_GRAVADO,
            Venda::STATUS_FECHADO,
            Venda::STATUS_CANCELADO,
        ];

        $statusCounts = [];
        $statusValues = [];
        foreach ($statusKeys as $key) {
            $qtd = (int) ($indicators['status'][$key]['qtd'] ?? 0);
            $total = (float) ($indicators['status'][$key]['total'] ?? 0);
            $statusCounts[$key] = '('.$this->formatQuantityLabel($qtd).')';
            $statusValues[$key] = 'R$ '.$this->formatMoneyLabel($total);
        }

        $tipoCounts = [];
        foreach (self::documentTabKeys() as $key) {
            $qtd = (int) ($indicators['tipo'][$key]['qtd'] ?? 0);
            $tipoCounts[$key] = '('.$this->formatQuantityLabel($qtd).')';
        }

        $currentQtd = (int) ($indicators['current']['qtd'] ?? 0);
        $currentTotal = (float) ($indicators['current']['total'] ?? 0);

        return [
            'statusCounts' => $statusCounts,
            'statusValues' => $statusValues,
            'tipoCounts' => $tipoCounts,
            'totalCount' => $this->formatQuantityLabel($currentQtd).' vendas',
            'totalValue' => 'R$ '.$this->formatMoneyLabel($currentTotal),
        ];
    }

    /**
     * @param  array<string, array{qtd: int, total: float}>  $status
     * @param  array<string, array{qtd: int, total: float}>  $tipo
     * @return array{qtd: int, total: float}
     */
    protected function resolveCurrentIndicator(array $status, array $tipo): array
    {
        $statusFilter = $this->statusFilter !== '' ? $this->statusFilter : 'todos';
        $tipoFilter = $this->tipoFilter !== '' ? $this->tipoFilter : 'todos';

        if ($statusFilter === 'todos' && $tipoFilter === 'todos') {
            return $status['todos'];
        }

        if ($tipoFilter === 'todos' && array_key_exists($statusFilter, $status)) {
            return $status[$statusFilter];
        }

        if ($statusFilter === 'todos' && array_key_exists($tipoFilter, $tipo)) {
            return $tipo[$tipoFilter];
        }

        // Situação + tipo específicos: já estão no bucket de tipo (query com status aplicado).
        if (array_key_exists($tipoFilter, $tipo)) {
            return $tipo[$tipoFilter];
        }

        return ['qtd' => 0, 'total' => 0.0];
    }

    protected function formatQuantityLabel(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    protected function formatMoneyLabel(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    protected function sqlPrefixed(string $table): string
    {
        return DB::getTablePrefix().$table;
    }

    /**
     * Aba inferior da listagem (não confundir com vendas.tipo cupom).
     * - pedido: tipo comercial atual
     * - nfce/nfe/nfse: vínculo com documento fiscal
     */
    protected function applyDocumentoTabFilter(Builder $query, string $tab): void
    {
        match ($tab) {
            self::DOC_TAB_PEDIDO => $query->where('tipo', Venda::TIPO_PEDIDO),
            self::DOC_TAB_NFCE => $this->applyHasNfce($query),
            self::DOC_TAB_NFE => $this->applyHasNfe($query),
            self::DOC_TAB_NFSE => $this->applyHasNfse($query),
            default => null,
        };
    }

    protected function applyHasNfce(Builder $query): void
    {
        $query->where(function (Builder $outer): void {
            $outer->whereHas('pdvVenda.nfce')
                ->orWhereHas('ordemServico', function (Builder $os): void {
                    $nfce = (new PdvVendaNfce)->getTable();
                    $osTable = (new OrdemServico)->getTable();
                    $os->whereExists(function (QueryBuilder $sub) use ($nfce, $osTable): void {
                        $sub->selectRaw('1')
                            ->from($nfce)
                            ->whereColumn("{$nfce}.ordem_servico_id", "{$osTable}.id");
                    });
                });
        });
    }

    protected function applyHasNfe(Builder $query): void
    {
        $query->where(function (Builder $outer): void {
            $outer->whereHas('nfes', function (Builder $nfe): void {
                $this->constrainNfeModelo55($nfe);
            })->orWhereHas('ordemServico', function (Builder $os): void {
                $nfeTable = (new Nfe)->getTable();
                $osTable = (new OrdemServico)->getTable();
                $os->whereExists(function (QueryBuilder $sub) use ($nfeTable, $osTable): void {
                    $sub->selectRaw('1')
                        ->from($nfeTable)
                        ->whereColumn("{$nfeTable}.ordem_servico_id", "{$osTable}.id")
                        ->where(function (QueryBuilder $m) use ($nfeTable): void {
                            $m->whereNull("{$nfeTable}.modelo")
                                ->orWhere("{$nfeTable}.modelo", '')
                                ->orWhere("{$nfeTable}.modelo", '!=', '65');
                        });
                });
            });
        });
    }

    protected function applyHasNfse(Builder $query): void
    {
        // NFS-e não tem venda_id: vínculo confiável é via OS (cabeçalho ou item).
        $query->whereHas('ordemServico', function (Builder $os): void {
            $os->where(function (Builder $q): void {
                $q->whereHas('nfse')
                    ->orWhereExists(function (QueryBuilder $sub): void {
                        $itens = (new NfseItem)->getTable();
                        $osTable = (new OrdemServico)->getTable();
                        $sub->selectRaw('1')
                            ->from($itens)
                            ->whereColumn("{$itens}.ordem_servico_id", "{$osTable}.id");
                    });
            });
        });
    }

    protected function constrainNfeModelo55(Builder $nfe): void
    {
        $nfe->where(function (Builder $m): void {
            $m->whereNull('modelo')
                ->orWhere('modelo', '')
                ->orWhere('modelo', '!=', '65');
        });
    }

    /**
     * Contagens das abas inferiores em 1 SQL (EXISTS), sem hidratar linhas.
     *
     * @param  array<string, array{qtd: int, total: float}>  $tipo
     * @return array<string, array{qtd: int, total: float}>
     */
    protected function aggregateDocumentTabIndicators(Builder $baseQuery, array $tipo): array
    {
        $v = $this->sqlPrefixed('vendas');
        $pv = $this->sqlPrefixed('pdv_vendas');
        $nfce = $this->sqlPrefixed('pdv_venda_nfce');
        $nfe = $this->sqlPrefixed('nfes');
        $os = $this->sqlPrefixed('ordens_servico');
        $nfse = $this->sqlPrefixed('nfses');
        $nfseItens = $this->sqlPrefixed('nfse_itens');

        $nfceExists = "EXISTS (SELECT 1 FROM {$pv} pv INNER JOIN {$nfce} nfc ON nfc.pdv_venda_id = pv.id WHERE pv.venda_id = {$v}.id)"
            ." OR EXISTS (SELECT 1 FROM {$os} os INNER JOIN {$nfce} nfc2 ON nfc2.ordem_servico_id = os.id WHERE os.venda_id = {$v}.id)";

        $nfeExists = "EXISTS (SELECT 1 FROM {$nfe} n WHERE n.venda_id = {$v}.id AND (n.modelo IS NULL OR n.modelo = '' OR n.modelo <> '65'))"
            ." OR EXISTS (SELECT 1 FROM {$os} os INNER JOIN {$nfe} n2 ON n2.ordem_servico_id = os.id WHERE os.venda_id = {$v}.id AND (n2.modelo IS NULL OR n2.modelo = '' OR n2.modelo <> '65'))";

        $nfseExists = "EXISTS (SELECT 1 FROM {$os} os WHERE os.venda_id = {$v}.id AND ("
            ."EXISTS (SELECT 1 FROM {$nfse} s WHERE s.ordem_servico_id = os.id)"
            ." OR EXISTS (SELECT 1 FROM {$nfseItens} si WHERE si.ordem_servico_id = os.id)"
            .'))';

        $row = (clone $baseQuery)
            ->selectRaw(
                "COUNT(*) as todos_qtd, COALESCE(SUM({$v}.total), 0) as todos_total,"
                ." SUM(CASE WHEN {$v}.tipo = 'pedido' THEN 1 ELSE 0 END) as pedido_qtd,"
                ." COALESCE(SUM(CASE WHEN {$v}.tipo = 'pedido' THEN {$v}.total ELSE 0 END), 0) as pedido_total,"
                ." SUM(CASE WHEN ({$nfceExists}) THEN 1 ELSE 0 END) as nfce_qtd,"
                ." COALESCE(SUM(CASE WHEN ({$nfceExists}) THEN {$v}.total ELSE 0 END), 0) as nfce_total,"
                ." SUM(CASE WHEN ({$nfeExists}) THEN 1 ELSE 0 END) as nfe_qtd,"
                ." COALESCE(SUM(CASE WHEN ({$nfeExists}) THEN {$v}.total ELSE 0 END), 0) as nfe_total,"
                ." SUM(CASE WHEN ({$nfseExists}) THEN 1 ELSE 0 END) as nfse_qtd,"
                ." COALESCE(SUM(CASE WHEN ({$nfseExists}) THEN {$v}.total ELSE 0 END), 0) as nfse_total"
            )
            ->first();

        $tipo[self::DOC_TAB_TODOS] = [
            'qtd' => (int) ($row->todos_qtd ?? 0),
            'total' => (float) ($row->todos_total ?? 0),
        ];
        $tipo[self::DOC_TAB_PEDIDO] = [
            'qtd' => (int) ($row->pedido_qtd ?? 0),
            'total' => (float) ($row->pedido_total ?? 0),
        ];
        $tipo[self::DOC_TAB_NFCE] = [
            'qtd' => (int) ($row->nfce_qtd ?? 0),
            'total' => (float) ($row->nfce_total ?? 0),
        ];
        $tipo[self::DOC_TAB_NFE] = [
            'qtd' => (int) ($row->nfe_qtd ?? 0),
            'total' => (float) ($row->nfe_total ?? 0),
        ];
        $tipo[self::DOC_TAB_NFSE] = [
            'qtd' => (int) ($row->nfse_qtd ?? 0),
            'total' => (float) ($row->nfse_total ?? 0),
        ];

        return $tipo;
    }

    protected function buildFilteredQuery(?string $statusFilter = null, ?string $tipoFilter = null): Builder
    {
        $query = Venda::query();

        $empresaId = ErpContext::currentEmpresaId();

        if ($empresaId) {
            if (ErpSchema::hasColumn((new Venda)->getTable(), 'empresa_id')) {
                $query->where(function (Builder $scoped) use ($empresaId): void {
                    $scoped->where('empresa_id', $empresaId)
                        ->orWhere(function (Builder $legacy) use ($empresaId): void {
                            $legacy->whereNull('empresa_id');
                            ErpEmpresaScopeFilter::applyVendaOrigemFallback($legacy, $empresaId);
                        });
                });
            } else {
                ErpEmpresaScopeFilter::applyVendaOrigemFallback($query, $empresaId);
            }
        }

        $status = $statusFilter ?? $this->statusFilter;
        $tipo = $tipoFilter ?? $this->tipoFilter;

        if ($status !== 'todos') {
            $query->where('status', $status);
        }

        $this->applyDocumentoTabFilter($query, $tipo);

        $columns = $this->activeSearchColumns();

        if (in_array('data', $columns, true)) {
            $this->applyLocalSearchByDateRange($query);
        }

        if (in_array('hora', $columns, true)) {
            $this->applyLocalSearchByTimeRange($query);
        }

        $textColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool => ! in_array($column, ['data', 'hora'], true),
        ));

        $this->applySmartTextLocalSearch($query, $textColumns);

        return $query;
    }

    /**
     * Pesquisa inteligente: AND entre palavras, OR entre campos textuais selecionados.
     * Cada palavra pode casar em um campo diferente (ex.: PAULO no vendedor + DINHEIRO no pagamento).
     *
     * @param  list<string>  $textColumns
     */
    protected function applySmartTextLocalSearch(Builder $query, array $textColumns): void
    {
        if ($textColumns === []) {
            return;
        }

        $words = $this->splitSearchWords($this->localSearch);

        if ($words === []) {
            return;
        }

        foreach ($words as $word) {
            ErpSearchFieldSelection::applyOr(
                $query,
                $textColumns,
                function (Builder $inner, string $column) use ($word): void {
                    $this->applyLocalSearchWord($inner, $word, $column);
                },
            );
        }
    }

    /**
     * @return list<string>
     */
    protected function splitSearchWords(string $term): array
    {
        $folded = $this->foldSearchToken($term);
        $parts = preg_split('/\s+/u', $folded, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || $parts === []) {
            return [];
        }

        return array_values(array_unique($parts));
    }

    protected function foldSearchToken(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');

        return strtr($value, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ]);
    }

    /**
     * Campos de pesquisa ativos (seleção múltipla; vazio = sem filtro LOCALIZAR).
     *
     * @return list<string>
     */
    public function activeSearchColumns(): array
    {
        $allowed = $this->localSearchColumns();

        $fromActive = array_values(array_unique(array_filter(
            $this->searchFieldsActive,
            fn (mixed $column): bool => is_string($column) && $column !== '' && in_array($column, $allowed, true),
        )));

        if ($fromActive !== []) {
            return $fromActive;
        }

        // Seleção explícita vazia (página passou searchFieldsActive=[] e searchColumn='').
        if ($this->searchFieldsActive === [] && $this->searchColumn === '' && $this->searchColumn2 === '') {
            return [];
        }

        // Compatibilidade com construtores/URLs legadas (só searchColumn / campo2).
        return array_values(array_unique(array_filter(
            [$this->searchColumn, $this->searchColumn2],
            fn (string $column): bool => $column !== '' && in_array($column, $allowed, true),
        )));
    }

    protected function applyDefaultOrder(Builder $query): Builder
    {
        $allowedOrder = ['numero', 'data', 'total', 'hora'];
        $orderBy = in_array($this->orderBy, $allowedOrder, true) ? $this->orderBy : 'numero';
        $direction = $this->orderDirection === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $direction);
    }

    public function isDateSearchColumn(): bool
    {
        return in_array('data', $this->activeSearchColumns(), true);
    }

    public function isTimeSearchColumn(): bool
    {
        return in_array('hora', $this->activeSearchColumns(), true);
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

        $horaDe = $this->normalizeHoraFiltro($this->localSearchHoraDe);
        $horaAte = $this->normalizeHoraFiltro($this->localSearchHoraAte);

        if ($this->databaseDriver($query) === 'sqlite') {
            if ($horaDe !== null) {
                $query->whereRaw("strftime('%H:%M:%S', hora) >= ?", [$horaDe]);
            }

            if ($horaAte !== null) {
                $query->whereRaw("strftime('%H:%M:%S', hora) <= ?", [$horaAte]);
            }

            return;
        }

        if ($horaDe !== null) {
            $query->whereRaw("TIME_FORMAT(hora, '%H:%i:%s') >= ?", [$horaDe]);
        }

        if ($horaAte !== null) {
            $query->whereRaw("TIME_FORMAT(hora, '%H:%i:%s') <= ?", [$horaAte]);
        }
    }

    protected function normalizeHoraFiltro(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return ['numero', 'data', 'cliente', 'vendedor', 'plataforma', 'meio_pagamento', 'total', 'tipo', 'hora'];
    }

    /**
     * Aplica uma palavra a um único campo. Sempre adiciona restrição (0=1 se não aplicável),
     * para o OR entre campos não virar “match all”.
     */
    protected function applyLocalSearchWord(Builder $query, string $word, string $column): void
    {
        $word = $this->foldSearchToken($word);

        if ($word === '') {
            $query->whereRaw('0 = 1');

            return;
        }

        $column = in_array($column, $this->localSearchColumns(), true) ? $column : 'cliente';

        match ($column) {
            'numero' => $this->applyLocalSearchWordByNumero($query, $word),
            'cliente' => $this->applyLocalSearchWordByCliente($query, $word),
            'vendedor' => $this->applyLocalSearchWordByVendedor($query, $word),
            'meio_pagamento' => $this->applyLocalSearchWordByMeioPagamento($query, $word),
            'plataforma' => $this->applyLocalSearchByPlataforma($query, mb_strtolower($word, 'UTF-8')),
            'total' => $this->applyLocalSearchWordByTotal($query, $word),
            'tipo' => $this->applyLocalSearchByTipo($query, $word),
            default => $query->whereRaw('0 = 1'),
        };
    }

    protected function applyLocalSearchWordByNumero(Builder $query, string $word): void
    {
        if (preg_match('/^\d+$/', $word) !== 1) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function (Builder $q) use ($word): void {
            $q->where('numero', 'like', $word.'%')
                ->orWhere('numero', 'like', '%'.$word.'%');
        });
    }

    protected function applyLocalSearchWordByCliente(Builder $query, string $word): void
    {
        $like = '%'.$word.'%';

        $query->whereHas(
            'cliente',
            fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $like),
        );
    }

    protected function applyLocalSearchWordByVendedor(Builder $query, string $word): void
    {
        $like = '%'.$word.'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where('vendedor_nome', 'like', $like)
                ->orWhereHas(
                    'vendedor',
                    fn (Builder $vendedorQuery): Builder => $vendedorQuery->where('nome', 'like', $like),
                );
        });
    }

    protected function applyLocalSearchWordByMeioPagamento(Builder $query, string $word): void
    {
        $like = '%'.$word.'%';

        $query->where(function (Builder $outer) use ($like): void {
            $outer->where('forma_pagamento', 'like', $like);

            if (! ErpSchema::hasTable((new PdvVendaPagamento)->getTable())
                || ! ErpSchema::hasTable((new PdvVenda)->getTable())
            ) {
                return;
            }

            $outer->orWhereHas('pdvVenda', function (Builder $pdv) use ($like): void {
                $pdv->where(function (Builder $origem): void {
                    $origem->whereNull('origem')
                        ->orWhere('origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
                })->whereHas(
                    'pagamentos',
                    fn (Builder $pag): Builder => $pag
                        ->where('forma', 'like', $like)
                        ->where('valor', '>', 0),
                );
            });
        });
    }

    protected function applyLocalSearchWordByTotal(Builder $query, string $word): void
    {
        $normalized = str_replace(['R$', ' '], '', $word);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized) && preg_match('/^\d+([.,]\d+)?$/', $word) !== 1) {
            $query->whereRaw('0 = 1');

            return;
        }

        $this->applyLocalSearchByTotal($query, $word);
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

        $query->where('tipo', 'like', '%'.mb_strtolower($term, 'UTF-8').'%');
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

        $hasPlataformaColumn = ErpSchema::hasColumn((new Venda)->getTable(), 'plataforma');

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

                $q->orWhereHas('pdvVenda', fn (Builder $pdv): Builder => $pdv->comercial());
            }),
            Venda::PLATAFORMA_OS => $query->where(function (Builder $q) use ($hasPlataformaColumn): void {
                if ($hasPlataformaColumn) {
                    $q->where('plataforma', Venda::PLATAFORMA_OS);
                }

                $q->orWhereHas('ordemServico');
            }),
            default => $query
                ->when($hasPlataformaColumn, fn (Builder $q): Builder => $q->where(
                    fn (Builder $inner): Builder => $inner
                        ->where('plataforma', Venda::PLATAFORMA_ERP)
                        ->orWhereNull('plataforma'),
                ))
                ->whereDoesntHave('forcaVendasOrder')
                ->whereDoesntHave('pdvVenda', fn (Builder $pdv): Builder => $pdv->comercial())
                ->whereDoesntHave('ordemServico'),
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
        $active = $this->activeSearchColumns();
        $campos = $active !== [] ? implode(',', $active) : null;

        return [
            'status' => $this->statusFilter !== 'todos' ? $this->statusFilter : null,
            'tipo' => $this->tipoFilter !== 'todos' ? $this->tipoFilter : null,
            'campo' => ($active[array_key_last($active)] ?? $this->searchColumn) !== 'cliente'
                ? ($active[array_key_last($active)] ?? $this->searchColumn)
                : null,
            'campos' => $campos !== null && count($active) > 1 ? $campos : null,
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
