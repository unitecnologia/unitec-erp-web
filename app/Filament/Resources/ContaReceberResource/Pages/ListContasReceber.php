<?php

namespace App\Filament\Resources\ContaReceberResource\Pages;

use App\Filament\Resources\ContaReceberResource\Pages\Concerns\ManagesContaReceberBaixaModal;
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

    /** @var array<int, string> */
    public array $selecionadosParaBaixa = [];

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Contas a Receber');

        if (! in_array($this->searchColumn, [
            'numero', 'emissao', 'historico', 'documento', 'cliente', 'vencimento',
            'valor', 'numero_cheque', 'desconto', 'juros', 'valor_recebido', 'recebido_em', 'saldo',
        ], true)) {
            $this->searchColumn = 'cliente';
        }

        // Sem filtro de período padrão: campos vazios = listar todos os títulos.
        // O usuário aplica o intervalo explicitamente em "Filtrar Período".
    }

    public function mountInteractsWithTable(): void
    {
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

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-receber__input',
            'create' => 'createConta',
            'edit' => 'editConta',
            'delete' => 'deleteConta',
            'extraKeys' => [
                'F4' => ['method' => 'printContasReceber'],
                'F8' => ['method' => 'baixarConta'],
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
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.receber.screen'),
                View::make('filament.components.erp.receber.table-host')
                    ->columnSpanFull(),
                View::make('filament.components.erp.receber.footer-summary'),
                View::make('filament.components.erp.receber.action-bar'),
                View::make('filament.components.erp.receber.view-modal'),
                View::make('filament.components.erp.receber.baixa-modal'),
                View::make('filament.components.erp.receber.form-modal'),
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
        $this->pushContaReceberListRefresh();
    }

    public function setViewTab(string $tab): void
    {
        if ($tab === 'desdobramentos') {
            $this->modulePending('Desdobramentos de Parcelas');

            return;
        }

        $this->viewTab = 'dados';
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'cliente';
        $this->clienteFilter = 'todos';
        $this->closeLocalClienteLookup();
        $this->clearListSelection();
        $this->pushContaReceberListRefresh(resetSort: true);
    }

    protected function onLocalClienteConfirmed(Person $person): void
    {
        if ($this->searchColumn !== 'cliente') {
            return;
        }

        $this->clienteFilter = (string) $person->id;
        $this->selecionadosParaBaixa = [];
    }

    protected function onLocalSearchChanged(string $value): void
    {
        if ($this->searchColumn !== 'cliente') {
            return;
        }

        if (trim($value) === '' || $this->localClienteLookupOpen) {
            $this->clienteFilter = 'todos';
            $this->selecionadosParaBaixa = [];
        }
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clienteFilter = 'todos';
        $this->closeLocalClienteLookup();
        $this->clearListSelection();
        $this->pushContaReceberListRefresh(resetSort: true);
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
            default => $this->defaultErpListSelectPrompt($action),
        };
    }

    public function printContasReceber(): void
    {
        if (! $this->erpAuthorizeOrNotify('contas_receber.print')) {
            return;
        }

        // F4 gera o relatório de cartões, respeitando situação/período/cliente da tela.
        $builder = new ContaReceberListQueryBuilder(
            situacaoFilter: $this->situacaoFilter,
            formaFilter: 'cartao',
            clienteFilter: $this->clienteFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDe: $this->periodoDeApplied,
            periodoAte: $this->periodoAteApplied,
        );

        $params = array_filter(
            $builder->reportFilters(),
            fn ($value): bool => filled($value),
        );

        $this->redirect(route('erp.reports.contas-receber-cartoes', $params), navigate: false);
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
        $this->pushContaReceberListRefreshSelectionOnly();
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

    protected function pushContaReceberListRefresh(bool $resetSort = false): void
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
        )->to(ContaReceberListTable::class);

        $this->skipRender();

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
