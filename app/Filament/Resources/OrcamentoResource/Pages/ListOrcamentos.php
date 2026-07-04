<?php

namespace App\Filament\Resources\OrcamentoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\OrcamentoResource\Pages\Concerns\ManagesOrcamentoEmailModal;
use App\Filament\Resources\OrcamentoResource\Pages\Concerns\ManagesOrcamentoViewModal;
use App\Filament\Resources\OrcamentoResource\Pages\Concerns\ManagesOrcamentoWhatsAppModal;
use App\Models\Orcamento;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
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
    use ManagesOrcamentoWhatsAppModal;

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

    protected function resetOrcamentoListUiState(): void
    {
        $this->printModalOpen = false;
        $this->previewOverlayOpen = false;
        $this->previewOverlayUrl = null;
        $this->viewModalOpen = false;
        $this->emailModalOpen = false;
        $this->whatsAppModalOpen = false;
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-orcamentos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um orçamento';
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
                'F10' => ['method' => 'openWhatsAppModal'],
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

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->visivelNaListaOrcamentos()
            ->with(['cliente', 'vendedor']);

        if ($this->statusFilter !== 'todos') {
            $query->where('status', $this->statusFilter);
        }

        if ($de = $this->normalizePeriodDate($this->periodoDeApplied)) {
            $query->whereDate('data', '>=', $de);
        }

        if ($ate = $this->normalizePeriodDate($this->periodoAteApplied)) {
            $query->whereDate('data', '<=', $ate);
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

        $column = in_array($this->searchColumn, ['numero', 'cliente', 'vendedor', 'cidade', 'uf'], true)
            ? $this->searchColumn
            : 'cliente';

        $like = '%' . $term . '%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $like),
            'cliente' => $query->whereHas('cliente', fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $like)),
            'vendedor' => $query->whereHas('vendedor', fn (Builder $vendedorQuery): Builder => $vendedorQuery->where('nome', 'like', $like)),
            'cidade' => $query->whereHas('cliente', fn (Builder $clienteQuery): Builder => $clienteQuery->where('cidade_nome', 'like', $like)),
            'uf' => $query->whereHas('cliente', fn (Builder $clienteQuery): Builder => $clienteQuery->where('uf', 'like', $like)),
        };
    }

    #[Computed]
    public function filteredTotal(): float
    {
        return (float) $this->buildListQuery()->sum('total');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.orcamentos.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.orcamentos.footer-total'),
                View::make('filament.components.erp.orcamentos.action-bar'),
                View::make('filament.components.erp.orcamentos.print-modal'),
                View::make('filament.components.erp.orcamentos.email-modal'),
                View::make('filament.components.erp.orcamentos.whatsapp-modal'),
                View::make('filament.components.erp.orcamentos.preview-overlay'),
                View::make('filament.components.erp.orcamentos.view-modal'),
            ]);
    }

    public function setStatusFilter(string $filter): void
    {
        $allowed = ['todos', Orcamento::STATUS_ABERTO, Orcamento::STATUS_FECHADO, Orcamento::STATUS_CANCELADO, Orcamento::STATUS_IMPORTADO];

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

    public function applyPeriodoFilter(string $de = '', string $ate = ''): void
    {
        if ($de !== '') {
            $this->periodoDe = $this->normalizePeriodDate($de) ?? trim($de);
        }

        if ($ate !== '') {
            $this->periodoAte = $this->normalizePeriodDate($ate) ?? trim($ate);
        }

        $this->applyPeriodFilter();
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

    protected function normalizePeriodDate(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches) === 1) {
            return sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'cliente';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function createOrcamento(): void
    {
        $this->redirect(OrcamentoResource::getUrl('create'));
    }

    public function editOrcamento(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $this->redirect(OrcamentoResource::getUrl('edit', ['record' => $this->highlightedRecordId]));
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
        $this->resetTable();

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

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'cancel' => 'um orçamento para cancelar',
            'print' => 'um orçamento para imprimir',
            'email' => 'um orçamento para enviar e-mail',
            'whatsapp' => 'um orçamento para enviar WhatsApp',
            default => $this->defaultErpListSelectPrompt($action),
        };
    }
}
