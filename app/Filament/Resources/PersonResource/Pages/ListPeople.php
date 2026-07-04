<?php

namespace App\Filament\Resources\PersonResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\PersonResource;
use App\Models\Person;
use App\Support\Erp\Queries\PersonListQueryBuilder;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListPeople extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;

    protected static string $resource = PersonResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'nome_razao';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    #[Url(as: 'tipo')]
    public string $tipoFilter = 'clientes';

    public function mount(): void
    {
        parent::mount();

        $this->searchColumn = $this->normalizeSearchColumn($this->searchColumn);
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->tipoFilter = $this->normalizeTipoFilter($this->tipoFilter);

        $this->normalizeLocalSearchCase();
        $this->syncErpScreenTitle();
    }

    protected function normalizeTipoFilter(mixed $value): string
    {
        $allowed = [
            'clientes',
            'funcionarios',
            'fornecedores',
            'administradoras',
            'parceiros',
            'ccf_spc',
            'todos',
        ];

        return in_array($value, $allowed, true) ? (string) $value : 'clientes';
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'inativos', 'todos'], true) ? (string) $value : 'ativos';
    }

    protected function normalizeSearchColumn(mixed $value): string
    {
        $allowed = ['codigo', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj', 'rg_ie', 'endereco'];

        return in_array($value, $allowed, true) ? (string) $value : 'nome_razao';
    }

    protected function syncErpScreenTitle(): void
    {
        ErpScreen::set(match ($this->tipoFilter) {
            'ccf_spc' => 'Lista SPC/CCF',
            'todos' => 'Contatos',
            default => 'Pessoas',
        });
    }

    public function pessoasListUrl(
        ?string $tipo = null,
        ?string $status = null,
        ?string $campo = null,
        ?string $q = null,
    ): string {
        $params = [];

        $tipoValue = $tipo ?? $this->tipoFilter;

        if ($tipoValue !== 'clientes') {
            $params['tipo'] = $tipoValue;
        }

        $statusValue = $status ?? $this->statusFilter;

        if ($statusValue !== 'ativos') {
            $params['status'] = $statusValue;
        }

        $campoValue = $campo ?? $this->searchColumn;

        if ($campoValue !== 'nome_razao') {
            $params['campo'] = $campoValue;
        }

        $searchValue = $q ?? $this->localSearch;

        if (filled($searchValue)) {
            $params['q'] = $searchValue;
        }

        return PersonResource::getUrl('index') . '?' . http_build_query($params);
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-pessoas-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma pessoa';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-pessoas__search-text',
            'create' => 'createPerson',
            'edit' => 'editPerson',
            'delete' => 'deletePerson',
            'extraKeys' => [
                'F4' => ['method' => 'printPeople'],
            ],
        ];
    }

    public function setTipoFilter(string $tipo): void
    {
        if (! in_array($tipo, ['clientes', 'funcionarios', 'fornecedores', 'administradoras', 'parceiros', 'todos'], true)) {
            return;
        }

        $this->tipoFilter = $tipo;
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
        $this->syncErpScreenTitle();
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->searchColumn = $this->normalizeSearchColumn($this->searchColumn);
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
        $this->normalizeLocalSearchCase();
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function normalizeLocalSearchCase(): void
    {
        if (! filled($this->localSearch)) {
            return;
        }

        if (! in_array($this->searchColumn, ['nome_razao', 'apelido_fantasia', 'endereco'], true)) {
            return;
        }

        $this->localSearch = mb_strtoupper($this->localSearch, 'UTF-8');
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'nome_razao';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(PersonResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return (new PersonListQueryBuilder(
            statusFilter: $this->statusFilter,
            tipoFilter: $this->tipoFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
        ))->build();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.pessoas.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.pessoas.status-filters'),
                View::make('filament.components.erp.pessoas.action-bar'),
            ]);
    }

    public function createPerson(): void
    {
        if (! $this->erpAuthorizeOrNotify('pessoas.create')) {
            return;
        }

        $this->redirect(PersonResource::getUrl('create', [
            'tipo' => $this->tipoFilter,
        ]));
    }

    public function editPerson(): void
    {
        if (! $this->erpAuthorizeOrNotify('pessoas.update')) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        $this->redirect(PersonResource::getUrl('edit', ['record' => $recordId]));
    }

    public function deletePerson(): void
    {
        if (! $this->erpAuthorizeOrNotify('pessoas.delete')) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        Person::query()->whereKey($recordId)->delete();

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Pessoa excluída.')
            ->success()
            ->send();
    }

    public function printPeople(): void
    {
        if (! $this->erpAuthorizeOrNotify('pessoas.print')) {
            return;
        }

        $builder = new PersonListQueryBuilder(
            statusFilter: $this->statusFilter,
            tipoFilter: $this->tipoFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
        );

        $params = array_filter(
            $builder->reportFilters(),
            fn ($value): bool => filled($value),
        );

        $url = route('erp.reports.pessoas-listagem', $params);

        $this->redirect($url, navigate: false);
    }
}
