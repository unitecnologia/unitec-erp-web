<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Concerns\EmbedsInPdvOverlay;
use App\Filament\Concerns\InteractsWithErpDualSearchFields;
use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\Concerns\ManagesProductCardex;
use App\Models\Empresa;
use App\Models\Product;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpDataSyncVersion;
use App\Support\Erp\ProductCloneService;
use App\Support\Erp\ProductListLiveSearch;
use App\Support\Erp\ProductDeletionGuard;
use App\Support\Erp\Queries\ProductListQueryBuilder;
use App\Support\Erp\Queries\ProductSerialListQueryBuilder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Livewire\Erp\ProductListTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class ListProducts extends ListRecords
{
    use EmbedsInPdvOverlay;
    use InteractsWithErpDualSearchFields;
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use ManagesProductCardex;

    protected static string $resource = ProductResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'descricao';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    #[Url(as: 'view')]
    public string $viewFilter = 'produtos';

    public function mount(): void
    {
        parent::mount();

        $this->viewFilter = in_array($this->viewFilter, ['produtos', 'seriais'], true)
            ? $this->viewFilter
            : 'produtos';

        // Sem ?campo= na URL: usa o campo marcado como padrão (sessão).
        if (! request()->has('campo')) {
            $this->searchColumn = $this->isSeriaisView()
                ? (string) session('erp_produtos_search_column_seriais', 'descricao')
                : (string) session('erp_produtos_search_column', 'descricao');
        }

        $this->searchColumn = $this->isSeriaisView()
            ? $this->normalizeSerialSearchColumn($this->searchColumn)
            : $this->normalizeSearchColumn($this->searchColumn);
        $this->erpRestoreDualSearchFields($this->productSearchColumns(), $this->productSearchFieldsSessionKey());
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);

        ErpScreen::set($this->isSeriaisView() ? 'Seriais' : 'Produtos');
        ProductListLiveSearch::put($this->localSearch);
    }

    /**
     * A grade é renderizada pelo Livewire filho {@see ProductListTable}.
     * Evita carregar/serializar registros Filament no componente pai (HTML/Livewire menores).
     */
    public function mountInteractsWithTable(): void
    {
    }

    public function setSearchColumn(string $column): void
    {
        $normalized = $this->isSeriaisView()
            ? $this->normalizeSerialSearchColumn($column)
            : $this->normalizeSearchColumn($column);

        if (! $this->erpToggleDualSearchField($normalized, $this->productSearchColumns(), $this->productSearchFieldsSessionKey())) {
            return;
        }

        session([$this->productSearchColumnSessionKey() => $this->searchColumn]);

        $this->clearListSelection();
        $this->pushProductListRefresh(resetSort: true, skipParentRender: false);
    }

    /**
     * @return list<string>
     */
    protected function productSearchColumns(): array
    {
        return $this->isSeriaisView()
            ? ['descricao', 'numero_serie']
            : [
                'codigo', 'referencia', 'codigo_barras', 'descricao', 'grupo',
                'preco_venda', 'estoque', 'localizacao',
            ];
    }

    protected function productSearchFieldsSessionKey(): string
    {
        return $this->isSeriaisView()
            ? 'erp_produtos_search_fields_seriais'
            : 'erp_produtos_search_fields';
    }

    protected function productSearchColumnSessionKey(): string
    {
        return $this->isSeriaisView()
            ? 'erp_produtos_search_column_seriais'
            : 'erp_produtos_search_column';
    }

    public function persistProductSearchFields(): void
    {
        $fields = $this->searchFieldsActive !== [] ? $this->searchFieldsActive : [$this->searchColumn];
        $this->searchFieldsActive = array_values($fields);
        $this->searchFieldsQuery = count($this->searchFieldsActive) > 1 ? implode(',', $this->searchFieldsActive) : '';

        session([
            $this->productSearchFieldsSessionKey() => $this->searchFieldsActive,
            $this->productSearchColumnSessionKey() => $this->searchColumn,
        ]);

        $this->skipRender();
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'inativos', 'todos'], true) ? (string) $value : 'ativos';
    }

    protected function normalizeSearchColumn(mixed $value): string
    {
        $allowed = [
            'codigo', 'referencia', 'codigo_barras', 'descricao', 'grupo',
            'preco_venda', 'estoque', 'localizacao',
        ];

        return in_array($value, $allowed, true) ? (string) $value : 'descricao';
    }

    protected function normalizeSerialSearchColumn(mixed $value): string
    {
        return in_array($value, ['descricao', 'numero_serie'], true) ? (string) $value : 'descricao';
    }

    public function isSeriaisView(): bool
    {
        return $this->viewFilter === 'seriais';
    }

    public function produtosListUrl(
        ?string $status = null,
        ?string $campo = null,
        ?string $q = null,
        ?string $view = null,
    ): string {
        $params = [];

        $viewValue = $view ?? $this->viewFilter;

        if ($viewValue === 'seriais') {
            $params['view'] = 'seriais';
        }

        $statusValue = $status ?? $this->statusFilter;

        if ($viewValue !== 'seriais' && $statusValue !== 'ativos') {
            $params['status'] = $statusValue;
        }

        $campoValue = $campo ?? $this->searchColumn;
        $defaultCampo = $viewValue === 'seriais' ? 'descricao' : 'descricao';

        if ($campoValue !== $defaultCampo) {
            $params['campo'] = $campoValue;
        }

        $searchValue = $q ?? $this->localSearch;

        if (filled($searchValue)) {
            $params['q'] = $searchValue;
        }

        if ($this->embedsInPdv) {
            $params['pdv'] = '1';
        }

        $query = http_build_query($params);

        return ProductResource::getUrl('index') . ($query !== '' ? '?' . $query : '');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-produtos-page';
    }

    public function getPageClasses(): array
    {
        $classes = [
            ...parent::getPageClasses(),
            'erp-list-page',
            static::erpListPageClass(),
        ];

        if ($this->embedsInPdv) {
            $classes[] = 'erp-pdv-embed';
        }

        return $classes;
    }

    public function closeScreen(): void
    {
        if ($this->embedsInParentOverlay()) {
            $this->closeEmbedOverlay();

            return;
        }

        ErpScreen::set('Principal');

        $this->redirect(filament()->getUrl());
    }

    protected function erpListEntityName(): string
    {
        return 'um produto';
    }

    protected function erpListSyncChannel(): ?string
    {
        return ErpDataSyncVersion::CHANNEL_PRODUCTS;
    }

    public function erpListSyncPollEnabled(): bool
    {
        if (! config('unitec.erp_list_sync_poll_enabled', true)) {
            return false;
        }

        return $this->erpListSyncChannel() !== null;
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'duplicate' => 'um produto para duplicar',
            'history' => 'um produto para ver o histórico',
            default => $this->defaultErpListSelectPrompt($action),
        };
    }

    protected function productListDirectEditUrl(): ?string
    {
        if ($this->isSeriaisView() || ! $this->erpCan('produtos.update')) {
            return null;
        }

        return $this->urlWithPdvEmbed(
            ProductResource::getUrl('edit', ['record' => '__RECORD__'])
        );
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-produtos__search-text',
            'create' => 'createProduct',
            'edit' => 'editProduct',
            'editUrl' => $this->productListDirectEditUrl(),
            'delete' => 'deleteProduct',
            'extraKeys' => [
                'F4' => ['method' => 'printProducts'],
                'F7' => ['method' => 'openProductCardex'],
                'F8' => ['method' => 'duplicateProduct'],
            ],
        ];
    }

    public function setViewFilter(string $view): void
    {
        if (! in_array($view, ['produtos', 'seriais'], true)) {
            return;
        }

        $this->viewFilter = $view;
        $this->searchColumn = $view === 'seriais' ? 'descricao' : $this->normalizeSearchColumn($this->searchColumn);
        $this->erpApplyDualSearchFields([$this->searchColumn], $this->productSearchFieldsSessionKey());
        session([$this->productSearchColumnSessionKey() => $this->searchColumn]);
        $this->localSearch = '';
        ProductListLiveSearch::put('');
        $this->clearListSelection();
        $this->pushProductListRefresh(resetSort: true);

        ErpScreen::set($view === 'seriais' ? 'Seriais' : 'Produtos');
    }

    public function setStatusFilter(string $filter): void
    {
        if ($this->isSeriaisView()) {
            return;
        }

        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->pushProductListRefresh(skipParentRender: false);
    }

    /**
     * Contagens das abas Ativos / Inativos / Todos (respeita a busca atual).
     *
     * @return array{ativos: int, inativos: int, todos: int}
     */
    public function productStatusCounts(): array
    {
        $this->pullListSearch();
        $empresa = $this->currentEmpresa();
        $searchColumn = $this->searchColumn;
        $localSearch = $this->localSearch;
        $searchFieldsActive = $this->searchFieldsActive;

        $countFor = static function (string $status) use ($empresa, $searchColumn, $localSearch, $searchFieldsActive): int {
            return (new ProductListQueryBuilder(
                statusFilter: $status,
                searchColumn: $searchColumn,
                localSearch: $localSearch,
                empresa: $empresa,
                applyDefaultOrder: false,
                searchFieldsActive: $searchFieldsActive,
                deferReservaSum: true,
            ))->build()->count();
        };

        return [
            'ativos' => $countFor('ativos'),
            'inativos' => $countFor('inativos'),
            'todos' => $countFor('todos'),
        ];
    }

    public function updatedSearchColumn(): void
    {
        $this->setSearchColumn($this->searchColumn);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->pushProductListRefresh();
    }

    public function updatedLocalSearch(): void
    {
        ProductListLiveSearch::put($this->localSearch);
        $this->clearListSelection();
        $this->pushProductListRefresh(resetSort: true);
    }

    public function search(): void
    {
        $this->updatedLocalSearch();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        ProductListLiveSearch::put('');
        $this->searchColumn = 'descricao';
        $this->erpApplyDualSearchFields(['descricao'], $this->productSearchFieldsSessionKey());
        session([$this->productSearchColumnSessionKey() => $this->searchColumn]);
        $this->clearListSelection();
        $this->pushProductListRefresh(resetSort: true, skipParentRender: false);
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
        $this->pushProductListRefresh(skipParentRender: false);
    }

    protected function pushProductListRefresh(bool $resetSort = false, bool $skipParentRender = true): void
    {
        $this->pullListSearch();

        $this->dispatch(
            'erp-product-list-refresh',
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            viewFilter: $this->viewFilter,
            perPage: (int) ($this->tableRecordsPerPage ?? 50),
            resetSort: $resetSort,
            searchFieldsActive: $this->searchFieldsActive,
        )->to(ProductListTable::class);

        if ($skipParentRender) {
            $this->skipRender();
        }
    }

    public function refreshTable(): void
    {
        $this->syncErpListSyncVersionFromStore();
        $this->pushProductListRefresh();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        if ($this->isSeriaisView()) {
            return $this->applyErpListSelection(ProductResource::serialsTable($table));
        }

        return $this->applyErpListSelection(ProductResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $this->pullListSearch();

        if ($this->isSeriaisView()) {
            return (new ProductSerialListQueryBuilder(
                searchColumn: $this->searchColumn,
                localSearch: $this->localSearch,
                empresa: $this->currentEmpresa(),
                searchFieldsActive: $this->searchFieldsActive,
            ))->build();
        }

        return (new ProductListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            empresa: $this->currentEmpresa(),
            applyDefaultOrder: false,
            searchFieldsActive: $this->searchFieldsActive,
        ))->build();
    }

    protected function pullListSearch(): void
    {
        $synced = ProductListLiveSearch::get();

        if ($synced !== null) {
            $this->localSearch = $synced;
        }
    }

    protected function currentEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }

    public function content(Schema $schema): Schema
    {
        $this->pullListSearch();

        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.produtos.screen'),
                View::make('filament.components.erp.produtos.table-host')
                    ->columnSpanFull(),
                View::make('filament.components.erp.produtos.status-filters'),
                View::make('filament.components.erp.produtos.action-bar'),
            ]);
    }

    public function createProduct(): void
    {
        if (! $this->erpAuthorizeOrNotify('produtos.create')) {
            return;
        }

        if ($this->isSeriaisView()) {
            Notification::make()
                ->title('Cadastre produtos na aba Produtos.')
                ->warning()
                ->send();

            return;
        }

        $this->redirect($this->urlWithPdvEmbed(ProductResource::getUrl('create')));
    }

    public function editProduct(int | string | null $recordId = null): void
    {
        if (! $this->erpAuthorizeOrNotify('produtos.update')) {
            return;
        }

        if ($this->isSeriaisView()) {
            Notification::make()
                ->title('Selecione a aba Produtos para alterar um produto.')
                ->warning()
                ->send();

            return;
        }

        $resolvedId = filled($recordId) ? (int) $recordId : $this->highlightedRecordId;

        if (! $resolvedId) {
            $this->highlightedRecordIdOrNotify('edit');

            return;
        }

        $this->redirect($this->urlWithPdvEmbed(ProductResource::getUrl('edit', ['record' => $resolvedId])));
    }

    public function deleteProduct(int | string | null $recordId = null): void
    {
        if (! $this->erpAuthorizeOrNotify('produtos.delete')) {
            return;
        }

        if ($this->isSeriaisView()) {
            return;
        }

        $recordId = filled($recordId) ? (int) $recordId : $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $product = Product::query()->find($recordId);

        if (! $product) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->danger()
                ->send();

            return;
        }

        $guard = app(ProductDeletionGuard::class);
        $blockingReasons = $guard->blockingReasons($product);

        if ($blockingReasons !== []) {
            Notification::make()
                ->title('Exclusão não permitida')
                ->body($guard->message($blockingReasons))
                ->warning()
                ->send();

            return;
        }

        try {
            $product->delete();
        } catch (\Throwable) {
            Notification::make()
                ->title('Exclusão não permitida')
                ->body('O produto possui vínculos e não pode ser excluído.')
                ->warning()
                ->send();

            return;
        }

        $this->clearListSelection();

        Notification::make()
            ->title('Produto excluído.')
            ->success()
            ->send();

        $this->pushProductListRefresh();
    }

    public function duplicateProduct(): void
    {
        if (! $this->erpAuthorizeOrNotify('produtos.duplicate')) {
            return;
        }

        if ($this->isSeriaisView()) {
            Notification::make()
                ->title('Duplicar está disponível na aba Produtos.')
                ->warning()
                ->send();

            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('duplicate');

        if (! $recordId) {
            return;
        }

        $source = Product::query()->find($recordId);

        if (! $source) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->danger()
                ->send();

            return;
        }

        $clone = app(ProductCloneService::class)->cloneFrom($source);

        Notification::make()
            ->title('Produto duplicado.')
            ->body('Código ' . $clone->codigo . ' — ajuste referência e preços.')
            ->success()
            ->send();

        $this->redirect($this->urlWithPdvEmbed(ProductResource::getUrl('edit', ['record' => $clone])));
    }

    public function printProducts(): void
    {
        if (! $this->erpAuthorizeOrNotify('produtos.print')) {
            return;
        }

        if ($this->isSeriaisView()) {
            Notification::make()
                ->title('Impressão disponível na aba Produtos.')
                ->warning()
                ->send();

            return;
        }

        $this->pullListSearch();

        $builder = new ProductListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            empresa: $this->currentEmpresa(),
            orderBy: 'descricao',
            searchFieldsActive: $this->searchFieldsActive,
        );

        $params = array_filter(
            $builder->reportFilters(),
            fn ($value): bool => filled($value),
        );

        $url = route('erp.reports.produtos-estoque', $params);

        $this->redirect($url, navigate: false);
    }
}
