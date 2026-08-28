<?php

namespace App\Filament\Resources\DevolucaoCompraResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\DevolucaoCompraResource;
use App\Filament\Resources\DevolucaoCompraResource\Pages\Concerns\ManagesDevolucaoCompraModal;
use App\Filament\Resources\NfeResource;
use App\Models\DevolucaoCompra;
use App\Support\Erp\Compras\ReabrirDevolucaoCompraService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Nfe\NfeDevolucaoCompraService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListDevolucoesCompra extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesDevolucaoCompraModal;

    protected static string $resource = DevolucaoCompraResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'fornecedor';

    #[Url(as: 'status')]
    public string $statusFilter = 'todos';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public bool $reabrirConfirmOpen = false;

    public ?int $reabrirConfirmId = null;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Devolução de Compra');

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
            'erp-hydrate-devcompra-dates',
            de: $this->periodoDe,
            ate: $this->periodoAte,
        );
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-devolucao-compra-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma devolução de compra';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-orcamentos__input',
            'create' => 'createDevolucao',
            'edit' => 'editDevolucao',
            'delete' => 'cancelDevolucao',
            'extraKeys' => [
                'F6' => ['method' => 'modulePending', 'params' => ['Imprimir devolução']],
                'F7' => ['method' => 'emitirNfeDevolucaoCompra'],
                'F8' => ['method' => 'reabrirDevolucao'],
            ],
        ];
    }

    public function emitirNfeDevolucaoCompra(): void
    {
        $id = $this->highlightedRecordIdOrNotify('emitir NF-e');

        if (! $id) {
            return;
        }

        if (! ErpAccess::currentCan('nfe.access')) {
            Notification::make()
                ->title('Sem permissão para acessar NF-e.')
                ->warning()
                ->send();

            return;
        }

        if (! ErpAccess::currentCan('nfe.emit')) {
            Notification::make()
                ->title('Sem permissão para emitir NF-e.')
                ->warning()
                ->send();

            return;
        }

        $empresaId = ErpContext::currentEmpresaId();

        $devolucao = DevolucaoCompra::query()
            ->with(['compra', 'fornecedor', 'itens'])
            ->when($empresaId, fn (Builder $query, int $eid) => $query->where('empresa_id', $eid))
            ->find($id);

        if (! $devolucao) {
            Notification::make()
                ->title('Devolução de compra não encontrada.')
                ->warning()
                ->send();

            return;
        }

        try {
            app(NfeDevolucaoCompraService::class)->validar($devolucao);
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Não foi possível emitir a NF-e de devolução.')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $this->redirect(NfeResource::getUrl('index').'?devolucao_compra_id='.$devolucao->id);
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(DevolucaoCompraResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['fornecedor']);

        $empresaId = ErpContext::currentEmpresaId();
        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        if ($this->statusFilter !== 'todos') {
            $query->where('situacao', $this->statusFilter);
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

        $column = in_array($this->searchColumn, ['numero', 'fornecedor', 'compra'], true)
            ? $this->searchColumn
            : 'fornecedor';

        $like = '%'.$term.'%';

        match ($column) {
            'numero' => $query->where(function (Builder $q) use ($like, $term): void {
                $q->where('numero', 'like', $like);
                if (is_numeric($term)) {
                    $q->orWhere('codigo_legado', (int) $term);
                }
            }),
            'fornecedor' => $query->where(function (Builder $q) use ($like): void {
                $q->where('fornecedor_nome', 'like', $like)
                    ->orWhereHas('fornecedor', fn (Builder $c): Builder => $c->where('nome_razao', 'like', $like));
            }),
            'compra' => $query->where('compra_numero', 'like', $like),
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
                View::make('filament.components.erp.devolucoes-compra.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.devolucoes-compra.footer-total'),
                View::make('filament.components.erp.devolucoes-compra.action-bar'),
                View::make('filament.components.erp.devolucoes-compra.lancamento-modal'),
                View::make('filament.components.erp.devolucoes-compra.reabrir-confirm-modal'),
            ]);
    }

    public function setStatusFilter(string $filter): void
    {
        $allowed = [
            'todos',
            DevolucaoCompra::SITUACAO_ABERTA,
            DevolucaoCompra::SITUACAO_FINALIZADA,
            DevolucaoCompra::SITUACAO_CANCELADA,
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
                ->title('Nenhuma devolução neste período.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title("Período filtrado. {$count} devolução(ões) encontrada(s).")
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

    public function createDevolucao(): void
    {
        $this->openCreateModal();
    }

    public function editDevolucao(): void
    {
        $id = $this->highlightedRecordIdOrNotify('edit');

        if (! $id) {
            return;
        }

        $record = DevolucaoCompra::query()->find($id);

        if (! $record) {
            return;
        }

        if (! $record->isEditable()) {
            Notification::make()
                ->title('Somente devolução aberta pode ser alterada.')
                ->warning()
                ->send();

            return;
        }

        $this->openEditModal((int) $id);
    }

    public function cancelDevolucao(): void
    {
        $id = $this->highlightedRecordIdOrNotify('delete');

        if (! $id) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'devolucoes_compra.update')) {
            return;
        }

        $record = DevolucaoCompra::query()->find($id);

        if (! $record) {
            return;
        }

        if ($record->situacao === DevolucaoCompra::SITUACAO_CANCELADA) {
            Notification::make()->title('Devolução já está cancelada.')->warning()->send();

            return;
        }

        if ($record->situacao === DevolucaoCompra::SITUACAO_FINALIZADA) {
            Notification::make()
                ->title('Não é possível cancelar uma devolução finalizada.')
                ->body('Estoque já foi baixado. Use Reabrir (F8) para estornar e voltar à situação Aberta.')
                ->warning()
                ->send();

            return;
        }

        $record->update(['situacao' => DevolucaoCompra::SITUACAO_CANCELADA]);

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()->title('Devolução cancelada.')->success()->send();
    }

    public function reabrirDevolucao(): void
    {
        $id = $this->highlightedRecordIdOrNotify('reabrir');

        if (! $id) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'devolucoes_compra.update')) {
            return;
        }

        $empresaId = ErpContext::currentEmpresaId();

        $record = DevolucaoCompra::query()
            ->when($empresaId, fn (Builder $query, int $eid) => $query->where('empresa_id', $eid))
            ->find($id);

        if (! $record) {
            Notification::make()
                ->title('Devolução de compra não encontrada.')
                ->warning()
                ->send();

            return;
        }

        if ($record->situacao === DevolucaoCompra::SITUACAO_ABERTA) {
            Notification::make()
                ->title('Devolução já está aberta.')
                ->warning()
                ->send();

            return;
        }

        if ($record->situacao === DevolucaoCompra::SITUACAO_CANCELADA) {
            Notification::make()
                ->title('Devolução cancelada não pode ser reaberta.')
                ->warning()
                ->send();

            return;
        }

        if ($record->situacao !== DevolucaoCompra::SITUACAO_FINALIZADA) {
            Notification::make()
                ->title('Só é possível reabrir devolução finalizada.')
                ->warning()
                ->send();

            return;
        }

        $this->reabrirConfirmId = (int) $record->id;
        $this->reabrirConfirmOpen = true;
    }

    public function cancelReabrirDevolucao(): void
    {
        $this->reabrirConfirmOpen = false;
        $this->reabrirConfirmId = null;
    }

    public function confirmReabrirDevolucao(): void
    {
        $id = $this->reabrirConfirmId;
        $this->cancelReabrirDevolucao();

        if (! $id) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'devolucoes_compra.update')) {
            return;
        }

        $empresaId = ErpContext::currentEmpresaId();

        $record = DevolucaoCompra::query()
            ->when($empresaId, fn (Builder $query, int $eid) => $query->where('empresa_id', $eid))
            ->find($id);

        if (! $record) {
            Notification::make()
                ->title('Devolução de compra não encontrada.')
                ->warning()
                ->send();

            return;
        }

        try {
            (new ReabrirDevolucaoCompraService())->reabrir($record);
        } catch (DomainException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->warning()
                ->send();

            return;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível reabrir a devolução.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Devolução reaberta')
            ->body('O estoque baixado na finalização foi estornado. Use Alterar (F3) para editar.')
            ->success()
            ->send();
    }
}
