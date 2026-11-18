<?php

namespace App\Filament\Resources\RhTreinamentoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhTreinamentoResource;
use App\Filament\Resources\RhTreinamentoResource\Pages\Concerns\ManagesRhTreinamentoCatalogModal;
use App\Filament\Resources\RhTreinamentoResource\Pages\Concerns\ManagesRhTreinamentoFormModal;
use App\Filament\Resources\RhTreinamentoResource\Pages\Concerns\ManagesRhTreinamentoMovimentos;
use App\Models\RhFuncionario;
use App\Models\RhTreinamentoParticipante;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListRhTreinamentos extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesRhTreinamentoCatalogModal;
    use ManagesRhTreinamentoFormModal;
    use ManagesRhTreinamentoMovimentos;

    protected static string $resource = RhTreinamentoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'funcionario';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    #[Url(as: 'validade')]
    public string $validadeFilter = 'todos';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->validadeFilter = $this->normalizeValidadeFilter($this->validadeFilter);

        ErpScreen::set('RH — Treinamentos');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['funcionario', 'treinamento', 'codigo'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'funcionario';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-treinamentos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um treinamento';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-treinamentos__search-text',
            'create' => 'createRealizacao',
            'edit' => 'editParticipante',
            'delete' => 'cancelarParticipante',
            'extraKeys' => [
                'F4' => ['method' => 'imprimirTreinamentos', 'params' => []],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(RhTreinamentoResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'cancelados', 'todos'], true)
            ? (string) $value
            : 'ativos';
    }

    protected function normalizeValidadeFilter(mixed $value): string
    {
        return in_array($value, ['todos', 'em_dia', 'proximos', 'vencidos', 'sem'], true)
            ? (string) $value
            : 'todos';
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function setValidadeFilter(string $filter): void
    {
        $this->validadeFilter = $this->normalizeValidadeFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $query = match ($this->statusFilter) {
            'ativos' => $query->where('situacao', RhTreinamentoParticipante::SIT_ATIVO)->where('ativo', true),
            'cancelados' => $query->where('situacao', RhTreinamentoParticipante::SIT_CANCELADO),
            default => $query,
        };

        if ($this->validadeFilter !== 'todos' && in_array($this->statusFilter, ['ativos', 'todos'], true)) {
            $this->applyValidadeFilter($query, $this->validadeFilter);
        }

        if (filled($this->localSearch)) {
            $this->applyParticipanteSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyValidadeFilter(Builder $query, string $filter): void
    {
        // Validade só faz sentido sobre ativos
        $query->where('situacao', RhTreinamentoParticipante::SIT_ATIVO)->where('ativo', true);

        $hoje = Carbon::today()->toDateString();
        $limite = Carbon::today()->addDays(30)->toDateString();

        match ($filter) {
            'sem' => $query->whereNull('vencimento_em'),
            'vencidos' => $query->whereNotNull('vencimento_em')->whereDate('vencimento_em', '<', $hoje),
            'proximos' => $query->whereNotNull('vencimento_em')
                ->whereDate('vencimento_em', '>=', $hoje)
                ->whereDate('vencimento_em', '<=', $limite),
            'em_dia' => $query->whereNotNull('vencimento_em')->whereDate('vencimento_em', '>', $limite),
            default => null,
        };
    }

    protected function applyParticipanteSearch(Builder $query, string $term): void
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

        if ($column === 'funcionario') {
            $like = '%'.$term.'%';
            $query->whereHas('funcionario', function (Builder $q) use ($like): void {
                $q->where('nome', 'like', $like)->orWhere('codigo', 'like', $like);
            });

            return;
        }

        $query->whereHas('realizacao.treinamento', function (Builder $q) use ($term): void {
            $q->where('nome', 'like', '%'.$term.'%')
                ->orWhere('codigo', 'like', '%'.$term.'%');
        });
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.rh-treinamentos.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.rh-treinamentos.status-filters'),
                View::make('filament.components.erp.rh-treinamentos.action-bar'),
                View::make('filament.components.erp.rh-treinamentos.realizacao-form-modal'),
                View::make('filament.components.erp.rh-treinamentos.catalog-form-modal'),
                View::make('filament.components.erp.rh-treinamentos.cancel-confirm-modal'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        if (filled($this->localSearch) && in_array($this->searchColumn, ['funcionario', 'treinamento'], true)) {
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

    public function historicoFuncionarioSelecionado(): void
    {
        if (! ErpAccess::currentCan('rh.treinamentos.access')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('open')) {
            return;
        }

        $record = RhTreinamentoParticipante::query()->find($this->highlightedRecordId);
        if (! $record) {
            return;
        }

        $func = RhFuncionario::query()->find($record->funcionario_id);
        $this->localSearch = $func?->nome ?? '';
        $this->searchColumn = 'funcionario';
        $this->statusFilter = 'todos';
        $this->validadeFilter = 'todos';
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Histórico filtrado pelo funcionário.')->success()->send();
    }

    public function imprimirTreinamentos(): void
    {
        if (! ErpAccess::currentCan('rh.treinamentos.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        $params = array_filter([
            'status' => $this->statusFilter !== 'ativos' ? $this->statusFilter : null,
            'validade' => $this->validadeFilter !== 'todos' ? $this->validadeFilter : null,
            'campo' => $this->searchColumn !== 'funcionario' ? $this->searchColumn : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'auto' => 1,
        ], fn ($v): bool => filled($v));

        $this->redirect(route('erp.reports.rh-treinamentos-listagem', $params), navigate: false);
    }
}
