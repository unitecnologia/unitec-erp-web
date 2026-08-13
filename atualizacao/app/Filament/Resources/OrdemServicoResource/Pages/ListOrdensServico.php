<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\OrdemServicoResource;
use App\Models\OrdemServico;
use App\Support\Erp\ErpAccess;
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

class ListOrdensServico extends ListRecords
{
    use InteractsWithErpListPage;

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

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Ordem de Serviço');

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
                'F6' => ['method' => 'modulePending', 'params' => ['Imprimir OS']],
            ],
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
            ->with(['cliente', 'atendente']);

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
            ]);
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

    public function editOrdem(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
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

        if ($ordem->situacao === OrdemServico::SITUACAO_CANCELADA) {
            Notification::make()->title('OS já está cancelada.')->warning()->send();

            return;
        }

        $ordem->update(['situacao' => OrdemServico::SITUACAO_CANCELADA]);

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()->title('Ordem de serviço cancelada.')->success()->send();
    }
}
