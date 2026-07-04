<?php

namespace App\Filament\Resources\ContaPagarResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\ContaPagarResource;
use App\Models\ContaPagar;
use App\Models\Person;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListContasPagar extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = ContaPagarResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'numero';

    #[Url(as: 'fornecedor')]
    public string $fornecedorFilter = 'todos';

    #[Url(as: 'situacao')]
    public string $situacaoFilter = 'todos';

    public string $viewTab = 'titulos';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Contas a Pagar');

        if ($this->periodoDe === '') {
            $this->periodoDe = now()->startOfMonth()->format('Y-m-d');
        }

        if ($this->periodoAte === '') {
            $this->periodoAte = now()->format('Y-m-d');
        }

        if ($this->periodoDeApplied === '') {
            $this->periodoDeApplied = $this->periodoDe;
        }

        if ($this->periodoAteApplied === '') {
            $this->periodoAteApplied = $this->periodoAte;
        }
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-pagar-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma conta';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-pagar__search-text',
            'create' => 'createConta',
            'edit' => 'editConta',
            'delete' => 'deleteConta',
            'extraKeys' => [
                'F4' => ['method' => 'modulePending', 'params' => ['Imprimir']],
                'F7' => ['method' => 'baixarConta'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(ContaPagarResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['fornecedor']);

        if ($this->fornecedorFilter !== 'todos' && is_numeric($this->fornecedorFilter)) {
            $query->where('fornecedor_id', (int) $this->fornecedorFilter);
        }

        if (filled($this->periodoDeApplied)) {
            $query->whereDate('vencimento', '>=', $this->periodoDeApplied);
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereDate('vencimento', '<=', $this->periodoAteApplied);
        }

        match ($this->situacaoFilter) {
            'a_pagar' => $query->where('saldo', '>', 0)->whereDate('vencimento', '>=', now()->toDateString()),
            'atrasadas' => $query->where('saldo', '>', 0)->whereDate('vencimento', '<', now()->toDateString()),
            'pagas' => $query->where('saldo', '<=', 0),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return [
            'numero', 'emissao', 'produto', 'documento', 'fornecedor', 'vencimento',
            'valor', 'desconto', 'juros', 'valor_pago', 'pago_em', 'saldo',
        ];
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)
            ? $this->searchColumn
            : 'numero';

        $like = '%' . $term . '%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $like),
            'emissao', 'vencimento', 'pago_em' => $this->applyLocalSearchByDate($query, $term, $column),
            'produto' => $query->where('produto', 'like', $like),
            'documento' => $query->where('documento', 'like', $like),
            'fornecedor' => $query->whereHas('fornecedor', fn (Builder $fornecedorQuery): Builder => $fornecedorQuery->where('nome_razao', 'like', $like)),
            'valor', 'desconto', 'juros', 'valor_pago', 'saldo' => $this->applyLocalSearchByMoney($query, $term, $column),
        };
    }

    protected function applyLocalSearchByDate(Builder $query, string $term, string $column): void
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $term, $matches)) {
            $query->whereDate($column, "{$matches[3]}-{$matches[2]}-{$matches[1]}");

            return;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $term)) {
            $query->whereDate($column, $term);

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("strftime('%d/%m/%Y', {$column}) LIKE ?", ['%' . $term . '%']);

            return;
        }

        $query->whereRaw("DATE_FORMAT({$column}, '%d/%m/%Y') LIKE ?", ['%' . $term . '%']);
    }

    protected function applyLocalSearchByMoney(Builder $query, string $term, string $column): void
    {
        $normalized = str_replace(['R$', ' '], '', $term);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            if ($this->databaseDriver($query) === 'sqlite') {
                $query->whereRaw("CAST({$column} AS TEXT) LIKE ?", ['%' . $normalized . '%']);

                return;
            }

            $query->where($column, 'like', '%' . $normalized . '%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', {$column}), '.', ',') LIKE ?", ['%' . $term . '%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT({$column}, 2), '.', ',') LIKE ?", ['%' . $term . '%']);
    }

    protected function databaseDriver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
    }

    #[Computed]
    public function fornecedoresOptions(): array
    {
        return Person::query()
            ->where('is_fornecedor', true)
            ->where('ativo', true)
            ->orderBy('nome_razao')
            ->pluck('nome_razao', 'id')
            ->all();
    }

    #[Computed]
    public function totalAPagar(): float
    {
        return (float) $this->buildListQuery()->sum('saldo');
    }

    #[Computed]
    public function totalPago(): float
    {
        return (float) $this->buildListQuery()->sum('valor_pago');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.pagar.screen'),
                View::make('filament.components.erp.pagar.hint'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.pagar.footer-summary'),
                View::make('filament.components.erp.pagar.action-bar'),
            ]);
    }

    public function applyPeriodFilter(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Período filtrado.')
            ->success()
            ->send();
    }

    public function updatedFornecedorFilter(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedInformarPeriodo(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function setSituacaoFilter(string $filter): void
    {
        $allowed = ['todos', 'a_pagar', 'atrasadas', 'pagas'];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->situacaoFilter = $filter;
        $this->clearListSelection();
        $this->resetTable();
    }

    public function setViewTab(string $tab): void
    {
        if ($tab === 'desdobramentos') {
            $this->modulePending('Desdobramentos de Parcelas');

            return;
        }

        $this->viewTab = 'titulos';
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function createConta(): void
    {
        $this->modulePending('Cadastro de conta a pagar (Fase 2)');
    }

    public function editConta(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $this->modulePending('Alteração de conta a pagar (Fase 2)');
    }

    public function deleteConta(): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        ContaPagar::query()->whereKey($recordId)->delete();

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Conta excluída.')
            ->success()
            ->send();
    }

    public function baixarConta(): void
    {
        if (! $this->highlightedRecordIdOrNotify('baixar')) {
            return;
        }

        $this->modulePending('Baixa de conta (Fase 2)');
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'baixar' => 'uma conta para baixar',
            default => $this->defaultErpListSelectPrompt($action),
        };
    }
}
