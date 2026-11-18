<?php

namespace App\Filament\Resources\RhHoleriteResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhHoleriteResource;
use App\Filament\Resources\RhHoleriteResource\Pages\Concerns\ManagesRhHoleriteDeleteConfirm;
use App\Filament\Resources\RhHoleriteResource\Pages\Concerns\ManagesRhHoleriteFormModal;
use App\Filament\Resources\RhHoleriteResource\Pages\Concerns\ManagesRhHoleriteImportModal;
use App\Models\RhAnexo;
use App\Models\RhHolerite;
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

class ListRhHolerites extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesRhHoleriteDeleteConfirm;
    use ManagesRhHoleriteFormModal;
    use ManagesRhHoleriteImportModal;

    protected static string $resource = RhHoleriteResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'funcionario';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    #[Url(as: 'ano')]
    public ?int $competenciaAno = null;

    #[Url(as: 'mes')]
    public ?int $competenciaMes = null;

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->competenciaAno = $this->normalizeCompetenciaAno($this->competenciaAno);
        $this->competenciaMes = $this->normalizeCompetenciaMes($this->competenciaMes);

        ErpScreen::set('RH — Holerites');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['funcionario', 'codigo'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'funcionario';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-holerites-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um holerite';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-holerites__search-text',
            'create' => 'createHolerite',
            'edit' => 'editHolerite',
            'delete' => 'deleteHolerite',
            'extraKeys' => [
                'F4' => ['method' => 'imprimirHolerites', 'params' => []],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(RhHoleriteResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'inativos', 'todos'], true) ? (string) $value : 'ativos';
    }

    protected function normalizeCompetenciaAno(mixed $value): ?int
    {
        $ano = (int) $value;

        return $ano >= 2000 && $ano <= 2100 ? $ano : null;
    }

    protected function normalizeCompetenciaMes(mixed $value): ?int
    {
        $mes = (int) $value;

        return $mes >= 1 && $mes <= 12 ? $mes : null;
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function setCompetenciaFilter(?int $ano, ?int $mes): void
    {
        $this->competenciaAno = $this->normalizeCompetenciaAno($ano);
        $this->competenciaMes = $this->normalizeCompetenciaMes($mes);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $query = match ($this->statusFilter) {
            'ativos' => $query->where('ativo', true),
            'inativos' => $query->where('ativo', false),
            default => $query,
        };

        $query->competencia($this->competenciaAno, $this->competenciaMes);

        if (filled($this->localSearch)) {
            $this->applyHoleriteSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyHoleriteSearch(Builder $query, string $term): void
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
                View::make('filament.components.erp.rh-holerites.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.rh-holerites.status-filters'),
                View::make('filament.components.erp.rh-holerites.action-bar'),
                View::make('filament.components.erp.rh-holerites.form-modal'),
                View::make('filament.components.erp.rh-holerites.confirm-delete-modal'),
                View::make('filament.components.erp.rh-holerites.import-modal'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        if (filled($this->localSearch) && $this->searchColumn === 'funcionario') {
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

    public function abrirHoleriteSelecionado(): void
    {
        if (! ErpAccess::currentCan('rh.holerites.access')) {
            Notification::make()->title('Sem permissão para visualizar holerite.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('open')) {
            return;
        }

        $holerite = RhHolerite::query()->find($this->highlightedRecordId);
        if (! $holerite) {
            Notification::make()->title('Holerite não encontrado.')->warning()->send();

            return;
        }

        $anexo = $holerite->anexos()
            ->where('categoria', RhAnexo::CAT_HOLERITE)
            ->orderByDesc('id')
            ->first(['id', 'caminho', 'titulo', 'categoria']);

        if (! $anexo || ! filled($anexo->caminho)) {
            Notification::make()->title('PDF não anexado neste holerite.')->warning()->send();

            return;
        }

        $this->dispatch('erp-open-url', url: route('erp.rh.anexo.download', ['anexo' => $anexo->getKey()]));
    }

    public function imprimirHolerites(): void
    {
        if (! ErpAccess::currentCan('rh.holerites.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        $params = array_filter([
            'status' => $this->statusFilter !== 'ativos' ? $this->statusFilter : null,
            'ano' => $this->competenciaAno,
            'mes' => $this->competenciaMes,
            'campo' => $this->searchColumn !== 'funcionario' ? $this->searchColumn : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'auto' => 1,
        ], fn ($v): bool => filled($v));

        $this->redirect(route('erp.reports.rh-holerites-listagem', $params), navigate: false);
    }
}
