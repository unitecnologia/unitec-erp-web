<?php

namespace App\Filament\Resources\RhOcorrenciaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhOcorrenciaResource;
use App\Filament\Resources\RhOcorrenciaResource\Pages\Concerns\ManagesRhOcorrenciaDeleteConfirm;
use App\Filament\Resources\RhOcorrenciaResource\Pages\Concerns\ManagesRhOcorrenciaFormModal;
use App\Models\RhAnexo;
use App\Models\RhOcorrencia;
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

class ListRhOcorrencias extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesRhOcorrenciaDeleteConfirm;
    use ManagesRhOcorrenciaFormModal;

    protected static string $resource = RhOcorrenciaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'funcionario';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativas';

    #[Url(as: 'tipo')]
    public string $tipoFilter = 'todos';

    #[Url(as: 'de')]
    public string $periodoDe = '';

    #[Url(as: 'ate')]
    public string $periodoAte = '';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->tipoFilter = $this->normalizeTipoFilter($this->tipoFilter);

        ErpScreen::set('RH — Ocorrências');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['funcionario', 'codigo', 'descricao'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'funcionario';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-ocorrencias-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma ocorrência';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-ocorrencias__search-text',
            'create' => 'createOcorrencia',
            'edit' => 'editOcorrencia',
            'delete' => 'cancelarOcorrencia',
            'extraKeys' => [
                'F4' => ['method' => 'imprimirOcorrencias', 'params' => []],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(RhOcorrenciaResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativas', 'canceladas', 'todos'], true)
            ? (string) $value
            : 'ativas';
    }

    protected function normalizeTipoFilter(mixed $value): string
    {
        if ($value === 'todos' || $value === '') {
            return 'todos';
        }

        return array_key_exists((string) $value, RhOcorrencia::tipos())
            ? (string) $value
            : 'todos';
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function setTipoFilter(string $filter): void
    {
        $this->tipoFilter = $this->normalizeTipoFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedPeriodoDe(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedPeriodoAte(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $query = match ($this->statusFilter) {
            'ativas' => $query->where('ativo', true)->where('situacao', '!=', RhOcorrencia::SIT_CANCELADA),
            'canceladas' => $query->where(function (Builder $q): void {
                $q->where('situacao', RhOcorrencia::SIT_CANCELADA)->orWhere('ativo', false);
            }),
            default => $query,
        };

        if ($this->tipoFilter !== 'todos') {
            $query->where('tipo', $this->tipoFilter);
        }

        if (filled($this->periodoDe)) {
            $query->whereDate('ocorrido_em', '>=', $this->periodoDe);
        }

        if (filled($this->periodoAte)) {
            $query->whereDate('ocorrido_em', '<=', $this->periodoAte);
        }

        if (filled($this->localSearch)) {
            $this->applyOcorrenciaSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyOcorrenciaSearch(Builder $query, string $term): void
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

        if ($column === 'descricao') {
            $query->where('descricao', 'like', '%'.$term.'%');

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
                View::make('filament.components.erp.rh-ocorrencias.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.rh-ocorrencias.status-filters'),
                View::make('filament.components.erp.rh-ocorrencias.action-bar'),
                View::make('filament.components.erp.rh-ocorrencias.form-modal'),
                View::make('filament.components.erp.rh-ocorrencias.confirm-delete-modal'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        if (filled($this->localSearch) && in_array($this->searchColumn, ['funcionario', 'descricao'], true)) {
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

    public function abrirAnexoSelecionado(): void
    {
        if (! ErpAccess::currentCan('rh.ocorrencias.access')) {
            Notification::make()->title('Sem permissão para abrir anexo desta ocorrência.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('open')) {
            return;
        }

        $ocorrencia = RhOcorrencia::query()->find($this->highlightedRecordId);
        if (! $ocorrencia) {
            Notification::make()->title('Ocorrência não encontrada.')->warning()->send();

            return;
        }

        $anexo = $ocorrencia->anexos()
            ->where('categoria', RhAnexo::CAT_OCORRENCIA)
            ->orderByDesc('id')
            ->first(['id', 'caminho', 'titulo', 'categoria']);

        if (! $anexo || ! filled($anexo->caminho)) {
            Notification::make()->title('Nenhum anexo nesta ocorrência.')->warning()->send();

            return;
        }

        $this->dispatch('erp-open-url', url: route('erp.rh.anexo.download', ['anexo' => $anexo->getKey()]));
    }

    public function imprimirOcorrencias(): void
    {
        if (! ErpAccess::currentCan('rh.ocorrencias.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        $params = array_filter([
            'status' => $this->statusFilter !== 'ativas' ? $this->statusFilter : null,
            'tipo' => $this->tipoFilter !== 'todos' ? $this->tipoFilter : null,
            'de' => filled($this->periodoDe) ? $this->periodoDe : null,
            'ate' => filled($this->periodoAte) ? $this->periodoAte : null,
            'campo' => $this->searchColumn !== 'funcionario' ? $this->searchColumn : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'auto' => 1,
        ], fn ($v): bool => filled($v));

        $this->redirect(route('erp.reports.rh-ocorrencias-listagem', $params), navigate: false);
    }

    public function imprimirRegistroSelecionado(): void
    {
        if (! ErpAccess::currentCan('rh.ocorrencias.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('print')) {
            return;
        }

        $this->redirect(route('erp.reports.rh-ocorrencia-registro', [
            'ocorrencia' => $this->highlightedRecordId,
            'auto' => 1,
        ]), navigate: false);
    }
}
