<?php

namespace App\Filament\Resources\RhExameResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhExameResource;
use App\Filament\Resources\RhExameResource\Pages\Concerns\ManagesRhExameDeleteConfirm;
use App\Filament\Resources\RhExameResource\Pages\Concerns\ManagesRhExameFormModal;
use App\Models\RhAnexo;
use App\Models\RhExame;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListRhExames extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesRhExameDeleteConfirm;
    use ManagesRhExameFormModal;

    protected static string $resource = RhExameResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'funcionario';

    #[Url(as: 'status')]
    public string $statusFilter = 'realizados';

    #[Url(as: 'alerta')]
    public int $alertaDias = 30;

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->alertaDias = $this->normalizeAlertaDias($this->alertaDias);

        ErpScreen::set('RH — Exames / ASO');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['funcionario', 'tipo', 'codigo'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'funcionario';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-exames-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um exame';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-exames__search-text',
            'create' => 'createExame',
            'edit' => 'editExame',
            'delete' => 'deleteExame',
            'extraKeys' => [
                'F4' => ['method' => 'imprimirExames', 'params' => []],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(RhExameResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['realizados', 'proximos', 'pendentes', 'todos'], true)
            ? (string) $value
            : 'realizados';
    }

    protected function normalizeAlertaDias(mixed $value): int
    {
        $dias = (int) $value;

        return in_array($dias, [7, 15, 30], true) ? $dias : 30;
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function setAlertaDias(int $dias): void
    {
        $this->alertaDias = $this->normalizeAlertaDias($dias);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $alerta = $this->alertaDias;
        $hoje = now()->toDateString();
        $limite = now()->addDays($alerta)->toDateString();

        $query = match ($this->statusFilter) {
            'realizados' => $query->where('ativo', true),
            'proximos' => $query->where('ativo', true)
                ->whereNotNull('proximo_em')
                ->whereDate('proximo_em', '>=', $hoje)
                ->whereDate('proximo_em', '<=', $limite),
            'pendentes' => $query->where('ativo', true)
                ->whereNotNull('proximo_em')
                ->whereDate('proximo_em', '<', $hoje),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applyExameSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyExameSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');
        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->erpAllowedSearchColumns(), true)
            ? $this->searchColumn
            : 'funcionario';

        if ($column === 'codigo') {
            if (is_numeric($term)) {
                $query->where(function (Builder $q) use ($term): void {
                    $q->whereKey((int) $term)->orWhere('codigo', $term);
                });

                return;
            }
            $query->where('codigo', 'like', '%'.$term.'%');

            return;
        }

        if ($column === 'tipo') {
            $tipos = collect(RhExame::tipos())
                ->filter(fn (string $label, string $key): bool => str_contains(mb_strtoupper($label, 'UTF-8'), $term) || str_contains(mb_strtoupper($key, 'UTF-8'), $term))
                ->keys()
                ->all();
            if ($tipos === []) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->whereIn('tipo', $tipos);

            return;
        }

        $like = '%'.$term.'%';
        $query->whereHas('funcionario', function (Builder $q) use ($like): void {
            $q->where('nome', 'like', $like)->orWhere('codigo', 'like', $like);
        });
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.rh-exames.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.rh-exames.status-filters'),
                View::make('filament.components.erp.rh-exames.action-bar'),
                View::make('filament.components.erp.rh-exames.form-modal'),
                View::make('filament.components.erp.rh-exames.confirm-delete-modal'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        if (filled($this->localSearch) && in_array($this->searchColumn, ['funcionario', 'tipo'], true)) {
            $this->localSearch = mb_strtoupper(trim($this->localSearch), 'UTF-8');
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'funcionario';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function abrirAsoSelecionado(): void
    {
        if (! ErpAccess::currentCan('rh.exames.access')) {
            Notification::make()->title('Sem permissão para visualizar ASO.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('open')) {
            return;
        }

        $exame = RhExame::query()->find($this->highlightedRecordId);
        if (! $exame) {
            Notification::make()->title('Exame não encontrado.')->warning()->send();

            return;
        }

        $anexo = $exame->anexos()
            ->where('categoria', RhAnexo::CAT_EXAME)
            ->orderByDesc('id')
            ->first(['id', 'caminho', 'titulo', 'categoria']);

        if (! $anexo || ! filled($anexo->caminho)) {
            Notification::make()->title('ASO não anexado neste exame.')->warning()->send();

            return;
        }

        $this->dispatch('erp-open-url', url: route('erp.rh.anexo.download', ['anexo' => $anexo->getKey()]));
    }

    public function imprimirExames(): void
    {
        if (! ErpAccess::currentCan('rh.exames.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        $params = array_filter([
            'status' => $this->statusFilter !== 'realizados' ? $this->statusFilter : null,
            'alerta' => $this->alertaDias !== 30 ? $this->alertaDias : null,
            'campo' => $this->searchColumn !== 'funcionario' ? $this->searchColumn : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'auto' => 1,
        ], fn ($v): bool => filled($v));

        $this->redirect(route('erp.reports.rh-exames-listagem', $params), navigate: false);
    }
}
