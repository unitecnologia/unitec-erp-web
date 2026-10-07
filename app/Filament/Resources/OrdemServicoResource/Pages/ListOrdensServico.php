<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Pages\NfsePage;
use App\Filament\Resources\NfeResource;
use App\Filament\Resources\OrdemServicoResource;
use App\Models\OrdemServico;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfe\NfeOrdemServicoService;
use App\Support\Erp\Nfse\NfseFromOrdemServico;
use App\Support\Erp\Os\OrdemServicoRetorno;
use App\Support\Erp\Os\OsReabrirService;
use DomainException;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class ListOrdensServico extends ListRecords
{
    use InteractsWithErpListPage;
    use Concerns\ManagesOrdemServicoEmailModal;
    use Concerns\ManagesOrdemServicoEspelhoModal;

    protected static string $resource = OrdemServicoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'cliente';

    #[Url(as: 'status')]
    public string $statusFilter = 'todos';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public bool $previewOverlayOpen = false;

    public ?string $previewOverlayUrl = null;

    public bool $printModalOpen = false;

    public bool $printDocsModalOpen = false;

    /** @var array<string, array{rotulo: string, url: string}> */
    #[Locked]
    public array $printDocsDisponiveis = [];

    /** @var list<string> */
    public array $printDocsMarcados = [];

    public bool $printDocsOsTecnica = false;

    #[Locked]
    public string $printDocsOsNumero = '';

    #[Locked]
    public ?string $osConfirmAcao = null;

    #[Locked]
    public ?int $osConfirmId = null;

    #[Locked]
    public string $osConfirmMensagem = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Ordem de Serviço');

        $this->highlightedRecordId = OrdemServicoRetorno::osSelecionadaNaRequisicao() ?? $this->highlightedRecordId;

        if ($this->periodoDe === '') {
            $this->periodoDe = now()->startOfMonth()->format('Y-m-d');
        }

        if ($this->periodoAte === '') {
            $this->periodoAte = now()->endOfMonth()->format('Y-m-d');
        }

        if ($this->periodoDeApplied === '') {
            $this->periodoDeApplied = $this->periodoDe;
        }

        if ($this->periodoAteApplied === '') {
            $this->periodoAteApplied = $this->periodoAte;
        }

        $this->dispatch(
            'erp-hydrate-os-dates',
            de: $this->periodoDe,
            ate: $this->periodoAte,
        );
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-orcamentos-page erp-os-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma ordem de serviço';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-orcamentos__input',
            'create' => 'createOrdem',
            'edit' => 'editOrdem',
            'delete' => 'cancelOrdem',
            'extraKeys' => [
                'F6' => ['method' => 'openPrintModal'],
                'F8' => ['method' => 'reabrirOrdem'],
                'F9' => ['method' => 'openSendModal'],
                'F10' => ['method' => 'emitirNfePecasDaOs'],
            ],
            'searchFocusKey' => 'F12',
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(OrdemServicoResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['cliente', 'atendente', 'nfseAutorizada', 'nfseItemAutorizado.nfse', 'nfeAutorizada', 'nfePecasEmitida']);

        if ($this->statusFilter === OrdemServico::SITUACAO_ABERTA) {
            $query->whereIn('situacao', [
                OrdemServico::SITUACAO_ABERTA,
                OrdemServico::SITUACAO_ANDAMENTO,
            ]);
        } elseif ($this->statusFilter === OrdemServico::SITUACAO_FINALIZADA) {
            $query->whereIn('situacao', [
                OrdemServico::SITUACAO_FINALIZADA,
                OrdemServico::SITUACAO_ENTREGUE,
            ]);
        } elseif ($this->statusFilter === OrdemServico::SITUACAO_CANCELADA) {
            $query->where('situacao', OrdemServico::SITUACAO_CANCELADA);
        }

        if ($de = $this->normalizePeriodDate($this->periodoDeApplied)) {
            $query->whereDate('data_inicio', '>=', $de);
        }

        if ($ate = $this->normalizePeriodDate($this->periodoAteApplied)) {
            $query->whereDate('data_inicio', '<=', $ate);
        }

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, ['numero', 'cliente', 'atendente', 'placa', 'equipamento'], true)
            ? $this->searchColumn
            : 'cliente';

        $like = '%'.$term.'%';

        match ($column) {
            'numero' => $query->where(function (Builder $q) use ($like, $term): void {
                $q->where('numero', 'like', $like);
                if (is_numeric($term)) {
                    $q->orWhere('codigo_legado', (int) $term);
                }
            }),
            'cliente' => $query->where(function (Builder $q) use ($like): void {
                $q->where('nome', 'like', $like)
                    ->orWhereHas('cliente', fn (Builder $c): Builder => $c->where('nome_razao', 'like', $like));
            }),
            'atendente' => $query->whereHas('atendente', fn (Builder $a): Builder => $a->where('nome', 'like', $like)),
            'placa' => $query->where(function (Builder $q) use ($like): void {
                $q->where('placa', 'like', $like)->orWhere('placa_veiculo', 'like', $like);
            }),
            'equipamento' => $query->where(function (Builder $q) use ($like): void {
                $q->where('descricao', 'like', $like)
                    ->orWhere('descricao2', 'like', $like)
                    ->orWhere('modelo', 'like', $like)
                    ->orWhere('marca', 'like', $like)
                    ->orWhere('numero_serie', 'like', $like);
            }),
        };
    }

    #[Computed]
    public function filteredTotal(): float
    {
        return (float) $this->buildListQuery()->sum('total_geral');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.ordens-servico.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.ordens-servico.footer-total'),
                View::make('filament.components.erp.ordens-servico.action-bar'),
                View::make('filament.components.erp.ordens-servico.print-modal'),
                View::make('filament.components.erp.ordens-servico.print-docs-modal'),
                View::make('filament.components.erp.ordens-servico.send-modal'),
                View::make('filament.components.erp.ordens-servico.preview-overlay'),
                View::make('filament.components.erp.ordens-servico.email-modal'),
                View::make('filament.components.erp.ordens-servico.espelho-modal'),
                View::make('filament.components.erp.ordens-servico.confirm-modal'),
            ]);
    }

    public function openPrintModal(): void
    {
        if (! $this->highlightedRecordId) {
            Notification::make()->title('Selecione uma ordem de serviço.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.print')) {
            return;
        }

        $ordem = $this->ordemSelecionadaParaFiscal();

        if ($ordem === null) {
            return;
        }

        $documentos = $this->documentosFiscaisImprimiveis($ordem);

        if ($documentos === []) {
            $this->printModalOpen = true;

            return;
        }

        $this->printDocsOsNumero = (string) ($ordem->numero ?: $ordem->id);
        $this->printDocsDisponiveis = $documentos;
        $this->printDocsMarcados = ['os', ...array_keys($documentos)];
        $this->printDocsOsTecnica = false;
        $this->printDocsModalOpen = true;
    }

    public function closePrintModal(): void
    {
        $this->printModalOpen = false;
    }

    public function closePrintDocsModal(): void
    {
        $this->printDocsModalOpen = false;
        $this->printDocsDisponiveis = [];
        $this->printDocsMarcados = [];
    }

    public function imprimirDocumentosSelecionados(): void
    {
        if (! $this->printDocsModalOpen || ! $this->highlightedRecordId) {
            return;
        }

        $marcados = array_values(array_unique(array_map('strval', $this->printDocsMarcados)));
        $urls = [];

        if (in_array('os', $marcados, true)) {
            $params = ['ordem' => $this->highlightedRecordId];

            if ($this->printDocsOsTecnica) {
                $params['tecnica'] = 1;
            }

            $urls[] = route('erp.reports.ordem-servico', $params);
        }

        foreach ($this->printDocsDisponiveis as $chave => $documento) {
            if (in_array($chave, $marcados, true)) {
                $urls[] = $documento['url'];
            }
        }

        if ($urls === []) {
            Notification::make()->title('Marque ao menos um documento para imprimir.')->warning()->send();

            return;
        }

        $this->closePrintDocsModal();
        $this->js(
            '((urls) => window.ErpPrintFila ? window.ErpPrintFila.imprimir(urls) : urls.forEach((u) => window.open(u, "_blank")))('
            .Js::from($urls)
            .')'
        );
    }

    /**
     * Notas com validade fiscal vinculadas à OS selecionada, já com a URL da rotina de impressão existente.
     * NFC-e ainda não tem vínculo com a OS (ver OrdemServico::nfceNumeroLista), por isso não entra.
     *
     * @return array<string, array{rotulo: string, url: string}>
     */
    private function documentosFiscaisImprimiveis(OrdemServico $ordem): array
    {
        $documentos = [];
        $nfse = $ordem->nfseAutorizadaVinculada();

        if ($nfse !== null && filled($nfse->xml_nfse) && ErpAccess::currentCan('nfse.access')) {
            $documentos['nfse'] = [
                'rotulo' => 'NFS-e nº '.trim((string) ($nfse->numero_nfse ?: $nfse->numero_dps)),
                'url' => route('erp.reports.nfse-impressao', ['nfse' => $nfse->id, 'embed' => 1]),
            ];
        }

        $nfe = $ordem->nfeAutorizada()->first();

        if ($nfe !== null && (filled($nfe->xml) || filled($nfe->chave))) {
            $documentos['nfe'] = [
                'rotulo' => 'NF-e nº '.(ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero),
                'url' => route('erp.reports.nfe-danfe', ['nfe' => $nfe->id]),
            ];
        }

        return $documentos;
    }

    public function imprimirOs(): void
    {
        $this->openPrintModal();
    }

    public function imprimirOsCompleta(): void
    {
        $this->abrirPreviewOs(tecnica: false);
    }

    public function imprimirOsTecnica(): void
    {
        $this->abrirPreviewOs(tecnica: true);
    }

    protected function abrirPreviewOs(bool $tecnica): void
    {
        if (! $this->highlightedRecordId) {
            Notification::make()->title('Selecione uma ordem de serviço.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.print')) {
            return;
        }

        $params = [
            'ordem' => $this->highlightedRecordId,
            'embed' => 1,
        ];

        if ($tecnica) {
            $params['tecnica'] = 1;
        }

        $this->closePrintModal();
        $this->previewOverlayUrl = route('erp.reports.ordem-servico', $params);
        $this->previewOverlayOpen = true;
    }

    #[On('close-os-preview')]
    public function closePreviewOverlay(): void
    {
        $this->previewOverlayOpen = false;
        $this->previewOverlayUrl = null;
    }

    public function setStatusFilter(string $filter): void
    {
        $allowed = [
            'todos',
            OrdemServico::SITUACAO_ABERTA,
            OrdemServico::SITUACAO_FINALIZADA,
            OrdemServico::SITUACAO_CANCELADA,
        ];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->statusFilter = $filter;
        $this->clearListSelection();
        $this->resetTable();
    }

    public function applyPeriodFilter(): void
    {
        $this->syncAppliedPeriodFilter();
        $this->notifyPeriodFilterResult();
    }

    public function applyPeriodFilterAuto(): void
    {
        $this->syncAppliedPeriodFilter();
    }

    protected function syncAppliedPeriodFilter(): void
    {
        $this->periodoDe = $this->normalizePeriodDate($this->periodoDe) ?? trim($this->periodoDe);
        $this->periodoAte = $this->normalizePeriodDate($this->periodoAte) ?? trim($this->periodoAte);

        $this->periodoDeApplied = $this->periodoDe !== '' ? $this->periodoDe : '';
        $this->periodoAteApplied = $this->periodoAte !== '' ? $this->periodoAte : '';

        if (
            filled($this->periodoDeApplied)
            && filled($this->periodoAteApplied)
            && $this->periodoDeApplied > $this->periodoAteApplied
        ) {
            [$this->periodoDeApplied, $this->periodoAteApplied] = [$this->periodoAteApplied, $this->periodoDeApplied];
            $this->periodoDe = $this->periodoDeApplied;
            $this->periodoAte = $this->periodoAteApplied;
        }

        $this->clearListSelection();
        $this->resetPage();
        $this->resetTable();
    }

    protected function notifyPeriodFilterResult(): void
    {
        $count = $this->buildListQuery()->count();

        if ($count === 0) {
            Notification::make()
                ->title('Nenhuma OS neste período.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title("Período filtrado. {$count} OS encontrada(s).")
            ->success()
            ->send();
    }

    protected function normalizePeriodDate(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m) === 1) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function createOrdem(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.create')) {
            return;
        }

        $this->redirect(OrdemServicoResource::getUrl('create'));
    }

    /**
     * @return array<int, string>
     */
    protected function erpListRecordClasses(Model $record): array
    {
        if (! $record instanceof OrdemServico || ! $this->osFaturada($record)) {
            return [];
        }

        $classes = ['erp-os-row--faturada'];

        if (! $record->possuiNfseAutorizada()) {
            $classes[] = 'erp-os-row--nfse-pendente';
        }

        if (! $record->possuiNfePecasEmitida()) {
            $classes[] = 'erp-os-row--nfe-pendente';
        }

        return $classes;
    }

    public function osSelecionadaPodeEmitirNfse(): bool
    {
        $ordem = $this->osSelecionadaFaturadaModel();

        return $ordem !== null && ! $ordem->possuiNfseAutorizada();
    }

    public function osSelecionadaPodeEmitirNfePecas(): bool
    {
        $ordem = $this->osSelecionadaFaturadaModel();

        return $ordem !== null && ! $ordem->possuiNfePecasEmitida();
    }

    private function osSelecionadaFaturadaModel(): ?OrdemServico
    {
        if (! $this->highlightedRecordId) {
            return null;
        }

        $ordem = OrdemServico::query()->find($this->highlightedRecordId);

        return $ordem !== null && $this->osFaturada($ordem) ? $ordem : null;
    }

    public function podeAlterarOsSelecionada(): bool
    {
        return ! $this->osSelecionadaFaturada();
    }

    public function osSelecionadaFaturada(): bool
    {
        if (! $this->highlightedRecordId) {
            return false;
        }

        $situacao = OrdemServico::query()->whereKey($this->highlightedRecordId)->value('situacao');

        return in_array($situacao, [OrdemServico::SITUACAO_FINALIZADA, OrdemServico::SITUACAO_ENTREGUE], true);
    }

    public function editOrdem(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        if (! $this->podeAlterarOsSelecionada()) {
            Notification::make()
                ->title('OS finalizada não pode ser alterada.')
                ->body('Use F8 | Reabrir para estornar o faturamento e alterar a OS.')
                ->warning()
                ->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.update')) {
            return;
        }

        $this->redirect(OrdemServicoResource::getUrl('edit', ['record' => $this->highlightedRecordId]));
    }

    public function cancelOrdem(): void
    {
        $id = $this->highlightedRecordIdOrNotify('delete');

        if (! $id) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.update')) {
            return;
        }

        $ordem = OrdemServico::query()->find($id);

        if (! $ordem) {
            return;
        }

        $motivo = app(OsReabrirService::class)->motivoBloqueioCancelamento($ordem);

        if ($motivo !== null) {
            Notification::make()->title($motivo)->warning()->send();

            return;
        }

        $this->abrirConfirmacaoOs(
            'cancelar',
            (int) $ordem->id,
            $this->osFaturada($ordem)
                ? 'Esta OS já foi faturada. Ao cancelar, serão estornados financeiro, caixa e estoque. Deseja continuar?'
                : 'Deseja realmente cancelar esta OS?',
        );
    }

    public function confirmarAcaoOs(): void
    {
        $acao = $this->osConfirmAcao;
        $id = $this->osConfirmId;
        $this->fecharConfirmacaoOs();

        if ($id === null || ! in_array($acao, ['cancelar', 'reabrir'], true)) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.update')) {
            return;
        }

        $acao === 'cancelar'
            ? $this->executarCancelamentoOrdem($id)
            : $this->executarReaberturaOrdem($id);
    }

    public function fecharConfirmacaoOs(): void
    {
        $this->osConfirmAcao = null;
        $this->osConfirmId = null;
        $this->osConfirmMensagem = '';
    }

    private function abrirConfirmacaoOs(string $acao, int $id, string $mensagem): void
    {
        $this->osConfirmAcao = $acao;
        $this->osConfirmId = $id;
        $this->osConfirmMensagem = $mensagem;
    }

    private function osFaturada(OrdemServico $ordem): bool
    {
        return in_array($ordem->situacao, [OrdemServico::SITUACAO_FINALIZADA, OrdemServico::SITUACAO_ENTREGUE], true);
    }

    private function executarCancelamentoOrdem(int $id): void
    {
        $ordem = OrdemServico::query()->find($id);

        if (! $ordem) {
            return;
        }

        $faturada = $this->osFaturada($ordem);

        try {
            app(OsReabrirService::class)->cancelar($ordem);
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->warning()->send();

            return;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível cancelar a OS.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->clearListSelection();
        $this->resetTable();

        $notification = Notification::make()->title('Ordem de serviço cancelada.')->success();

        if ($faturada) {
            $notification->body('Faturamento estornado: contas a receber, caixa e estoque das peças.');
        }

        $notification->send();
    }

    public function reabrirOrdem(): void
    {
        $id = $this->highlightedRecordIdOrNotify('reabrir');

        if (! $id) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.update')) {
            return;
        }

        $ordem = $this->ordemDaEmpresa($id);

        if (! $ordem) {
            Notification::make()->title('Ordem de serviço não encontrada.')->warning()->send();

            return;
        }

        $motivo = app(OsReabrirService::class)->motivoBloqueio($ordem);

        if ($motivo !== null) {
            Notification::make()->title($motivo)->warning()->send();

            return;
        }

        $this->abrirConfirmacaoOs(
            'reabrir',
            (int) $ordem->id,
            'Reabrir esta OS? Contas a receber, recebimentos, lançamentos no caixa e baixa de estoque do faturamento serão estornados.',
        );
    }

    private function ordemDaEmpresa(int $id): ?OrdemServico
    {
        $empresaId = ErpContext::currentEmpresaId();

        return OrdemServico::query()
            ->when($empresaId !== null, fn ($query) => $query->where(function ($query) use ($empresaId): void {
                $query->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
            }))
            ->find($id);
    }

    private function executarReaberturaOrdem(int $id): void
    {
        $ordem = $this->ordemDaEmpresa($id);

        if (! $ordem) {
            Notification::make()->title('Ordem de serviço não encontrada.')->warning()->send();

            return;
        }

        try {
            app(OsReabrirService::class)->reabrir($ordem);
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->warning()->send();

            return;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível reabrir a OS.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('OS reaberta.')
            ->body('Faturamento estornado. A OS pode ser alterada e faturada de novo.')
            ->success()
            ->send();
    }

    public function emitirNfseDaOs(): void
    {
        if (! $this->highlightedRecordId) {
            Notification::make()->title('Selecione uma ordem de serviço.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'nfse.access')) {
            return;
        }

        $ordem = $this->ordemSelecionadaParaFiscal();

        if ($ordem === null) {
            return;
        }

        $nfseEmitida = $ordem->nfseAutorizadaVinculada();

        if ($nfseEmitida !== null) {
            Notification::make()
                ->title('Esta OS já possui NFS-e emitida (nº '.trim((string) $nfseEmitida->numero_nfse).').')
                ->warning()
                ->send();

            return;
        }

        $motivo = NfseFromOrdemServico::motivoBloqueio($ordem);

        if ($motivo !== null) {
            Notification::make()->title($motivo)->warning()->send();

            return;
        }

        $this->redirect(OrdemServicoRetorno::anexar(NfsePage::getUrl().'?os='.$ordem->id), navigate: false);
    }

    public function emitirNfePecasDaOs(): void
    {
        if (! $this->highlightedRecordId) {
            Notification::make()->title('Selecione uma ordem de serviço.')->warning()->send();

            return;
        }

        $ordem = $this->ordemSelecionadaParaFiscal();

        if ($ordem === null) {
            return;
        }

        if (! $this->osFaturada($ordem)) {
            Notification::make()
                ->title('NF-e das peças só para OS finalizada.')
                ->body('Finalize a OS antes de emitir a NF-e das peças.')
                ->warning()
                ->send();

            return;
        }

        if ($ordem->possuiNfePecasEmitida()) {
            $numero = ltrim((string) $ordem->nfePecasEmitida?->numero, '0');

            Notification::make()
                ->title('Esta OS já possui NF-e das peças'.($numero !== '' ? ' (nº '.$numero.')' : '').'.')
                ->body('Para emitir outra, cancele a nota existente no módulo NF-e.')
                ->warning()
                ->send();

            return;
        }

        if (! NfeOrdemServicoService::osTemPecasParaNfe($ordem)) {
            Notification::make()->title('Esta OS não possui peças para emissão de NF-e.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'nfe.access')) {
            return;
        }

        $this->redirect(
            OrdemServicoRetorno::anexar(NfeResource::getUrl('index').'?ordem_servico_id='.$ordem->id),
            navigate: false,
        );
    }

    private function ordemSelecionadaParaFiscal(): ?OrdemServico
    {
        $empresaId = ErpContext::currentEmpresaId();
        $ordem = OrdemServico::query()
            ->with(['cliente', 'itens.product'])
            ->when($empresaId !== null, fn ($query) => $query->where(function ($query) use ($empresaId): void {
                $query->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
            }))
            ->find($this->highlightedRecordId);

        if ($ordem === null) {
            Notification::make()->title('Ordem de serviço não encontrada.')->warning()->send();
        }

        return $ordem;
    }
}
