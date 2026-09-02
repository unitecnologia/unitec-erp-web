<?php

namespace App\Filament\Resources\PersonResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\PersonResource;
use App\Livewire\Erp\PersonListTable;
use App\Models\Person;
use App\Support\Erp\ErpDataSyncVersion;
use App\Support\Erp\Queries\PersonListQueryBuilder;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListPeople extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use ManagesErpSearchColumn;

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

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->tipoFilter = $this->normalizeTipoFilter($this->tipoFilter);

        $this->normalizeLocalSearchCase();
        $this->syncErpScreenTitle();
    }

    public function mountInteractsWithTable(): void
    {
    }

    protected function erpAfterSearchColumnChanged(bool $hadSearch = true, bool $changed = true): void
    {
        $this->clearListSelection();

        if (! $hadSearch) {
            $this->skipRender();

            return;
        }

        $this->pushPersonListRefresh(resetSort: true);
    }

    public function erpListSyncPollEnabled(): bool
    {
        if (! config('unitec.erp_list_sync_poll_enabled', true)) {
            return false;
        }

        return $this->erpListSyncChannel() !== null;
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['codigo', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj', 'rg_ie', 'endereco'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'nome_razao';
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

    protected function erpListSyncChannel(): ?string
    {
        return ErpDataSyncVersion::CHANNEL_PEOPLE;
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
        $this->syncErpScreenTitle();
        $this->pushPersonListRefresh(resetSort: true);
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->pushPersonListRefresh();
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->pushPersonListRefresh();
    }

    public function search(): void
    {
        $this->normalizeLocalSearchCase();
        $this->clearListSelection();
        $this->pushPersonListRefresh(resetSort: true);
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
        $this->pushPersonListRefresh(resetSort: true);
    }

    public function pollErpListSync(): void
    {
        $channel = $this->erpListSyncChannel();

        if ($channel === null) {
            $this->skipRender();

            return;
        }

        $current = ErpDataSyncVersion::current($channel);

        if ($this->erpListSyncVersion === null) {
            $this->erpListSyncVersion = $current;
            $this->skipRender();

            return;
        }

        if (hash_equals($this->erpListSyncVersion, $current)) {
            $this->skipRender();

            return;
        }

        $this->erpListSyncVersion = $current;
        $this->pushPersonListRefresh();
    }

    protected function pushPersonListRefresh(bool $resetSort = false): void
    {
        $this->dispatch(
            'erp-person-list-refresh',
            statusFilter: $this->statusFilter,
            tipoFilter: $this->tipoFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            perPage: (int) ($this->tableRecordsPerPage ?? 50),
            resetSort: $resetSort,
        )->to(PersonListTable::class);

        $this->skipRender();
    }

    public function refreshTable(): void
    {
        $this->syncErpListSyncVersionFromStore();
        $this->pushPersonListRefresh();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
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
            applyDefaultOrder: false,
        ))->buildForList();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.pessoas.screen'),
                View::make('filament.components.erp.pessoas.table-host')
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

    public function editPerson(int | string | null $recordId = null): void
    {
        if (! $this->erpAuthorizeOrNotify('pessoas.update')) {
            return;
        }

        $resolvedId = filled($recordId) ? (int) $recordId : $this->highlightedRecordId;

        if (! $resolvedId) {
            $this->highlightedRecordIdOrNotify('edit');

            return;
        }

        $this->redirect(PersonResource::getUrl('edit', ['record' => $resolvedId]));
    }

    public function deletePerson(int | string | null $recordId = null): void
    {
        if (! $this->erpAuthorizeOrNotify('pessoas.delete')) {
            return;
        }

        $recordId = filled($recordId) ? (int) $recordId : $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        Person::query()->whereKey($recordId)->delete();

        $this->clearListSelection();
        $this->pushPersonListRefresh();

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
