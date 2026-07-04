<?php

namespace App\Filament\Resources\ContaReceberResource\Pages;

use App\Filament\Resources\ContaReceberResource\Pages\Concerns\ManagesContaReceberViewModal;
use App\Filament\Concerns\InteractsWithLocalClienteSearchLookup;
use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\ContaReceberResource;
use App\Models\ContaReceber;
use App\Models\Person;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaReceberExclusaoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListContasReceber extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithLocalClienteSearchLookup;
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

        if (! in_array($this->searchColumn, $this->localSearchColumns(), true)) {
            $this->searchColumn = 'cliente';
        }

        // Sem filtro de período padrão: campos vazios = listar todos os títulos.
        // O usuário aplica o intervalo explicitamente em "Filtrar Período".
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
                'F4' => ['method' => 'modulePending', 'params' => ['Imprimir']],
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
        return (float) $record->saldo > 0
            && $record->vencimento
            && $record->vencimento->isBefore(now()->startOfDay())
                ? ['erp-receber-row--vencida']
                : [];
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['cliente']);

        if ($this->clienteFilter !== 'todos' && is_numeric($this->clienteFilter)) {
            $query->where('cliente_id', (int) $this->clienteFilter);
        }

        $this->applyPeriodFilters($query);

        $hoje = ErpTimezone::toLocal()->toDateString();

        match ($this->situacaoFilter) {
            'a_receber' => $query->where('saldo', '>', 0)->whereDate('vencimento', '>=', $hoje),
            'atrasadas' => $query->where('saldo', '>', 0)->whereDate('vencimento', '<', $hoje),
            'recebidas' => $query->where('saldo', '<=', 0),
            default => $query,
        };

        if ($this->formaFilter !== 'todos' && array_key_exists($this->formaFilter, ContaReceber::formaLabels())) {
            $query->where('forma', $this->formaFilter);
        }

        if (filled($this->localSearch) && ! $this->shouldSkipLocalSearchWhileTyping()) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    /**
     * Filtra por vencimento somente quando o usuário aplicou um período
     * (campos "de/até" preenchidos + botão Filtrar Período).
     */
    protected function applyPeriodFilters(Builder $query): void
    {
        if (filled($this->periodoDeApplied)) {
            $query->whereDate('vencimento', '>=', $this->periodoDeApplied);
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereDate('vencimento', '<=', $this->periodoAteApplied);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return [
            'numero', 'emissao', 'historico', 'documento', 'cliente', 'vencimento',
            'valor', 'desconto', 'juros', 'valor_recebido', 'recebido_em', 'saldo',
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
            : 'cliente';

        $like = '%' . $term . '%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $like),
            'emissao', 'vencimento', 'recebido_em' => $this->applyLocalSearchByDate($query, $term, $column),
            'historico' => $query->where('historico', 'like', $like),
            'documento' => $query->where('documento', 'like', $like),
            'cliente' => $query->whereHas('cliente', fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $like)),
            'valor', 'desconto', 'juros', 'valor_recebido', 'saldo' => $this->applyLocalSearchByMoney($query, $term, $column),
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
    public function totalAReceber(): float
    {
        return (float) $this->buildListQuery()->sum('saldo');
    }

    #[Computed]
    public function totalRecebido(): float
    {
        return (float) $this->buildListQuery()->sum('valor_recebido');
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
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.receber.footer-summary'),
                View::make('filament.components.erp.receber.action-bar'),
                View::make('filament.components.erp.receber.view-modal'),
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
        $this->resetTable();
    }

    public function setSituacaoFilter(string $filter): void
    {
        $allowed = ['todos', 'a_receber', 'atrasadas', 'recebidas'];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->situacaoFilter = $filter;
        $this->clearListSelection();
        $this->resetTable();
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
        $this->resetTable();
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
        $this->resetTable();
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
        $this->resetTable();
    }

    protected function clearListSelection(): void
    {
        $this->highlightedRecordId = null;
        $this->selecionadosParaBaixa = [];
    }

    public function createConta(): void
    {
        $this->modulePending('Cadastro de conta a receber (Fase 2)');
    }

    public function editConta(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $this->modulePending('Alteração de conta a receber (Fase 2)');
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
        $this->resetTable();

        Notification::make()
            ->title('Conta excluída.')
            ->success()
            ->send();
    }

    public function baixarConta(): void
    {
        $ids = collect($this->selecionadosParaBaixa)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($ids !== []) {
            if ($this->clienteFilter === 'todos' || ! is_numeric($this->clienteFilter)) {
                Notification::make()
                    ->title('Selecione um cliente antes de marcar contas para baixa.')
                    ->warning()
                    ->send();

                return;
            }

            $quantidade = count($ids);
            $this->modulePending('Baixa de ' . $quantidade . ' conta' . ($quantidade === 1 ? '' : 's') . ' (Fase 2)');

            return;
        }

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
