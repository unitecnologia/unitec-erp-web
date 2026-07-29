<?php

namespace App\Filament\Resources\RhFuncionarioResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhFuncionarioResource;
use App\Filament\Resources\RhFuncionarioResource\Pages\Concerns\ManagesRhFuncionarioFormModal;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListRhFuncionarios extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;
    use ManagesRhFuncionarioFormModal;
    use ManagesErpSearchColumn {
        ManagesErpSearchColumn::updatedSearchColumn insteadof InteractsWithErpSimpleListPage;
    }

    protected static string $resource = RhFuncionarioResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'nome';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->rhFuncionarioForm = $this->blankRhFuncionarioForm();

        ErpScreen::set('RH — Funcionários');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['codigo', 'nome', 'cpf'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'nome';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-funcionarios-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um funcionário';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-rh-funcionarios__search-text';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createRhFuncionario';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editRhFuncionario';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteRhFuncionario';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(RhFuncionarioResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'inativos', 'todos'], true) ? (string) $value : 'ativos';
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()->with(['cargo', 'departamento']);

        $query = match ($this->statusFilter) {
            'ativos' => $query->where('ativo', true)->whereNull('data_demissao'),
            'inativos' => $query->where(fn (Builder $q) => $q->where('ativo', false)->orWhereNotNull('data_demissao')),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applySimpleLocalSearch($query, $this->localSearch, ['codigo', 'nome', 'cpf']);
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.shared.logistica-simple-screen')
                    ->viewData([
                        'pageClass' => 'erp-rh-funcionarios',
                        'nomeSearchLabel' => 'NOME',
                        'deleteHint' => 'funcionário',
                        'searchFields' => [
                            'codigo' => 'CÓDIGO',
                            'nome' => 'NOME',
                            'cpf' => 'CPF',
                        ],
                    ]),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.shared.erp-status-filters')
                    ->viewData(['pageClass' => 'erp-rh-funcionarios']),
                View::make('filament.components.erp.shared.logistica-simple-action-bar')
                    ->viewData([
                        'pageClass' => 'erp-rh-funcionarios',
                        'createMethod' => 'createRhFuncionario',
                        'editMethod' => 'editRhFuncionario',
                        'deleteMethod' => 'deleteRhFuncionario',
                    ]),
                View::make('filament.components.erp.rh-funcionarios.form-modal'),
            ]);
    }

    public function deleteRhFuncionario(): void
    {
        if ($this->rhFuncionarioModalOpen) {
            return;
        }

        $this->deleteSimpleRecord(RhFuncionario::class, 'Funcionário excluído.');
    }
}
