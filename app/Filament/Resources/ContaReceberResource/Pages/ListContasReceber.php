<?php

namespace App\Filament\Resources\ContaReceberResource\Pages;

use App\Filament\Resources\ContaReceberResource\Pages\Concerns\ManagesContaReceberBaixaModal;
use App\Filament\Resources\ContaReceberResource\Pages\Concerns\ManagesContaReceberDesdobramentos;
use App\Filament\Resources\ContaReceberResource\Pages\Concerns\ManagesContaReceberBoletoModal;
use App\Filament\Resources\ContaReceberResource\Pages\Concerns\ManagesContaReceberFormModal;
use App\Filament\Resources\ContaReceberResource\Pages\Concerns\ManagesContaReceberViewModal;
use App\Filament\Concerns\InteractsWithLocalClienteSearchLookup;
use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\ContaReceberResource;
use App\Livewire\Erp\ContaReceberListTable;
use App\Models\ContaReceber;
use App\Models\Person;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaReceberExclusaoService;
use App\Support\Erp\Queries\ContaReceberListQueryBuilder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class ListContasReceber extends ListRecords
{
    use InteractsWithErpListPage;
    use \App\Filament\Concerns\InteractsWithErpPermissions;
    use InteractsWithLocalClienteSearchLookup;
    use ManagesContaReceberBaixaModal;
    use ManagesContaReceberBoletoModal;
    use ManagesContaReceberDesdobramentos;
    use ManagesContaReceberFormModal;
    use ManagesContaReceberViewModal;

    protected static string $resource = ContaReceberResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'cliente';

    #[Url(as: 'cliente')]
    public string $clienteFilter = 'todos';

    #[Url(as: 'situacao')]
    public string $situacaoFilter = 'todos';

    #[Url(as: 'forma')]
    public string $formaFilter = 'todos';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public string $viewTab = 'dados';

    /** @var list<string> */
    public array $searchFieldsActive = ['cliente'];

    private const FILTRO_SESSION_KEY = 'erp_receber_filtro';

    /** @var array<int, string> */
    public array $selecionadosParaBaixa = [];

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Contas a Receber');

        $saved = session(self::FILTRO_SESSION_KEY);
        $saved = is_array($saved) ? $saved : [];
        $urlTemFiltro = request()->hasAny(['q', 'campo', 'cliente', 'situacao', 'forma']);

        if (! $urlTemFiltro && $saved !== []) {
            $this->applySavedReceberFiltro($saved);
        }

        if (! in_array($this->searchColumn, $this->receberSearchColumns(), true)) {
            $this->searchColumn = 'cliente';
        }

        $this->searchFieldsActive = $this->normalizeReceberSearchFields($this->searchFieldsActive);

        if (
            $urlTemFiltro
            && ($saved['searchColumn'] ?? null) === $this->searchColumn
            && ($saved['localSearch'] ?? null) === $this->localSearch
            && is_array($saved['searchFieldsActive'] ?? null)
        ) {
            $this->searchFieldsActive = $this->normalizeReceberSearchFields($saved['searchFieldsActive']);
        }

        if ($this->searchFieldsActive === [] || ! in_array($this->searchColumn, $this->searchFieldsActive, true)) {
            $this->searchFieldsActive = [$this->searchColumn];
        }

        $this->restoreClienteConfirmado();
    }

    public function mountInteractsWithTable(): void
    {
    }

    public function updated(string $property): void
    {
        $root = strstr($property, '.', true) ?: $property;

        if (! in_array($root, [
            'localSearch', 'searchColumn', 'searchFieldsActive', 'clienteFilter',
            'situacaoFilter', 'formaFilter', 'periodoDe', 'periodoAte',
            'periodoDeApplied', 'periodoAteApplied',
        ], true)) {
            return;
        }

        $this->rememberReceberFiltro();
    }

    /**
     * @return list<string>
     */
    protected function receberSearchColumns(): array
    {
        return [
            'emissao', 'historico', 'documento', 'cliente', 'vencimento',
            'valor', 'numero_cheque', 'desconto', 'juros', 'valor_recebido', 'recebido_em', 'saldo',
        ];
    }

    /**
     * @param  array<mixed>  $fields
     * @return list<string>
     */
    protected function normalizeReceberSearchFields(array $fields): array
    {
        $allowed = [
            'emissao', 'historico', 'documento', 'cliente', 'vencimento',
            'valor', 'desconto', 'juros', 'valor_recebido', 'recebido_em', 'saldo',
        ];

        return array_values(array_unique(array_filter(
            $fields,
            fn (mixed $column): bool => is_string($column) && in_array($column, $allowed, true),
        )));
    }

    /**
     * @param  array<string, mixed>  $saved
     */
    protected function applySavedReceberFiltro(array $saved): void
    {
        if (is_string($saved['searchColumn'] ?? null)) {
            $this->searchColumn = $saved['searchColumn'];
        }

        if (is_array($saved['searchFieldsActive'] ?? null)) {
            $this->searchFieldsActive = $saved['searchFieldsActive'];
        }

        if (is_string($saved['localSearch'] ?? null)) {
            $this->localSearch = $saved['localSearch'];
        }

        if (is_string($saved['clienteFilter'] ?? null) && $saved['clienteFilter'] !== '') {
            $this->clienteFilter = $saved['clienteFilter'];
        }

        if (in_array($saved['situacaoFilter'] ?? null, ['todos', 'a_receber', 'atrasadas', 'recebidas'], true)) {
            $this->situacaoFilter = $saved['situacaoFilter'];
        }

        if (in_array($saved['formaFilter'] ?? null, [
            'todos',
            ContaReceber::FORMA_CARTEIRA,
            ContaReceber::FORMA_CHEQUE,
            ContaReceber::FORMA_CARTAO,
            ContaReceber::FORMA_BOLETO,
        ], true)) {
            $this->formaFilter = $saved['formaFilter'];
        }

        foreach (['periodoDe', 'periodoAte', 'periodoDeApplied', 'periodoAteApplied'] as $field) {
            if (is_string($saved[$field] ?? null)) {
                $this->{$field} = $saved[$field];
            }
        }
    }

    protected function restoreClienteConfirmado(): void
    {
        if ($this->searchFieldsActive !== ['cliente'] || ! is_numeric($this->clienteFilter)) {
            return;
        }

        $this->localClienteConfirmedTerm = mb_strtoupper(trim($this->localSearch), 'UTF-8');
    }

    protected function rememberReceberFiltro(): void
    {
        session([self::FILTRO_SESSION_KEY => [
            'searchFieldsActive' => $this->normalizeReceberSearchFields($this->searchFieldsActive),
            'searchColumn' => $this->searchColumn,
            'localSearch' => $this->localSearch,
            'clienteFilter' => $this->clienteFilter,
            'situacaoFilter' => $this->situacaoFilter,
            'formaFilter' => $this->formaFilter,
            'periodoDe' => $this->periodoDe,
            'periodoAte' => $this->periodoAte,
            'periodoDeApplied' => $this->periodoDeApplied,
            'periodoAteApplied' => $this->periodoAteApplied,
        ]]);
    }

    public function shouldSkipContaReceberLocalSearch(): bool
    {
        return $this->shouldSkipLocalSearchWhileTyping();
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-receber-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma conta';
    }

    /**
     * @return array<int, string>
     */
    protected function erpListExtraPageClasses(): array
    {
        return $this->viewTab === 'desdobramentos'
            ? ['erp-receber-page--desdobramentos']
            : [];
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-receber__input',
            'create' => 'createConta',
            'edit' => 'editConta',
            'delete' => 'deleteConta',
            'extraKeys' => $this->viewTab === 'desdobramentos'
                ? [
                    'F11' => ['method' => 'pedirEstornoDesdobramento'],
                ]
                : [
                    'F4' => ['method' => 'printContasReceber'],
                    'F8' => ['method' => 'baixarConta'],
                    'F11' => ['method' => 'pedirEstornoDesdobramento'],
                ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(ContaReceberResource::table($table));
    }

    /**
     * @return array<int, string>
     */
    protected function erpListRecordClasses(Model $record): array
    {
        if ((float) $record->saldo <= 0) {
            return ['erp-receber-row--recebida'];
        }

        if ($record->vencimento && $record->vencimento->isBefore(now()->startOfDay())) {
            return ['erp-receber-row--vencida'];
        }

        return [];
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function listQueryBuilder(): ContaReceberListQueryBuilder
    {
        return new ContaReceberListQueryBuilder(
            situacaoFilter: $this->situacaoFilter,
            formaFilter: $this->formaFilter,
            clienteFilter: $this->clienteFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDe: $this->periodoDeApplied,
            periodoAte: $this->periodoAteApplied,
            skipLocalSearch: $this->shouldSkipLocalSearchWhileTyping(),
            searchFieldsActive: $this->searchFieldsActive,
        );
    }

    protected function buildListQuery(): Builder
    {
        return $this->listQueryBuilder()->buildForList();
    }

    #[Computed]
    public function totalAReceber(): float
    {
        return $this->listQueryBuilder()->sumSaldoFiltered();
    }

    #[Computed]
    public function totalRecebido(): float
    {
        return $this->listQueryBuilder()->sumValorRecebidoFiltered();
    }

    #[Computed]
    public function totalSelecionado(): float
    {
        $ids = collect($this->selecionadosParaBaixa)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($ids === []) {
            return 0.0;
        }

        return (float) ContaReceber::query()
            ->whereIn('id', $ids)
            ->sum('saldo');
    }

    #[Computed]
    public function quantidadeSelecionada(): int
    {
        return count($this->selecionadosParaBaixa);
    }

    #[Computed]
    public function podeExcluirContaDestacada(): bool
    {
        $conta = $this->contaDestacada();

        return $conta instanceof ContaReceber
            && app(ContaReceberExclusaoService::class)->podeExcluir($conta);
    }

    #[Computed]
    public function exclusaoContaTooltip(): string
    {
        if (! $this->highlightedRecordId) {
            return 'Selecione uma conta na lista';
        }

        $conta = ContaReceber::query()->find($this->highlightedRecordId);

        if (! $conta) {
            return 'Selecione uma conta na lista';
        }

        $service = app(ContaReceberExclusaoService::class);

        if ($service->podeExcluir($conta)) {
            return 'Excluir conta avulsa selecionada';
        }

        return $service->motivoBloqueio($conta) ?? 'Não é possível excluir esta conta';
    }

    protected function contaDestacada(): ?ContaReceber
    {
        if (! $this->highlightedRecordId) {
            return null;
        }

        return ContaReceber::query()->find($this->highlightedRecordId);
    }

    public function content(Schema $schema): Schema
    {
        $components = [
            View::make('filament.components.erp.receber.screen'),
        ];

        if ($this->viewTab === 'desdobramentos') {
            $components[] = View::make('filament.components.erp.receber.desdobramentos');
        } else {
            $components[] = View::make('filament.components.erp.receber.table-host')
                ->columnSpanFull();
            $components[] = View::make('filament.components.erp.receber.footer-summary');
        }

        $components[] = View::make('filament.components.erp.receber.action-bar');
        $components[] = View::make('filament.components.erp.receber.view-modal');
        $components[] = View::make('filament.components.erp.receber.baixa-modal');
        $components[] = View::make('filament.components.erp.receber.estorno-confirm-modal');
        $components[] = View::make('filament.components.erp.receber.form-modal');

        return $schema
            ->gap(false)
            ->components([
                ...$components,
                View::make('filament.components.erp.receber.boleto-vencimento-progress'),
                View::make('filament.components.erp.receber.boleto-emit-progress'),
                View::make('filament.components.erp.receber.boleto-vencimento-sucesso-overlay'),
                View::make('filament.components.erp.receber.boleto-vencimento-erro-overlay'),
                View::make('filament.components.erp.receber.boleto-forma-bloqueio-overlay'),
                View::make('filament.components.erp.receber.boleto-conta-modal'),
                View::make('filament.components.erp.receber.boleto-sucesso-overlay'),
                View::make('filament.components.erp.receber.boleto-enviar-modal'),
            ]);
    }

    public function applyPeriodoFilter(string $de = '', string $ate = ''): void
    {
        $this->periodoDe = trim($de);
        $this->periodoAte = trim($ate);
        $this->syncAppliedPeriodFilter();
    }

    public function updatedPeriodoDe(): void
    {
        $this->syncAppliedPeriodFilter();
    }

    public function updatedPeriodoAte(): void
    {
        $this->syncAppliedPeriodFilter();
    }

    protected function syncAppliedPeriodFilter(): void
    {
        $this->periodoDeApplied = trim($this->periodoDe);
        $this->periodoAteApplied = trim($this->periodoAte);
        $this->clearListSelection();
        $this->rememberReceberFiltro();
        $this->pushContaReceberListRefresh();
    }

    public function setSituacaoFilter(string $filter): void
    {
        $allowed = ['todos', 'a_receber', 'atrasadas', 'recebidas'];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->situacaoFilter = $filter;
        $this->clearListSelection();
        $this->rememberReceberFiltro();
        $this->pushContaReceberListRefresh();
    }

    public function setFormaFilter(string $filter): void
    {
        $allowed = [
            'todos',
            ContaReceber::FORMA_CARTEIRA,
            ContaReceber::FORMA_CHEQUE,
            ContaReceber::FORMA_CARTAO,
            ContaReceber::FORMA_BOLETO,
        ];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->formaFilter = $filter;
        $this->clearListSelection();
        $this->rememberReceberFiltro();
        $this->pushContaReceberListRefresh();
    }

    public function setViewTab(string $tab): void
    {
        if ($tab === 'desdobramentos') {
            $this->abrirDesdobramentos();

            return;
        }

        $this->voltarParaTitulos();
    }

    public function isLocalClienteSearchColumn(): bool
    {
        return $this->searchFieldsActive === ['cliente'];
    }

    public function toggleSearchField(string $column): void
    {
        $allowed = [
            'emissao', 'historico', 'documento', 'cliente', 'vencimento',
            'valor', 'desconto', 'juros', 'valor_recebido', 'recebido_em', 'saldo',
        ];

        if (! in_array($column, $allowed, true)) {
            return;
        }

        $active = array_values(array_filter(
            $this->searchFieldsActive,
            fn (mixed $item): bool => is_string($item) && in_array($item, $allowed, true),
        ));

        if (in_array($column, $active, true)) {
            if (count($active) === 1) {
                return;
            }

            $active = array_values(array_filter(
                $active,
                fn (string $item): bool => $item !== $column,
            ));
        } else {
            $active[] = $column;
        }

        $previous = $this->searchColumn;
        $this->searchFieldsActive = $active;
        $this->searchColumn = $active[array_key_last($active)];
        $this->syncClienteLookupWithSearchFields();

        $this->rememberReceberFiltro();

        if ($this->searchColumn === $previous) {
            $this->clearListSelection();
            $this->pushContaReceberListRefresh();
        }
    }

    protected function syncClienteLookupWithSearchFields(): void
    {
        if ($this->isLocalClienteSearchColumn()) {
            return;
        }

        $this->clienteFilter = 'todos';
        $this->localClienteConfirmedTerm = '';
        $this->closeLocalClienteLookup();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchFieldsActive = ['cliente'];
        $this->searchColumn = 'cliente';
        $this->clienteFilter = 'todos';
        $this->localClienteConfirmedTerm = '';
        $this->closeLocalClienteLookup();
        $this->clearListSelection();
        $this->rememberReceberFiltro();
        $this->pushContaReceberListRefresh(resetSort: true);
    }

    protected function onLocalClienteConfirmed(Person $person): void
    {
        if ($this->searchColumn !== 'cliente') {
            return;
        }

        $this->clienteFilter = (string) $person->id;
        $this->selecionadosParaBaixa = [];
        $this->rememberReceberFiltro();
    }

    protected function onLocalSearchChanged(string $value): void
    {
        if ($this->searchColumn !== 'cliente') {
            return;
        }

        // Já confirmou este cliente: não zera o filtro.
        if (
            $this->localClienteConfirmedTerm !== ''
            && mb_strtoupper(trim($value), 'UTF-8') === $this->localClienteConfirmedTerm
            && is_numeric($this->clienteFilter)
        ) {
            return;
        }

        if (trim($value) === '' || $this->localClienteLookupOpen) {
            $this->clienteFilter = 'todos';
            $this->selecionadosParaBaixa = [];
        }
    }

    public function updatedSearchColumn(): void
    {
        $this->syncClienteLookupWithSearchFields();
        $this->clearListSelection();
        $this->pushContaReceberListRefresh();
    }

    protected function clearListSelection(): void
    {
        $this->highlightedRecordId = null;
        $this->selecionadosParaBaixa = [];
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->pushContaReceberListRefresh();
    }

    public function deleteConta(): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $conta = ContaReceber::query()->find($recordId);

        if (! $conta) {
            Notification::make()
                ->title('Conta não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $service = app(ContaReceberExclusaoService::class);

        if (! $service->podeExcluir($conta)) {
            Notification::make()
                ->title('Não é possível excluir')
                ->body($service->motivoBloqueio($conta))
                ->warning()
                ->send();

            return;
        }

        $conta->delete();

        $this->clearListSelection();
        $this->pushContaReceberListRefresh();

        Notification::make()
            ->title('Conta excluída.')
            ->success()
            ->send();
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'baixar' => 'uma conta para baixar',
            'desdobramentos' => 'um título para ver as baixas',
            'gerar boleto' => 'uma conta para gerar boleto',
            default => $this->defaultErpListSelectPrompt($action),
        };
    }

    public function printContasReceber(): void
    {
        if (! $this->erpAuthorizeOrNotify('contas_receber.print')) {
            return;
        }

        $builder = new ContaReceberListQueryBuilder(
            situacaoFilter: $this->situacaoFilter,
            formaFilter: $this->formaFilter,
            clienteFilter: $this->clienteFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDe: $this->periodoDeApplied,
            periodoAte: $this->periodoAteApplied,
            searchFieldsActive: $this->searchFieldsActive,
        );

        $params = array_filter(
            $builder->reportFilters(),
            fn ($value): bool => filled($value),
        );

        foreach ($this->searchFieldsActive as $campo) {
            $params['campos'][] = $campo;
        }

        $this->redirect(route('erp.reports.contas-receber', $params), navigate: false);
    }

    #[On('erp-receber-open-view')]
    public function onErpReceberOpenView(int $contaId): void
    {
        $this->openContaReceberView($contaId);
    }

    #[On('erp-receber-toggle-baixa')]
    public function onErpReceberToggleBaixa(int $contaId, bool $selected): void
    {
        $ids = collect($this->selecionadosParaBaixa)->map(fn ($id): int => (int) $id);

        if ($selected) {
            $ids->push($contaId);
        } else {
            $ids = $ids->reject(fn (int $id): bool => $id === $contaId);
        }

        $this->selecionadosParaBaixa = $ids->unique()->values()->all();

        // Só sincroniza estado + rodapé — sem redesenhar a grade (azul já no clique).
        $this->dispatch(
            'erp-receber-selection-sync',
            selecionadosParaBaixa: $this->selecionadosParaBaixa,
        )->to(ContaReceberListTable::class);

        $this->skipRender();
        $this->patchReceberFooterSelecionado();
    }

    public function refreshTable(): void
    {
        $this->pushContaReceberListRefresh();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    public function resetTable(): void
    {
        $this->pushContaReceberListRefresh();
    }

    /**
     * @param  bool  $skipPageRender  false quando a página precisa redesenhar overlays/modais
     */
    protected function pushContaReceberListRefresh(bool $resetSort = false, bool $skipPageRender = true): void
    {
        $builder = $this->listQueryBuilder();
        $totalAReceber = $builder->sumSaldoFiltered();
        $totalRecebido = $builder->sumValorRecebidoFiltered();

        $this->dispatch(
            'erp-receber-list-refresh',
            situacaoFilter: $this->situacaoFilter,
            formaFilter: $this->formaFilter,
            clienteFilter: $this->clienteFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
            skipLocalSearch: $this->shouldSkipLocalSearchWhileTyping(),
            perPage: (int) ($this->tableRecordsPerPage ?? 50),
            selecionadosParaBaixa: $this->selecionadosParaBaixa,
            resetSort: $resetSort,
            searchFieldsActive: $this->searchFieldsActive,
        )->to(ContaReceberListTable::class);

        if ($skipPageRender) {
            $this->skipRender();
        }

        $this->patchReceberFooterTotals($totalAReceber, $totalRecebido);
        $this->patchReceberFooterSelecionado();
    }

    protected function pushContaReceberListRefreshSelectionOnly(): void
    {
        $this->dispatch(
            'erp-receber-list-refresh',
            situacaoFilter: $this->situacaoFilter,
            formaFilter: $this->formaFilter,
            clienteFilter: $this->clienteFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
            skipLocalSearch: $this->shouldSkipLocalSearchWhileTyping(),
            perPage: (int) ($this->tableRecordsPerPage ?? 50),
            selecionadosParaBaixa: $this->selecionadosParaBaixa,
            resetSort: false,
            searchFieldsActive: $this->searchFieldsActive,
        )->to(ContaReceberListTable::class);

        $this->skipRender();
        $this->patchReceberFooterSelecionado();
    }

    protected function patchReceberFooterTotals(float $totalAReceber, float $totalRecebido): void
    {
        $this->js(sprintf(
            '(() => {
                const items = document.querySelectorAll(".erp-receber__totals .erp-receber__total-value");
                if (items[0]) items[0].textContent = %s;
                if (items[1]) items[1].textContent = %s;
            })()',
            json_encode('R$ '.number_format($totalAReceber, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($totalRecebido, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
        ));
    }

    protected function patchReceberFooterSelecionado(): void
    {
        $qtd = $this->quantidadeSelecionada;
        $total = $this->totalSelecionado;
        $label = $qtd === 1 ? 'conta' : 'contas';

        $this->js(sprintf(
            '(() => {
                const totals = document.querySelector(".erp-receber__totals");
                if (!totals) return;
                let block = totals.querySelector(".erp-receber__total-item--selected");
                if (%d < 1) {
                    if (block) block.remove();
                    return;
                }
                const formatted = %s;
                const meta = "(%d " + %s + ")";
                if (!block) {
                    block = document.createElement("div");
                    block.className = "erp-receber__total-item erp-receber__total-item--selected";
                    block.innerHTML = "<span class=\\"erp-receber__total-label\\">TOTAL SELECIONADO |</span>"
                        + "<span class=\\"erp-receber__total-value erp-receber__total-value--selected\\"></span>"
                        + "<span class=\\"erp-receber__total-meta\\"></span>";
                    totals.appendChild(block);
                }
                const valueEl = block.querySelector(".erp-receber__total-value--selected");
                const metaEl = block.querySelector(".erp-receber__total-meta");
                if (valueEl) valueEl.textContent = formatted;
                if (metaEl) metaEl.textContent = meta;
            })()',
            $qtd,
            json_encode('R$ '.number_format($total, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            $qtd,
            json_encode($label, JSON_UNESCAPED_UNICODE),
        ));
    }
}
