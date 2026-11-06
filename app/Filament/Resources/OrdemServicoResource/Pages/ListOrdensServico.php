<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Pages\NfsePage;
use App\Filament\Resources\NfeResource;
use App\Filament\Resources\NfceResource\Pages\Concerns\ManagesNfceClienteEmail;
use App\Filament\Resources\OrdemServicoResource;
use App\Filament\Resources\PersonResource;
use App\Livewire\Erp\OsListTable;
use App\Models\OrdemServico;
use App\Models\PdvVendaNfce;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfe\NfeClienteCadastro;
use App\Support\Erp\Nfe\NfeOrdemServicoService;
use App\Support\Erp\Nfce\NfceEmpresaEscopo;
use App\Support\Erp\Nfse\NfseFromOrdemServico;
use App\Support\Erp\Nfse\NfseTomadorCadastro;
use App\Support\Erp\Os\OrdemServicoRetorno;
use App\Support\Erp\Os\OsReabrirService;
use App\Support\Erp\OsListRowFormatter;
use App\Support\Erp\Queries\OsListQueryBuilder;
use DomainException;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
    use InteractsWithErpPermissions;
    use ManagesNfceClienteEmail;
    use Concerns\ManagesOrdemServicoEmailModal;
    use Concerns\ManagesOrdemServicoEspelhoModal;
    use Concerns\ManagesOrdemServicoNfcePecas;

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

    public bool $osClienteFiscalIncompletoOpen = false;

    /** @var list<string> */
    public array $osClienteFiscalIncompletoCampos = [];

    #[Locked]
    public string $osClienteFiscalIncompletoNome = '';

    #[Locked]
    public ?int $osClienteFiscalIncompletoId = null;

    #[Locked]
    public string $osClienteFiscalIncompletoDocumento = '';

    #[Locked]
    public string $osClienteFiscalIncompletoAtalho = '';

    /**
     * Meta da linha selecionada (classes/botões), preenchida no highlight — sem SQL no render.
     *
     * @var array{
     *     faturada?: bool,
     *     nfse_pendente?: bool,
     *     nfse_cliente_incompleto?: bool,
     *     nfe_pendente?: bool,
     *     nfe_cliente_incompleto?: bool,
     *     nfce_pendente?: bool
     * }|null
     */
    public ?array $selectedOsMeta = null;

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

        if ($this->highlightedRecordId) {
            $this->hydrateSelectedOsMetaFromId((int) $this->highlightedRecordId);
        }

        $this->dispatch(
            'erp-hydrate-os-dates',
            de: $this->periodoDe,
            ate: $this->periodoAte,
        );
    }

    /**
     * Grade isolada em OsListTable — evita init/remount da EmbeddedTable do Filament.
     */
    public function mountInteractsWithTable(): void
    {
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
            'blockingSelector' => '.erp-os-nfce-emitir-modal, .erp-nfe-fiscal-overlay.is-visible, .erp-nfe-danfe-email-modal, .erp-orc-email-modal, [data-erp-sefaz-progress].is-visible',
            'extraKeys' => [
                'F6' => ['method' => 'openPrintModal'],
                'F7' => ['method' => 'emitirNfseDaOs'],
                'F8' => ['method' => 'reabrirOrdem'],
                'F9' => ['method' => 'openSendModal'],
                'F10' => ['method' => 'emitirNfePecasDaOs'],
                'F11' => ['method' => 'emitirNfcePecasDaOs'],
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

    protected function listQueryBuilder(): OsListQueryBuilder
    {
        return new OsListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
        );
    }

    protected function buildListQuery(): Builder
    {
        return $this->listQueryBuilder()->buildForList();
    }

    #[Computed]
    public function filteredTotal(): float
    {
        return $this->listQueryBuilder()->sumFilteredTotal();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.ordens-servico.screen'),
                View::make('filament.components.erp.ordens-servico.table-host')
                    ->columnSpanFull(),
                View::make('filament.components.erp.ordens-servico.footer-total'),
                View::make('filament.components.erp.ordens-servico.action-bar'),
                View::make('filament.components.erp.ordens-servico.print-modal'),
                View::make('filament.components.erp.ordens-servico.print-docs-modal'),
                View::make('filament.components.erp.ordens-servico.send-modal'),
                View::make('filament.components.erp.ordens-servico.preview-overlay'),
                View::make('filament.components.erp.ordens-servico.email-modal'),
                View::make('filament.components.erp.ordens-servico.espelho-modal'),
                View::make('filament.components.erp.ordens-servico.confirm-modal'),
                View::make('filament.components.erp.ordens-servico.os-cliente-fiscal-incompleto-modal'),
                View::make('filament.components.erp.ordens-servico.os-nfce-emitir-modal'),
                View::make('filament.components.erp.ordens-servico.os-nfce-fiscal-overlays'),
                View::make('filament.components.erp.nfce.enviar-modal'),
            ]);
    }

    #[On('erp-os-open-espelho')]
    public function onErpOsOpenEspelho(int $ordemId): void
    {
        $this->openOrdemServicoEspelho($ordemId);
    }

    protected function empresaIdAtiva(): ?int
    {
        return NfceEmpresaEscopo::empresaIdAtiva();
    }

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

    public function openPrintModal(): void
    {
        if (! $this->highlightedRecordId) {
            Notification::make()->title('Selecione uma ordem de serviço.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.print')) {
            return;
        }

        // Impressão: docs fiscais — sem carregar itens/produtos.
        $ordem = $this->ordemSelecionadaParaFiscal([
            'cliente',
            'nfcePecasEmitida',
            'nfceAutorizada',
            'nfePecasEmitida',
            'nfeAutorizada',
            'nfseAutorizada',
            'nfseItemAutorizado.nfse',
        ]);

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

        $nfe = $ordem->relationLoaded('nfeAutorizada') ? $ordem->nfeAutorizada : $ordem->nfeAutorizada()->first();

        if ($nfe !== null && (filled($nfe->xml) || filled($nfe->chave))) {
            $documentos['nfe'] = [
                'rotulo' => 'NF-e nº '.(ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero),
                'url' => route('erp.reports.nfe-danfe', ['nfe' => $nfe->id]),
            ];
        }

        $nfce = $ordem->relationLoaded('nfceAutorizada') ? $ordem->nfceAutorizada : $ordem->nfceAutorizada()->first();

        if ($nfce !== null && ErpAccess::currentCan('nfce.access') && (int) ($nfce->pdv_venda_id ?? 0) > 0) {
            $documentos['nfce'] = [
                'rotulo' => 'NFC-e nº '.(ltrim((string) $nfce->numero, '0') ?: (string) $nfce->numero),
                'url' => route('erp.reports.nfce-cupom', ['venda' => $nfce->pdv_venda_id, 'auto' => 0, 'copias' => 1]),
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
        $this->pushOsListRefresh();
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
        $this->pushOsListRefresh(resetSort: true);
    }

    protected function notifyPeriodFilterResult(): void
    {
        $count = $this->listQueryBuilder()->countFiltered();

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
        $this->pushOsListRefresh(resetSort: true);
    }

    public function updatedLocalSearch(): void
    {
        $this->clearListSelection();
        $this->pushOsListRefresh(resetSort: true);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->pushOsListRefresh();
    }

    public function highlightRecord(int | string $recordId, mixed $meta = null): void
    {
        $normalized = $this->normalizeSelectedOsMeta($meta);
        $this->selectedOsMeta = $normalized;
        $this->highlightedRecordId = (int) $recordId;

        // Clique sem meta (fallback) ou seleção só por ID: hidrata 1× a partir da grade leve.
        if ($this->selectedOsMeta === null) {
            $this->hydrateSelectedOsMetaFromId((int) $recordId);
        }

        $this->skipRender();
    }

    public function updatedHighlightedRecordId(mixed $value): void
    {
        if ($value === null || $value === '' || (int) $value < 1) {
            $this->selectedOsMeta = null;

            return;
        }

        if ($this->selectedOsMeta === null) {
            $this->hydrateSelectedOsMetaFromId((int) $value);
        }
    }

    protected function clearListSelection(): void
    {
        $this->highlightedRecordId = null;
        $this->selectedOsMeta = null;
    }

    public function refreshTable(): void
    {
        $this->pushOsListRefresh();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    /**
     * Compatível com traits (e-mail / NFC-e) que ainda chamam resetTable().
     */
    public function resetTable(): void
    {
        $this->pushOsListRefresh();
    }

    protected function pushOsListRefresh(bool $resetSort = false): void
    {
        $total = $this->listQueryBuilder()->sumFilteredTotal();

        $this->dispatch(
            'erp-os-list-refresh',
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
            perPage: (int) ($this->tableRecordsPerPage ?? 50),
            resetSort: $resetSort,
            highlightedRecordId: $this->highlightedRecordId,
        )->to(OsListTable::class);

        $this->skipRender();

        $resetButtons = $this->highlightedRecordId
            ? ''
            : 'document.querySelectorAll(".erp-os-page [data-erp-enable-on-row]").forEach((b) => { b.disabled = true; });'
                .'document.querySelectorAll(".erp-os-page [data-erp-disable-on-row]").forEach((b) => { b.disabled = false; });'
                .'document.querySelectorAll(".erp-os-page [data-erp-warn-on-row]").forEach((b) => { b.classList.remove("is-warning"); });';

        $this->js(sprintf(
            '(() => { const el = document.querySelector(".erp-os-page .erp-orcamentos__total-value"); if (el) el.textContent = %s; %s })()',
            json_encode('R$ '.number_format($total, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            $resetButtons,
        ));
    }

    public function createOrdem(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.create')) {
            return;
        }

        $this->redirectErpAdmin(OrdemServicoResource::getUrl('create'));
    }

    public function osSelecionadaPodeEmitirNfse(): bool
    {
        return (bool) ($this->selectedOsMeta['nfse_pendente'] ?? false);
    }

    /**
     * Amarelo no F7: usa meta da linha selecionada (sem SQL no render).
     */
    public function osSelecionadaNfseClienteIncompleto(): bool
    {
        return (bool) ($this->selectedOsMeta['nfse_cliente_incompleto'] ?? false);
    }

    public function osSelecionadaPodeEmitirNfePecas(): bool
    {
        return (bool) ($this->selectedOsMeta['nfe_pendente'] ?? false);
    }

    /**
     * Amarelo no F10: meta da linha selecionada (sem SQL no render).
     */
    public function osSelecionadaNfeClienteIncompleto(): bool
    {
        return (bool) ($this->selectedOsMeta['nfe_cliente_incompleto'] ?? false);
    }

    public function osSelecionadaPodeEmitirNfcePecas(): bool
    {
        return (bool) ($this->selectedOsMeta['nfce_pendente'] ?? false);
    }

    /**
     * Indica peças pelos totais já presentes na OS — sem carregar itens.
     */
    protected function osListaIndicaPecas(OrdemServico $ordem): bool
    {
        return (float) ($ordem->subtotal_pecas ?? 0) > 0.0001
            || (float) ($ordem->total_produtos ?? 0) > 0.0001;
    }

    public function podeAlterarOsSelecionada(): bool
    {
        return ! $this->osSelecionadaEstaFaturadaParaAcao();
    }

    /**
     * Estado da barra de ações — só meta da seleção (sem SQL).
     */
    public function osSelecionadaFaturada(): bool
    {
        return (bool) ($this->selectedOsMeta['faturada'] ?? false);
    }

    /**
     * Checagem no clique (F3): usa meta se válida; senão 1 query pontual.
     */
    protected function osSelecionadaEstaFaturadaParaAcao(): bool
    {
        if ($this->selectedOsMeta !== null) {
            return (bool) ($this->selectedOsMeta['faturada'] ?? false);
        }

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

        $this->redirectErpAdmin(OrdemServicoResource::getUrl('edit', ['record' => $this->highlightedRecordId]));
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

        $ordem = $this->ordemDaEmpresa($id);

        if (! $ordem) {
            Notification::make()->title('Ordem de serviço não encontrada.')->warning()->send();

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

    protected function osFaturada(OrdemServico $ordem): bool
    {
        return in_array($ordem->situacao, [OrdemServico::SITUACAO_FINALIZADA, OrdemServico::SITUACAO_ENTREGUE], true);
    }

    private function executarCancelamentoOrdem(int $id): void
    {
        $ordem = $this->ordemDaEmpresa($id);

        if (! $ordem) {
            Notification::make()->title('Ordem de serviço não encontrada.')->warning()->send();

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
            ->daEmpresaAtiva($empresaId)
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

        $faltando = NfseTomadorCadastro::camposFaltantesDoCliente($ordem->cliente);

        if ($faltando !== []) {
            $this->abrirOsClienteFiscalIncompleto(
                'NFS-e',
                'F7',
                $faltando,
                trim((string) ($ordem->cliente?->nome_razao ?? $ordem->nome ?? '—')),
                $ordem->cliente_id !== null ? (int) $ordem->cliente_id : null,
            );

            return;
        }

        $this->redirect(OrdemServicoRetorno::anexar(NfsePage::getUrl().'?os='.$ordem->id), navigate: false);
    }

    public function fecharOsClienteFiscalIncompleto(): void
    {
        $this->osClienteFiscalIncompletoOpen = false;
        $this->osClienteFiscalIncompletoCampos = [];
        $this->osClienteFiscalIncompletoNome = '';
        $this->osClienteFiscalIncompletoId = null;
        $this->osClienteFiscalIncompletoDocumento = '';
        $this->osClienteFiscalIncompletoAtalho = '';
    }

    public function abrirCadastroClienteFiscalIncompleto(): void
    {
        $clienteId = $this->osClienteFiscalIncompletoId;

        $this->fecharOsClienteFiscalIncompleto();

        if ($clienteId === null || $clienteId < 1) {
            Notification::make()->title('Cliente da OS não encontrado para correção.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'pessoas.update')) {
            return;
        }

        $this->redirect(PersonResource::getUrl('edit', ['record' => $clienteId]), navigate: false);
    }

    /**
     * @param  list<string>  $campos
     */
    private function abrirOsClienteFiscalIncompleto(
        string $documento,
        string $atalho,
        array $campos,
        string $nome,
        ?int $clienteId,
    ): void {
        $this->osClienteFiscalIncompletoOpen = true;
        $this->osClienteFiscalIncompletoCampos = $campos;
        $this->osClienteFiscalIncompletoNome = $nome;
        $this->osClienteFiscalIncompletoId = $clienteId;
        $this->osClienteFiscalIncompletoDocumento = $documento;
        $this->osClienteFiscalIncompletoAtalho = $atalho;
    }

    public function emitirNfePecasDaOs(): void
    {
        if (! $this->highlightedRecordId) {
            Notification::make()->title('Selecione uma ordem de serviço.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'nfe.access')) {
            return;
        }

        $ordem = $this->ordemSelecionadaParaFiscal();

        if ($ordem === null) {
            return;
        }

        if ($ordem->possuiNfePecasEmitida()) {
            $numero = ltrim((string) $ordem->nfePecasEmitida?->numero, '0');

            Notification::make()
                ->title('Esta OS já possui NF-e das peças'.($numero !== '' ? ' (nº '.$numero.')' : '').'.')
                ->warning()
                ->send();

            return;
        }

        if ($ordem->possuiNfcePecasDocumentada()) {
            $numero = ltrim((string) ($ordem->nfceAutorizada?->numero ?? $ordem->nfcePecasEmitida?->numero ?? ''), '0');

            Notification::make()
                ->title('Esta OS já possui NFC-e das peças'.($numero !== '' ? ' (nº '.$numero.')' : '').'. Emita NF-e somente se cancelar a NFC-e.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->osFaturada($ordem)) {
            Notification::make()
                ->title('NF-e das peças só para OS finalizada.')
                ->warning()
                ->send();

            return;
        }

        // Sem peças: indisponível (botão desabilitado na grade). Sem toast de cadastro.
        if (! NfeOrdemServicoService::osTemPecasParaNfe($ordem)) {
            return;
        }

        $faltando = NfeClienteCadastro::camposFaltantesDoCliente($ordem->cliente);

        if ($faltando !== []) {
            $this->abrirOsClienteFiscalIncompleto(
                'NF-e Peças',
                'F10',
                $faltando,
                trim((string) ($ordem->cliente?->nome_razao ?? $ordem->nome ?? '—')),
                $ordem->cliente_id !== null ? (int) $ordem->cliente_id : null,
            );

            return;
        }

        $this->redirect(
            OrdemServicoRetorno::anexar(NfeResource::getUrl('index').'?ordem_servico_id='.$ordem->id),
            navigate: false,
        );
    }

    /**
     * @param  list<string>  $with
     */
    protected function ordemSelecionadaParaFiscal(array $with = []): ?OrdemServico
    {
        $empresaId = ErpContext::currentEmpresaId();
        $relations = $with !== [] ? $with : $this->ordemFiscalWithItens();

        $ordem = OrdemServico::query()
            ->with($relations)
            ->daEmpresaAtiva($empresaId)
            ->find($this->highlightedRecordId);

        if ($ordem === null) {
            Notification::make()->title('Ordem de serviço não encontrada.')->warning()->send();
        }

        return $ordem;
    }

    /**
     * @return list<string>
     */
    protected function ordemFiscalWithItens(): array
    {
        return [
            'cliente',
            'itens.product',
            'nfcePecasEmitida',
            'nfceAutorizada',
            'nfePecasEmitida',
            'nfeAutorizada',
            'nfseAutorizada',
            'nfseItemAutorizado.nfse',
        ];
    }

    /**
     * @param  mixed  $meta
     * @return array{
     *     faturada: bool,
     *     nfse_pendente: bool,
     *     nfse_cliente_incompleto: bool,
     *     nfe_pendente: bool,
     *     nfe_cliente_incompleto: bool,
     *     nfce_pendente: bool
     * }|null
     */
    protected function normalizeSelectedOsMeta(mixed $meta): ?array
    {
        if (! is_array($meta)) {
            return null;
        }

        return [
            'faturada' => (bool) ($meta['faturada'] ?? false),
            'nfse_pendente' => (bool) ($meta['nfse_pendente'] ?? false),
            'nfse_cliente_incompleto' => (bool) ($meta['nfse_cliente_incompleto'] ?? false),
            'nfe_pendente' => (bool) ($meta['nfe_pendente'] ?? false),
            'nfe_cliente_incompleto' => (bool) ($meta['nfe_cliente_incompleto'] ?? false),
            'nfce_pendente' => (bool) ($meta['nfce_pendente'] ?? false),
        ];
    }

    protected function hydrateSelectedOsMetaFromId(int $id): void
    {
        $ordem = (new OsListQueryBuilder)->buildForList()->whereKey($id)->first();

        if (! $ordem instanceof OrdemServico) {
            $this->selectedOsMeta = null;

            return;
        }

        $this->selectedOsMeta = app(OsListRowFormatter::class)->selectionMeta($ordem);
    }
}
