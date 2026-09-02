<?php

namespace App\Filament\Resources\OrcamentoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\OrcamentoResource\Pages\Concerns\ManagesOrcamentoEmailModal;
use App\Filament\Resources\OrcamentoResource\Pages\Concerns\ManagesOrcamentoViewModal;
use App\Livewire\Erp\OrcamentoListTable;
use App\Models\Orcamento;
use App\Support\Erp\ErpDataSyncVersion;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Queries\OrcamentoListQueryBuilder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class ListOrcamentos extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesOrcamentoEmailModal;
    use ManagesOrcamentoViewModal;

    protected static string $resource = OrcamentoResource::class;

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

    public bool $printModalOpen = false;

    public bool $previewOverlayOpen = false;

    public ?string $previewOverlayUrl = null;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Orçamentos');

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

        $this->resetOrcamentoListUiState();

        $this->dispatch(
            'erp-hydrate-orcamentos-dates',
            de: $this->periodoDe,
            ate: $this->periodoAte,
        );
    }

    public function mountInteractsWithTable(): void
    {
    }

    public function erpListSyncPollEnabled(): bool
    {
        if (! config('unitec.erp_list_sync_poll_enabled', true)) {
            return false;
        }

        return $this->erpListSyncChannel() !== null;
    }

    protected function resetOrcamentoListUiState(): void
    {
        $this->printModalOpen = false;
        $this->previewOverlayOpen = false;
        $this->previewOverlayUrl = null;
        $this->viewModalOpen = false;
        $this->emailModalOpen = false;
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-orcamentos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um orçamento';
    }

    protected function erpListSyncChannel(): ?string
    {
        return ErpDataSyncVersion::CHANNEL_QUOTES;
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-orcamentos__input',
            'create' => 'createOrcamento',
            'edit' => 'editOrcamento',
            'extraKeys' => [
                'F4' => ['method' => 'cancelOrcamento'],
                'F6' => ['method' => 'openPrintModal'],
                'F9' => ['method' => 'openEmailModal'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(OrcamentoResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function listQueryBuilder(): OrcamentoListQueryBuilder
    {
        return new OrcamentoListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
        );
    }

    protected function buildListQuery(): Builder
    {
        return $this->listQueryBuilder()
            ->buildForList();
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
                View::make('filament.components.erp.orcamentos.screen'),
                View::make('filament.components.erp.orcamentos.table-host')
                    ->columnSpanFull(),
                View::make('filament.components.erp.orcamentos.footer-total'),
                View::make('filament.components.erp.orcamentos.action-bar'),
                View::make('filament.components.erp.orcamentos.print-modal'),
                View::make('filament.components.erp.orcamentos.email-modal'),
                View::make('filament.components.erp.orcamentos.preview-overlay'),
                View::make('filament.components.erp.orcamentos.view-modal'),
            ]);
    }

    #[On('erp-orcamento-open-view')]
    public function onErpOrcamentoOpenView(int $orcamentoId): void
    {
        $this->openOrcamentoView($orcamentoId);
    }

    public function setStatusFilter(string $filter): void
    {
        $allowed = ['todos', Orcamento::STATUS_ABERTO, Orcamento::STATUS_FECHADO, Orcamento::STATUS_CANCELADO, Orcamento::STATUS_IMPORTADO];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->statusFilter = $filter;
        $this->clearListSelection();
        $this->pushOrcamentoListRefresh();
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

    public function applyPeriodoFilter(string $de = '', string $ate = ''): void
    {
        if ($de !== '') {
            $this->periodoDe = $this->normalizePeriodDate($de) ?? '';
        }

        if ($ate !== '') {
            $this->periodoAte = $this->normalizePeriodDate($ate) ?? '';
        }

        $this->applyPeriodFilter();
    }

    protected function syncAppliedPeriodFilter(): void
    {
        $this->periodoDe = $this->normalizePeriodDate($this->periodoDe) ?? '';
        $this->periodoAte = $this->normalizePeriodDate($this->periodoAte) ?? '';

        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;

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

        $this->dispatch(
            'erp-hydrate-orcamentos-dates',
            de: $this->periodoDe,
            ate: $this->periodoAte,
        );

        $this->pushOrcamentoListRefresh(resetSort: true);
    }

    protected function notifyPeriodFilterResult(): void
    {
        $count = $this->listQueryBuilder()->countFiltered();

        if ($count === 0) {
            Notification::make()
                ->title('Nenhum orçamento neste período.')
                ->body('Não há registros entre '
                    . ($this->periodoDeApplied ? \Illuminate\Support\Carbon::parse($this->periodoDeApplied)->format('d/m/Y') : '—')
                    . ' e '
                    . ($this->periodoAteApplied ? \Illuminate\Support\Carbon::parse($this->periodoAteApplied)->format('d/m/Y') : '—')
                    . '.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title("Período filtrado. {$count} orçamento(s) encontrado(s).")
            ->success()
            ->send();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'cliente';
        $this->clearListSelection();
        $this->pushOrcamentoListRefresh(resetSort: true);
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->pushOrcamentoListRefresh(resetSort: true);
    }

    public function updatedLocalSearch(): void
    {
        $this->clearListSelection();
        $this->pushOrcamentoListRefresh(resetSort: true);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->pushOrcamentoListRefresh();
    }

    public function search(): void
    {
        $this->clearListSelection();
        $this->pushOrcamentoListRefresh(resetSort: true);
    }

    public function createOrcamento(): void
    {
        $this->redirect(OrcamentoResource::getUrl('create'));
    }

    public function editOrcamento(int | string | null $recordId = null): void
    {
        $resolvedId = filled($recordId) ? (int) $recordId : $this->highlightedRecordId;

        if (! $resolvedId) {
            $this->highlightedRecordIdOrNotify('edit');

            return;
        }

        $this->redirect(OrcamentoResource::getUrl('edit', ['record' => $resolvedId]));
    }

    public function cancelOrcamento(): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('cancel');

        if (! $recordId) {
            return;
        }

        $orcamento = Orcamento::query()->find($recordId);

        if (! $orcamento) {
            return;
        }

        if ($orcamento->status === Orcamento::STATUS_CANCELADO) {
            Notification::make()
                ->title('Orçamento já está cancelado.')
                ->warning()
                ->send();

            return;
        }

        $orcamento->update(['status' => Orcamento::STATUS_CANCELADO]);

        $this->clearListSelection();
        $this->pushOrcamentoListRefresh();

        Notification::make()
            ->title('Orçamento cancelado.')
            ->success()
            ->send();
    }

    public function openPrintModal(): void
    {
        if (! $this->highlightedRecordIdOrNotify('print')) {
            return;
        }

        $this->printModalOpen = true;
    }

    public function closePrintModal(): void
    {
        $this->printModalOpen = false;
    }

    public function visualizarOrcamentoImpressao(): void
    {
        if (! $this->highlightedRecordId) {
            return;
        }

        $this->closePrintModal();
        $this->previewOverlayUrl = route('erp.reports.orcamento', [
            'orcamento' => $this->highlightedRecordId,
            'embed' => 1,
        ]);
        $this->previewOverlayOpen = true;
    }

    public function imprimirBobinaOrcamento(): void
    {
        if (! $this->highlightedRecordId) {
            return;
        }

        $this->closePrintModal();
        $this->previewOverlayUrl = route('erp.reports.orcamento', [
            'orcamento' => $this->highlightedRecordId,
            'bobina' => 1,
            'embed' => 1,
        ]);
        $this->previewOverlayOpen = true;
    }

    #[On('close-orcamento-preview')]
    public function closePreviewOverlay(): void
    {
        $this->previewOverlayOpen = false;
        $this->previewOverlayUrl = null;
    }

    public function pollErpListSync(): void
    {
        $channel = $this->erpListSyncChannel();

        if ($channel === null) {
            $this->skipRender();

            return;
        }

        $current = ErpDataSyncVersion::current($channel);

        if ($this->erpListSyncVersion === null) {
            $this->erpListSyncVersion = $current;
            $this->skipRender();

            return;
        }

        if (hash_equals($this->erpListSyncVersion, $current)) {
            $this->skipRender();

            return;
        }

        $this->erpListSyncVersion = $current;
        $this->pushOrcamentoListRefresh();
    }

    public function refreshTable(): void
    {
        $this->syncErpListSyncVersionFromStore();
        $this->pushOrcamentoListRefresh();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    protected function pushOrcamentoListRefresh(bool $resetSort = false): void
    {
        $total = $this->listQueryBuilder()->sumFilteredTotal();

        $this->dispatch(
            'erp-orcamento-list-refresh',
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
            perPage: (int) ($this->tableRecordsPerPage ?? 50),
            resetSort: $resetSort,
        )->to(OrcamentoListTable::class);

        $this->skipRender();

        $this->js(sprintf(
            '(() => { const el = document.querySelector(".erp-orcamentos__total-value"); if (el) el.textContent = %s; })()',
            json_encode('R$ '.number_format($total, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
        ));
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'cancel' => 'um orçamento para cancelar',
            'print' => 'um orçamento para imprimir',
            'enviar' => 'um orçamento para enviar',
            default => $this->defaultErpListSelectPrompt($action),
        };
    }

    protected function normalizePeriodDate(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $this->isValidPeriodIsoDate($value) ? $value : null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches) === 1) {
            $iso = sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);

            return $this->isValidPeriodIsoDate($iso) ? $iso : null;
        }

        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $matches) === 1) {
            $iso = sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);

            return $this->isValidPeriodIsoDate($iso) ? $iso : null;
        }

        return null;
    }

    protected function isValidPeriodIsoDate(string $iso): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
