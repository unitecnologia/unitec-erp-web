<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\Concerns\ManagesProductCardex;
use App\Models\Empresa;
use App\Models\Product;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ProductCloneService;
use App\Support\Erp\ProductDeletionGuard;
use App\Support\Erp\Queries\ProductListQueryBuilder;
use App\Support\Erp\Queries\ProductSerialListQueryBuilder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class ListProducts extends ListRecords
{
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

        $this->searchColumn = $this->isSeriaisView()
            ? $this->normalizeSerialSearchColumn($this->searchColumn)
            : $this->normalizeSearchColumn($this->searchColumn);
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->viewFilter = in_array($this->viewFilter, ['produtos', 'seriais'], true)
            ? $this->viewFilter
            : 'produtos';

        ErpScreen::set($this->isSeriaisView() ? 'Seriais' : 'Produtos');
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

        $query = http_build_query($params);

        return ProductResource::getUrl('index') . ($query !== '' ? '?' . $query : '');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-produtos-page';
    }

    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-list-page',
            static::erpListPageClass(),
        ];
    }

    protected function erpListEntityName(): string
    {
        return 'um produto';
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'duplicate' => 'um produto para duplicar',
            'history' => 'um produto para ver o histórico',
            default => $this->defaultErpListSelectPrompt($action),
        };
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-produtos__search-text',
            'create' => 'createProduct',
            'edit' => 'editProduct',
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
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();

        ErpScreen::set($view === 'seriais' ? 'Seriais' : 'Produtos');
    }

    public function setStatusFilter(string $filter): void
    {
        if ($this->isSeriaisView()) {
            return;
        }

        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->searchColumn = $this->isSeriaisView()
            ? $this->normalizeSerialSearchColumn($this->searchColumn)
            : $this->normalizeSearchColumn($this->searchColumn);
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
        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = $this->isSeriaisView() ? 'descricao' : 'descricao';
        $this->clearListSelection();
        $this->resetTable();
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
        if ($this->isSeriaisView()) {
            return (new ProductSerialListQueryBuilder(
                searchColumn: $this->searchColumn,
                localSearch: $this->localSearch,
                empresa: $this->currentEmpresa(),
            ))->build();
        }

        return (new ProductListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            empresa: $this->currentEmpresa(),
        ))->build();
    }

    protected function currentEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.produtos.screen'),
                EmbeddedTable::make()
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

        $this->redirect(ProductResource::getUrl('create'));
    }

    public function editProduct(): void
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

        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        $this->redirect(ProductResource::getUrl('edit', ['record' => $recordId]));
    }

    public function deleteProduct(): void
    {
        if (! $this->erpAuthorizeOrNotify('produtos.delete')) {
            return;
        }

        if ($this->isSeriaisView()) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

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

        $this->resetTable();
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

        $this->redirect(ProductResource::getUrl('edit', ['record' => $clone]));
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

        $builder = new ProductListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            empresa: $this->currentEmpresa(),
            orderBy: 'descricao',
        );

        $params = array_filter(
            $builder->reportFilters(),
            fn ($value): bool => filled($value),
        );

        $url = route('erp.reports.produtos-estoque', $params);

        $this->redirect($url, navigate: false);
    }
}
