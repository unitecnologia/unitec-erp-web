<?php

namespace App\Filament\Resources\TransportadoraResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\TransportadoraResource;
use App\Filament\Resources\TransportadoraResource\Pages\Concerns\ManagesTransportadoraFormModal;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListTransportadoras extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesTransportadoraFormModal;

    protected static string $resource = TransportadoraResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);

        ErpScreen::set('Transportadoras');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['codigo', 'proprietario', 'apelido', 'cnpj_cpf', 'cidade'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'codigo';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-transportadoras-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma transportadora';
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'inativos', 'todos'], true) ? (string) $value : 'ativos';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-transportadoras__search-text',
            'create' => 'createTransportadora',
            'edit' => 'editTransportadora',
            'extraKeys' => [
                'F4' => ['method' => 'modulePending', 'params' => ['Imprimir']],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(TransportadoraResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        match ($this->statusFilter) {
            'ativos' => $query->where('ativo', true),
            'inativos' => $query->where('ativo', false),
            default => null,
        };

        if (filled($this->localSearch)) {
            $allowed = ['codigo', 'proprietario', 'apelido', 'cnpj_cpf', 'cidade'];
            $column = in_array($this->searchColumn, $allowed, true)
                ? $this->searchColumn
                : 'codigo';

            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');

            if ($column === 'cnpj_cpf') {
                $digits = preg_replace('/\D/', '', $term) ?? '';
                $query->where($column, 'like', '%'.$digits.'%');
            } else {
                $query->where($column, 'like', '%'.$term.'%');
            }
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.transportadoras.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.transportadoras.status-filters'),
                View::make('filament.components.erp.transportadoras.action-bar'),
                View::make('filament.components.erp.transportadoras.form-modal'),
            ]);
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
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
        if (filled($this->localSearch) && in_array($this->searchColumn, ['proprietario', 'apelido', 'cidade'], true)) {
            $this->localSearch = mb_strtoupper(trim($this->localSearch), 'UTF-8');
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'codigo';
        $this->clearListSelection();
        $this->resetTable();
    }
}
