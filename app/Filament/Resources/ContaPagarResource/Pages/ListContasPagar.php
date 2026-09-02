<?php

namespace App\Filament\Resources\ContaPagarResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Concerns\InteractsWithLocalFornecedorSearchLookup;
use App\Filament\Resources\ContaPagarResource;
use App\Filament\Resources\ContaPagarResource\Pages\Concerns\ManagesContaPagarBaixaModal;
use App\Filament\Resources\ContaPagarResource\Pages\Concerns\ManagesContaPagarDesdobramentos;
use App\Filament\Resources\ContaPagarResource\Pages\Concerns\ManagesContaPagarFormModal;
use App\Livewire\Erp\ContaPagarListTable;
use App\Models\ContaPagar;
use App\Models\Person;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Queries\ContaPagarListQueryBuilder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListContasPagar extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use InteractsWithLocalFornecedorSearchLookup;
    use ManagesContaPagarBaixaModal;
    use ManagesContaPagarDesdobramentos;
    use ManagesContaPagarFormModal;

    protected static string $resource = ContaPagarResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'vencimento';

    /** @var list<string> */
    public array $searchFieldsActive = ['fornecedor', 'vencimento'];

    /** @var array<string, string> */
    public array $localSearchByField = [];

    #[Url(as: 'situacao')]
    public string $situacaoFilter = 'todos';

    public string $viewTab = 'titulos';

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Contas a Pagar');

        $this->searchFieldsActive = $this->ensureTwoSearchFields($this->normalizedSearchFieldsActive());
        $this->searchColumn = $this->searchFieldsActive[array_key_last($this->searchFieldsActive)] ?? 'vencimento';
        $this->hydrateLocalSearchByFieldFromLegacy();

        if ($this->activeDateSearchColumn() !== null
            && ($this->localSearchDe === '' || $this->localSearchAte === '')) {
            $this->applyCurrentMonthDateFilter();
        }
    }

    public function mountInteractsWithTable(): void
    {
    }

    public function shouldSkipContaPagarFornecedorSearch(): bool
    {
        return $this->shouldSkipFornecedorSearchWhileTyping();
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
        $extra = [
            'F4' => ['method' => 'printContasPagar'],
            'F7' => ['method' => 'baixarConta'],
            'F8' => ['method' => 'verHistoricoPagamentos'],
        ];

        if ($this->viewTab === 'desdobramentos') {
            $extra = [
                'F8' => ['method' => 'pedirEstornoDesdobramento'],
            ];
        }

        return [
            'searchInput' => '.erp-pagar__search-text, .erp-pagar__search-date-from, .erp-field-dd__btn',
            'create' => 'createConta',
            'edit' => 'editConta',
            'extraKeys' => $extra,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function erpListExtraPageClasses(): array
    {
        return $this->viewTab === 'desdobramentos'
            ? ['erp-pagar-page--desdobramentos']
            : [];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(ContaPagarResource::table($table));
    }

    /**
     * @return array<int, string>
     */
    protected function erpListRecordClasses(Model $record): array
    {
        if ((float) $record->saldo <= 0) {
            return ['erp-pagar-row--paga'];
        }

        if ($record->vencimento && $record->vencimento->isBefore(now()->startOfDay())) {
            return ['erp-pagar-row--vencida'];
        }

        return [];
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function listQueryBuilder(): ContaPagarListQueryBuilder
    {
        return new ContaPagarListQueryBuilder(
            situacaoFilter: $this->situacaoFilter,
            fornecedorFilter: $this->fornecedorFilter,
            searchFieldsActive: $this->normalizedSearchFieldsActive(),
            localSearchByField: $this->localSearchByField,
            localSearchDe: $this->localSearchDe,
            localSearchAte: $this->localSearchAte,
            skipFornecedorSearch: $this->shouldSkipFornecedorSearchWhileTyping(),
        );
    }

    protected function buildListQuery(): Builder
    {
        return $this->listQueryBuilder()->buildForList();
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return [
            'numero', 'emissao', 'documento', 'fornecedor', 'vencimento',
            'valor', 'desconto', 'juros', 'valor_pago', 'pago_em', 'saldo',
        ];
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
        return $this->listQueryBuilder()->sumSaldoFiltered();
    }

    #[Computed]
    public function totalPago(): float
    {
        return $this->listQueryBuilder()->sumValorPagoFiltered();
    }

    public function content(Schema $schema): Schema
    {
        $components = [
            View::make('filament.components.erp.pagar.screen'),
        ];

        if ($this->viewTab === 'desdobramentos') {
            $components[] = View::make('filament.components.erp.pagar.desdobramentos');
        } else {
            $components[] = View::make('filament.components.erp.pagar.hint');
            $components[] = View::make('filament.components.erp.pagar.table-host')
                ->columnSpanFull();
            $components[] = View::make('filament.components.erp.pagar.footer-summary');
        }

        $components[] = View::make('filament.components.erp.pagar.action-bar');
        $components[] = View::make('filament.components.erp.pagar.historico-pagamentos-modal');
        $components[] = View::make('filament.components.erp.pagar.baixa-modal');
        $components[] = View::make('filament.components.erp.pagar.form-modal');
        $components[] = View::make('filament.components.erp.pagar.estorno-confirm-modal');

        return $schema
            ->gap(false)
            ->components($components);
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
            $this->abrirDesdobramentos();

            return;
        }

        $this->voltarParaTitulos();
    }

    public function setSearchColumn(string $column): void
    {
        $this->toggleSearchField($column);
    }

    public function updatedSearchColumn(): void
    {
        $this->toggleSearchField($this->searchColumn);
    }

    public function toggleSearchField(string $column): void
    {
        $allowed = $this->localSearchColumns();

        if (! in_array($column, $allowed, true)) {
            return;
        }

        $active = $this->ensureTwoSearchFields($this->normalizedSearchFieldsActive());

        if (in_array($column, $active, true)) {
            // Sempre mantém 2: clicar no já marcado só reordena (fica como o mais recente).
            $active = array_values(array_filter($active, fn (string $item): bool => $item !== $column));
            $active[] = $column;
            $this->searchFieldsActive = $active;
            $this->searchColumn = $column;

            return;
        }

        $active[] = $column;
        $active = array_values(array_slice($active, -2));
        $hadDate = $this->activeDateSearchColumn() !== null;

        $this->searchFieldsActive = $active;
        $this->searchColumn = $column;
        $this->pruneLocalSearchByField();

        if (! in_array('fornecedor', $active, true)) {
            $this->fornecedorFilter = 'todos';
            $this->closeLocalFornecedorLookup();
        }

        $hasDate = collect($active)->contains(fn (string $item): bool => $this->isDateSearchColumn($item));

        if ($hasDate && (! $hadDate || $this->localSearchDe === '' || $this->localSearchAte === '')) {
            $this->applyCurrentMonthDateFilter();
        }

        if (! $hasDate) {
            $this->localSearchDe = '';
            $this->localSearchAte = '';
        }

        $this->syncLegacyLocalSearch();
        $this->clearListSelection();
        $this->resetTable();
        $this->dispatch('erp-masks-refresh');
    }

    /**
     * @param  list<string>  $active
     * @return list<string>
     */
    protected function ensureTwoSearchFields(array $active): array
    {
        $allowed = $this->localSearchColumns();
        $active = array_values(array_unique(array_filter(
            $active,
            fn (mixed $column): bool => is_string($column) && in_array($column, $allowed, true),
        )));

        $defaults = ['fornecedor', 'vencimento'];

        foreach ($defaults as $default) {
            if (count($active) >= 2) {
                break;
            }

            if (! in_array($default, $active, true)) {
                $active[] = $default;
            }
        }

        foreach ($allowed as $column) {
            if (count($active) >= 2) {
                break;
            }

            if (! in_array($column, $active, true)) {
                $active[] = $column;
            }
        }

        return array_values(array_slice($active, 0, 2));
    }

    protected function pruneLocalSearchByField(): void
    {
        $active = $this->normalizedSearchFieldsActive();

        foreach (array_keys($this->localSearchByField) as $column) {
            if (! in_array($column, $active, true) || $this->isDateSearchColumn($column)) {
                unset($this->localSearchByField[$column]);
            }
        }
    }

    protected function syncLegacyLocalSearch(): void
    {
        foreach ($this->normalizedSearchFieldsActive() as $column) {
            if ($this->isDateSearchColumn($column)) {
                continue;
            }

            $this->localSearch = (string) ($this->localSearchByField[$column] ?? '');

            return;
        }

        $this->localSearch = '';
    }

    protected function hydrateLocalSearchByFieldFromLegacy(): void
    {
        if (! filled($this->localSearch)) {
            return;
        }

        foreach ($this->searchFieldsActive as $column) {
            if ($this->isDateSearchColumn($column)) {
                continue;
            }

            if (! filled($this->localSearchByField[$column] ?? null)) {
                $this->localSearchByField[$column] = $this->localSearch;
            }

            return;
        }
    }

    public function updatedLocalSearchByField(): void
    {
        $fornecedorTerm = (string) ($this->localSearchByField['fornecedor'] ?? '');

        if ($this->isLocalFornecedorSearchActive()) {
            $this->onLocalFornecedorSearchTyped($fornecedorTerm);
        } else {
            $this->closeLocalFornecedorLookup();
            $this->fornecedorFilter = 'todos';
        }

        $this->syncLegacyLocalSearch();
        $this->clearListSelection();
        $this->resetTable();
    }

    public function applyLocalDateFilter(?string $de = null, ?string $ate = null): void
    {
        // JS chama sem argumentos após syncLivewire — não zerar de/até.
        if (func_num_args() >= 1) {
            $this->localSearchDe = trim((string) $de);
        }

        if (func_num_args() >= 2) {
            $this->localSearchAte = trim((string) $ate);
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearchDe(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearchAte(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    /**
     * @return list<string>
     */
    protected function normalizedSearchFieldsActive(): array
    {
        $allowed = $this->localSearchColumns();
        $active = array_values(array_filter(
            $this->searchFieldsActive,
            fn (mixed $column): bool => is_string($column) && in_array($column, $allowed, true),
        ));

        if ($active === []) {
            $fallback = in_array($this->searchColumn, $allowed, true) ? $this->searchColumn : 'vencimento';

            return [$fallback];
        }

        return array_values(array_unique($active));
    }

    protected function activeDateSearchColumn(): ?string
    {
        foreach ($this->normalizedSearchFieldsActive() as $column) {
            if ($this->isDateSearchColumn($column)) {
                return $column;
            }
        }

        return null;
    }

    protected function isDateSearchColumn(string $column): bool
    {
        return in_array($column, ['emissao', 'vencimento', 'pago_em'], true);
    }

    protected function applyCurrentMonthDateFilter(): void
    {
        $hoje = ErpTimezone::toLocal();
        $this->localSearchDe = $hoje->copy()->startOfMonth()->toDateString();
        $this->localSearchAte = $hoje->copy()->endOfMonth()->toDateString();
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->pushContaPagarListRefresh();
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

    public bool $historicoPagamentosOpen = false;

    public string $historicoPagamentosTitulo = '';

    /** @var array<int, array<string, string>> */
    public array $historicoPagamentosRows = [];

    public function verHistoricoPagamentos(): void
    {
        $id = $this->highlightedRecordIdOrNotify('baixar');

        if (! $id) {
            return;
        }

        $conta = ContaPagar::query()->with(['pagamentos.formaPagamento', 'pagamentos.planoConta', 'pagamentos.caixaConta'])->find($id);

        if (! $conta) {
            return;
        }

        $this->historicoPagamentosTitulo = 'Baixas — título '.$conta->numero;
        $this->historicoPagamentosRows = $conta->pagamentos
            ->sortBy('data')
            ->values()
            ->map(fn ($pagamento): array => [
                'data' => optional($pagamento->data)?->format('d/m/Y') ?? '—',
                'valor_pago' => number_format((float) $pagamento->valor_pago, 2, ',', '.'),
                'juros' => number_format((float) $pagamento->juros, 2, ',', '.'),
                'desconto' => number_format((float) $pagamento->desconto, 2, ',', '.'),
                'forma' => mb_strtoupper((string) ($pagamento->formaPagamento?->descricao ?? '—'), 'UTF-8'),
                'plano' => mb_strtoupper((string) ($pagamento->planoConta?->descricao ?? '—'), 'UTF-8'),
                'conta' => mb_strtoupper((string) ($pagamento->caixaConta?->nome ?? '—'), 'UTF-8'),
            ])
            ->all();

        $this->historicoPagamentosOpen = true;
    }

    public function closeHistoricoPagamentos(): void
    {
        $this->historicoPagamentosOpen = false;
        $this->historicoPagamentosTitulo = '';
        $this->historicoPagamentosRows = [];
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'baixar' => 'uma conta para baixar',
            'desdobramentos' => 'um título pago para ver desdobramentos',
            default => $this->defaultErpListSelectPrompt($action),
        };
    }

    public function printContasPagar(): void
    {
        if (! $this->erpAuthorizeOrNotify('contas_pagar.print')) {
            return;
        }

        $situacao = match ($this->situacaoFilter) {
            'a_pagar' => 'abertos',
            'atrasadas' => 'vencidos',
            'pagas' => 'baixados',
            default => 'todos',
        };

        $params = array_filter([
            'situacao' => $situacao,
            'de' => filled($this->localSearchDe) ? $this->localSearchDe : null,
            'ate' => filled($this->localSearchAte) ? $this->localSearchAte : null,
        ], fn ($value): bool => filled($value));

        $this->redirect(route('erp.reports.tabular', [
            'slug' => 'contas-pagar',
            ...$params,
        ]), navigate: false);
    }

    public function refreshTable(): void
    {
        $this->pushContaPagarListRefresh();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    public function resetTable(): void
    {
        if ($this->viewTab === 'titulos') {
            $this->pushContaPagarListRefresh();
        }
    }

    protected function pushContaPagarListRefresh(bool $resetSort = false): void
    {
        if ($this->viewTab !== 'titulos') {
            return;
        }

        $builder = $this->listQueryBuilder();
        $totalAPagar = $builder->sumSaldoFiltered();
        $totalPago = $builder->sumValorPagoFiltered();

        $this->dispatch(
            'erp-pagar-list-refresh',
            situacaoFilter: $this->situacaoFilter,
            fornecedorFilter: $this->fornecedorFilter,
            searchFieldsActive: $this->normalizedSearchFieldsActive(),
            localSearchByField: $this->localSearchByField,
            localSearchDe: $this->localSearchDe,
            localSearchAte: $this->localSearchAte,
            skipFornecedorSearch: $this->shouldSkipFornecedorSearchWhileTyping(),
            perPage: (int) ($this->tableRecordsPerPage ?? 50),
            resetSort: $resetSort,
        )->to(ContaPagarListTable::class);

        $this->skipRender();

        $this->patchPagarFooterTotals($totalAPagar, $totalPago);
    }

    protected function patchPagarFooterTotals(float $totalAPagar, float $totalPago): void
    {
        $this->js(sprintf(
            '(() => {
                const items = document.querySelectorAll(".erp-pagar__totals .erp-pagar__total-value");
                if (items[0]) items[0].textContent = %s;
                if (items[1]) items[1].textContent = %s;
            })()',
            json_encode('R$ '.number_format($totalAPagar, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($totalPago, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
        ));
    }
}
