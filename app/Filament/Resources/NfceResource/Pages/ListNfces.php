<?php

namespace App\Filament\Resources\NfceResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\NfceResource;
use App\Filament\Resources\NfceResource\Pages\Concerns\ManagesNfceClienteEmail;
use App\Filament\Resources\NfceResource\Pages\Concerns\ManagesNfceContadorEmail;
use App\Filament\Resources\NfceResource\Pages\Concerns\ManagesNfceFiscalActions;
use App\Filament\Resources\NfceResource\Pages\Concerns\ManagesNfceRelatorio;
use App\Livewire\Erp\NfceListTable;
use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Nfce\NfceEmpresaEscopo;
use App\Support\Erp\Nfce\NfceImpressaoFiscal;
use App\Support\Erp\Pdv\PdvNfceCupomPrinter;
use App\Support\Fiscal\NfceContingenciaJustificativa;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListNfces extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use ManagesNfceFiscalActions;
    use ManagesNfceRelatorio;
    use ManagesNfceContadorEmail;
    use ManagesNfceClienteEmail;

    protected static string $resource = NfceResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'serie';

    #[Url(as: 'status')]
    public string $statusFilter = PdvVendaNfce::TAB_TRANSMITIDOS;

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public string $chaveFilter = '';

    public string $chaveFilterApplied = '';

    public bool $nfceRegularizacaoOpen = false;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('NFC-e');

        if (! in_array((int) $this->tableRecordsPerPage, [25, 50, 100], true)) {
            $this->tableRecordsPerPage = 50;
        }

        if ($this->periodoDe === '') {
            $this->periodoDe = now()->startOfMonth()->format('Y-m-d');
        }

        if ($this->periodoAte === '') {
            $this->periodoAte = now()->format('Y-m-d');
        }

        if ($this->periodoDeApplied === '') {
            $this->periodoDeApplied = $this->periodoDe;
        }

        if ($this->periodoAteApplied === '') {
            $this->periodoAteApplied = $this->periodoAte;
        }

        $this->statusFilter = PdvVendaNfce::normalizeTabFilter($this->statusFilter);
    }

    /**
     * A grade é renderizada pelo Livewire filho {@see NfceListTable}.
     * Evita o EmbeddedTable Filament (HTML ~28 KB/linha).
     */
    public function mountInteractsWithTable(): void
    {
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-nfe-page';
    }

    protected function erpListExtraPageClasses(): array
    {
        return ['erp-nfce-page'];
    }

    protected function erpListEntityName(): string
    {
        return 'uma NFC-e';
    }

    protected function defaultErpListSelectPrompt(string $action): string
    {
        $entity = $this->erpListEntityName();

        return match ($action) {
            'imprimir' => 'uma NFC-e na lista para imprimir',
            'cancelar' => 'uma NFC-e autorizada para cancelar',
            'recuperar' => 'uma NFC-e para consultar na SEFAZ',
            'transmitir' => 'uma ou mais NFC-e em contingência para transmitir',
            'enviar' => 'uma NFC-e autorizada para enviar',
            'edit' => "{$entity} na lista",
            'delete' => "{$entity} para excluir",
            default => $entity,
        };
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            // Pesquisa só pelo campo: sem tecla de foco (o padrão F6 conflitava com Imprimir).
            'searchInput' => null,
            'searchFocusKey' => false,
            'doubleClickEdit' => false,
            'create' => 'modulePending',
            'edit' => 'imprimirNfce',
            'delete' => null,
            'singleFlight' => true,
            'blockingSelector' => '.erp-nfce-fiscal-modal, .erp-nfe-danfe-email-modal, .erp-orc-email-modal, [data-erp-sefaz-progress].is-visible',
            'extraKeys' => [
                'F2' => ['method' => 'cancelarNfce'],
                'F3' => ['method' => 'inutilizarNfce'],
                'F4' => ['method' => 'recuperarNfce'],
                'F5' => ['method' => 'transmitirNfce'],
                'F6' => ['method' => 'imprimirNfce'],
                'F7' => ['method' => 'printNfceRelatorio'],
                'F8' => ['method' => 'openNfceEnviarModal'],
                'F9' => ['method' => 'openNfceRegularizacao'],
                'F11' => ['method' => 'openNfceContadorEmailModal'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        // Mantido por compatibilidade Filament; a UI usa {@see NfceListTable}.
        return $this->applyErpListSelection(NfceResource::table($table));
    }

    protected function clearListSelection(): void
    {
        $this->highlightedRecordId = null;
        $this->clearNfceTransmitirSelecao();
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    /**
     * Filtros da listagem / totais / bulk.
     * A grade leve aplica o mesmo filtro em {@see NfceListTable} com SELECT enxuto (sem XML).
     *
     * @param  bool  $withListRelations  eager load só quando for materializar linhas (não para SUM/pluck).
     */
    protected function buildListQuery(bool $withListRelations = false): Builder
    {
        $table = (new PdvVendaNfce)->getTable();
        $query = PdvVendaNfce::query();

        if ($withListRelations) {
            $query
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

        NfceEmpresaEscopo::aplicar($query, $this->empresaIdAtiva());

        $statuses = PdvVendaNfce::statusesForTab($this->statusFilter);
        $query->whereIn("{$table}.status", $statuses);
        PdvVendaNfce::excluirSimuladasForaTransmitidos($query, $this->statusFilter);

        if (filled($this->periodoDeApplied)) {
            $query->whereHas('pdvVenda', fn (Builder $venda): Builder => $venda
                ->whereDate('fechado_em', '>=', $this->periodoDeApplied));
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereHas('pdvVenda', fn (Builder $venda): Builder => $venda
                ->whereDate('fechado_em', '<=', $this->periodoAteApplied));
        }

        if (filled($this->chaveFilterApplied)) {
            $digits = preg_replace('/\D/', '', $this->chaveFilterApplied) ?? '';
            $query->where("{$table}.chave", 'like', '%'.$digits.'%');
        }

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function pushNfceListRefresh(bool $resetSort = false, bool $skipParentRender = true): void
    {
        $this->dispatch(
            'erp-nfce-list-refresh',
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
            chaveFilterApplied: $this->chaveFilterApplied,
            perPage: (int) ($this->tableRecordsPerPage ?: 50),
            resetSort: $resetSort,
            selectedTransmitIds: $this->nfceSelecionadosTransmitir,
            highlightedRecordId: $this->highlightedRecordId,
        )->to(NfceListTable::class);

        if ($skipParentRender) {
            $this->skipRender();
        }
    }

    protected function nfceTransmitSelecaoChanged(): void
    {
        $this->pushNfceListRefresh(skipParentRender: false);
    }

    #[On('erp-nfce-toggle-transmit')]
    public function onErpNfceToggleTransmit(int $id): void
    {
        $this->toggleNfceTransmitirSelecionado($id);
    }

    public function resetTable(): void
    {
        $this->pushNfceListRefresh(skipParentRender: false);
    }

    public function refreshTable(): void
    {
        $this->pushNfceListRefresh();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
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

        match ($column) {
            'serie' => $query->where('serie', 'like', $like),
            'numero' => $query->where('numero', 'like', $like),
            'chave' => $query->where('chave', 'like', $like),
            'protocolo' => $query->where('protocolo', 'like', $like),
            'cpf' => $query->where(function (Builder $outer) use ($like): void {
                $outer->whereHas('pdvVenda', fn (Builder $venda): Builder => $venda->where('cpf_nota', 'like', $like))
                    ->orWhereHas('pdvVenda.person', fn (Builder $person): Builder => $person->where('cpf_cnpj', 'like', $like));
            }),
            'caixa' => $query->whereHas('pdvVenda.sessao.terminal', fn (Builder $terminal): Builder => $terminal->where('nome', 'like', $like)),
            'usuario' => $query->whereHas('pdvVenda.user', fn (Builder $user): Builder => $user->where('name', 'like', $like)),
            'vendedor' => $query->where(function (Builder $outer) use ($like): void {
                $outer->whereHas('pdvVenda.vendedor', fn (Builder $vendedor): Builder => $vendedor->where('nome', 'like', $like))
                    ->orWhereHas('pdvVenda', fn (Builder $venda): Builder => $venda->where('vendedor_nome', 'like', $like));
            }),
            'pedido' => $query->whereHas('pdvVenda.venda', fn (Builder $venda): Builder => $venda->where('numero', 'like', $like)),
        };
    }

    protected function empresaIdAtiva(): ?int
    {
        return NfceEmpresaEscopo::empresaIdAtiva();
    }

    /**
     * Permissão no backend para comandos da tela (Livewire aceita chamada direta de métodos públicos).
     */
    protected function nfcePodeExecutar(?string $permissao = null): bool
    {
        if (! $this->erpAuthorizeOrNotify('nfce.access')) {
            return false;
        }

        return $permissao === null || $this->erpAuthorizeOrNotify($permissao);
    }

    /**
     * @param  list<string>  $with
     */
    protected function findNfceNoEscopo(?int $id, array $with = []): ?PdvVendaNfce
    {
        return NfceEmpresaEscopo::find($id, $this->empresaIdAtiva(), $with);
    }

    public function highlightRecord(int | string $recordId): void
    {
        $id = (int) $recordId;
        $this->highlightedRecordId = NfceEmpresaEscopo::filtrarIds([$id], $this->empresaIdAtiva()) !== [] ? $id : null;

        // Contingência / Duplicidade: remonta o rodapé (aviso amarelo + chave). Demais abas: só destaque.
        if (in_array($this->statusFilter, [PdvVendaNfce::TAB_CONTINGENCIA, PdvVendaNfce::TAB_DUPLICIDADE], true)) {
            unset($this->highlightedChave, $this->highlightedNfceAvisoRodape);

            return;
        }

        $this->skipRender();
    }

    /**
     * Motivo e orientação da NFC-e selecionada (rodapé amarelo) — Contingência ou Duplicidade.
     *
     * @return array{titulo: string, motivo: string, ajuda: string}|null
     */
    #[Computed]
    public function highlightedNfceAvisoRodape(): ?array
    {
        $id = $this->highlightedRecordId;

        if (! $id && $this->statusFilter === PdvVendaNfce::TAB_CONTINGENCIA && $this->nfceSelecionadosTransmitir !== []) {
            $id = (int) $this->nfceSelecionadosTransmitir[array_key_last($this->nfceSelecionadosTransmitir)];
        }

        if (! $id) {
            return null;
        }

        $record = $this->findNfceNoEscopo((int) $id);

        if (! $record) {
            return null;
        }

        $numero = str_pad((string) $record->numero, 6, '0', STR_PAD_LEFT);

        if ($this->statusFilter === PdvVendaNfce::TAB_CONTINGENCIA
            && $record->status === PdvVendaNfce::STATUS_CONTINGENCIA) {
            $motivo = trim((string) ($record->motivo_contingencia ?? ''));
            if ($motivo === '') {
                $motivo = NfceContingenciaJustificativa::PADRAO;
            }

            return [
                'titulo' => 'NFC-e '.$numero.' em contingência',
                'motivo' => $motivo,
                'ajuda' => 'A nota foi emitida sem autorização da SEFAZ. Selecione-a e use F5 | Transmitir para regularizar. Se a SEFAZ estiver fora do ar, aguarde e tente de novo.',
            ];
        }

        if ($this->statusFilter === PdvVendaNfce::TAB_DUPLICIDADE
            && $record->status === PdvVendaNfce::STATUS_DUPLICIDADE) {
            $motivo = trim((string) ($record->motivo_rejeicao ?? ''));
            if ($motivo === '') {
                $motivo = 'Rejeição 539: este número já foi autorizado na SEFAZ com outra chave.';
            }

            $conflitoConfirmado = str_contains(mb_strtolower($motivo, 'UTF-8'), 'conflito confirmado');

            return [
                'titulo' => 'NFC-e '.$numero.' em duplicidade (539)',
                'motivo' => $motivo,
                'ajuda' => $conflitoConfirmado
                    ? 'Conflito confirmado: a SEFAZ já tem outra NFC-e com este número (conteúdo diferente desta venda). O F4 não pode restaurar. Use F5 | Transmitir — o sistema emite com número novo e não reutiliza este nas próximas notas.'
                    : 'O número pode já ter sido usado na SEFAZ com outra chave. Use F4 | Recuperar para tentar restaurar a nota autorizada. Se o conflito for confirmado como outra emissão, use F5 | Transmitir (novo número).',
            ];
        }

        return null;
    }

    #[Computed]
    public function empresaNome(): string
    {
        $empresaId = $this->empresaIdAtiva();

        $empresa = $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : null;

        if (! $empresa) {
            return '—';
        }

        return $empresa->fantasia ?: ($empresa->nome ?: $empresa->razao_social);
    }

    #[Computed]
    public function searchColumnLabels(): array
    {
        return [
            'serie' => 'Série',
            'numero' => 'Número',
            'chave' => 'Chave',
            'protocolo' => 'Protocolo',
            'cpf' => 'CPF',
            'caixa' => 'Caixa',
            'usuario' => 'Usuário',
            'vendedor' => 'Vendedor',
            'pedido' => 'Nº Pedido',
        ];
    }

    #[Computed]
    public function filteredTotal(): float
    {
        // Sem eager load / SELECT de listagem — só o agregado do filtro atual.
        $query = $this->buildListQuery(withListRelations: false);

        return (float) $query
            ->join('pdv_vendas as pv_total', 'pv_total.id', '=', 'pdv_venda_nfce.pdv_venda_id')
            ->sum('pv_total.total');
    }

    /**
     * Faixas inutilizadas na SEFAZ (inclusive números que nunca viraram documento).
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function nfceInutilizacoesFaixas(): array
    {
        if ($this->statusFilter !== PdvVendaNfce::TAB_INUTILIZADOS) {
            return [];
        }

        $empresaId = $this->empresaIdAtiva();

        if (! $empresaId || ! DbSchema::hasTable('nfce_inutilizacoes')) {
            return [];
        }

        $query = DB::table('nfce_inutilizacoes as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.user_id')
            ->where('i.empresa_id', $empresaId)
            ->where('i.modelo', \App\Support\Fiscal\NfceNumeracao::MODELO)
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

    public function baixarXmlInutilizacao(int $id): ?StreamedResponse
    {
        if (! $this->nfcePodeExecutar()) {
            return null;
        }

        $empresaId = $this->empresaIdAtiva();

        $row = $empresaId && DbSchema::hasTable('nfce_inutilizacoes')
            ? DB::table('nfce_inutilizacoes')->where('id', $id)->where('empresa_id', $empresaId)->first()
            : null;

        if (! $row || blank($row->xml)) {
            Notification::make()->title('XML da inutilização não disponível.')->warning()->send();

            return null;
        }

        $nome = sprintf('inut-nfce-s%d-%d-%d.xml', (int) $row->serie, (int) $row->numero_inicial, (int) $row->numero_final);
        $xml = (string) $row->xml;

        return response()->streamDownload(function () use ($xml): void {
            echo $xml;
        }, $nome, ['Content-Type' => 'application/xml']);
    }

    #[Computed]
    public function highlightedChave(): string
    {
        if (! $this->highlightedRecordId) {
            return '';
        }

        $record = $this->findNfceNoEscopo((int) $this->highlightedRecordId);

        return (string) ($record?->chave ?? '');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.nfce.screen'),
                View::make('filament.components.erp.nfce.table-host')
                    ->columnSpanFull(),
                View::make('filament.components.erp.nfce.footer-total'),
                View::make('filament.components.erp.nfce.action-bar'),
                View::make('filament.components.erp.nfce.fiscal-modals'),
                View::make('filament.components.erp.nfce.fiscal-progress'),
                View::make('filament.components.erp.nfce.email-contador-modal'),
                View::make('filament.components.erp.nfce.enviar-modal'),
                View::make('filament.components.erp.nfce.regularizacao-host'),
            ]);
    }

    public function openNfceRegularizacao(): void
    {
        if (! $this->nfcePodeExecutar()) {
            return;
        }

        $this->nfceRegularizacaoOpen = true;
    }

    #[On('erp-nfce-regularizacao-fechar')]
    public function closeNfceRegularizacao(bool $houveEmissao = false): void
    {
        $this->nfceRegularizacaoOpen = false;

        if ($houveEmissao) {
            $this->pushNfceListRefresh(skipParentRender: false);
        }
    }

    public function setStatusFilter(string $filter): void
    {
        // Atalho/compatibilidade: a UI das abas chama NfceListTable::setStatusFilter (sem remount do pai).
        $normalized = PdvVendaNfce::normalizeTabFilter($filter);

        if ($normalized === $this->statusFilter) {
            $this->skipRender();

            return;
        }

        $this->statusFilter = $normalized;
        $this->clearListSelection();
        unset($this->filteredTotal, $this->highlightedChave, $this->highlightedNfceAvisoRodape, $this->nfceInutilizacoesFaixas);
        $this->pushNfceListRefresh(skipParentRender: true);
    }

    public function applyPeriodFilter(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->clearListSelection();
        $this->pushNfceListRefresh(skipParentRender: false);

        Notification::make()
            ->title('Período filtrado.')
            ->success()
            ->send();
    }

    public function applyPeriodFilterAuto(): void
    {
        if ($this->periodoDe === $this->periodoDeApplied && $this->periodoAte === $this->periodoAteApplied) {
            $this->skipRender();

            return;
        }

        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->clearListSelection();
        $this->pushNfceListRefresh(skipParentRender: false);
    }

    public function applyChaveFilter(): void
    {
        $this->chaveFilterApplied = trim($this->chaveFilter);
        $this->clearListSelection();
        $this->pushNfceListRefresh(skipParentRender: false);
    }

    public function applyChaveFilterValue(string $chave = ''): void
    {
        $digits = substr(preg_replace('/\D/', '', $chave) ?? '', 0, 44);
        $this->chaveFilter = $digits;

        if ($digits === $this->chaveFilterApplied) {
            $this->skipRender();

            return;
        }

        $this->applyChaveFilter();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->pushNfceListRefresh(resetSort: true, skipParentRender: false);
    }

    public function updatedLocalSearch(): void
    {
        $this->clearListSelection();
        $this->pushNfceListRefresh(skipParentRender: false);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->pushNfceListRefresh(skipParentRender: false);
    }

    public function applyFooterSearch(): void
    {
        $this->clearListSelection();
        $this->pushNfceListRefresh(skipParentRender: false);
    }

    public function imprimirNfce(): void
    {
        if (! $this->nfcePodeExecutar()) {
            return;
        }

        $id = $this->highlightedRecordIdOrNotify('imprimir');
        if (! $id) {
            return;
        }

        $nfce = $this->findNfceNoEscopo($id, ['pdvVenda']);
        $vendaId = $nfce?->pdv_venda_id;

        if (! $vendaId) {
            Notification::make()
                ->title('NFC-e sem venda vinculada.')
                ->warning()
                ->send();

            return;
        }

        if (($bloqueio = NfceImpressaoFiscal::motivoBloqueio($nfce)) !== null) {
            Notification::make()
                ->title('Impressão fiscal bloqueada.')
                ->body($bloqueio)
                ->warning()
                ->send();

            return;
        }

        $this->js(PdvNfceCupomPrinter::livewireOpenJs((int) $vendaId, 1));
    }
}
