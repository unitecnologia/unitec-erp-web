<?php

namespace App\Filament\Resources\DevolucaoVendaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\DevolucaoVendaResource;
use App\Models\DevolucaoVenda;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
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

class ListDevolucoesVenda extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = DevolucaoVendaResource::class;

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

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Devolução de Venda');

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
            'erp-hydrate-devvenda-dates',
            de: $this->periodoDe,
            ate: $this->periodoAte,
        );
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-orcamentos-page erp-devolucao-venda-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma devolução de venda';
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
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(DevolucaoVendaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['cliente', 'vendedor']);

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

        $column = in_array($this->searchColumn, ['numero', 'cliente', 'venda', 'vendedor'], true)
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
                $q->where('cliente_nome', 'like', $like)
                    ->orWhereHas('cliente', fn (Builder $c): Builder => $c->where('nome_razao', 'like', $like));
            }),
            'venda' => $query->where('venda_numero', 'like', $like),
            'vendedor' => $query->whereHas('vendedor', fn (Builder $v): Builder => $v->where('nome', 'like', $like)),
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
                View::make('filament.components.erp.devolucoes-venda.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.devolucoes-venda.footer-total'),
                View::make('filament.components.erp.devolucoes-venda.action-bar'),
            ]);
    }

    public function setStatusFilter(string $filter): void
    {
        $allowed = [
            'todos',
            DevolucaoVenda::SITUACAO_ABERTA,
            DevolucaoVenda::SITUACAO_FINALIZADA,
            DevolucaoVenda::SITUACAO_CANCELADA,
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
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'devolucoes_venda.create')) {
            return;
        }

        $this->redirect(DevolucaoVendaResource::getUrl('create'));
    }

    public function editDevolucao(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'devolucoes_venda.update')) {
            return;
        }

        $this->redirect(DevolucaoVendaResource::getUrl('edit', ['record' => $this->highlightedRecordId]));
    }

    public function cancelDevolucao(): void
    {
        $id = $this->highlightedRecordIdOrNotify('delete');

        if (! $id) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'devolucoes_venda.update')) {
            return;
        }

        $record = DevolucaoVenda::query()->find($id);

        if (! $record) {
            return;
        }

        if ($record->situacao === DevolucaoVenda::SITUACAO_CANCELADA) {
            Notification::make()->title('Devolução já está cancelada.')->warning()->send();

            return;
        }

        if ($record->situacao === DevolucaoVenda::SITUACAO_FINALIZADA) {
            Notification::make()
                ->title('Não é possível cancelar uma devolução finalizada.')
                ->body('Estoque e financeiro já foram aplicados. Use um estorno específico se necessário.')
                ->warning()
                ->send();

            return;
        }

        $record->update(['situacao' => DevolucaoVenda::SITUACAO_CANCELADA]);

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()->title('Devolução cancelada.')->success()->send();
    }
}
