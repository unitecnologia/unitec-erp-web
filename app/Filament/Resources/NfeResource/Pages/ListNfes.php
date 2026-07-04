<?php

namespace App\Filament\Resources\NfeResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\NfeResource;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeEmissaoModal;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\Person;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListNfes extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesNfeEmissaoModal;

    protected static string $resource = NfeResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'cliente';

    #[Url(as: 'status')]
    public string $statusFilter = 'aberta';

    #[Url(as: 'cliente')]
    public string $clienteFilter = 'todos';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public string $chaveFilter = '';

    public string $chaveFilterApplied = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('NF-e');

        if ($this->periodoDe === '') {
            $this->periodoDe = '2000-06-01';
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

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-nfe-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma NF-e';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-nfe__search-text, .erp-nfe__chave-input',
            'create' => 'createNfe',
            'edit' => 'editNfe',
            'extraKeys' => [
                'F4' => ['method' => 'modulePending', 'params' => ['Cancelar NF-e']],
                'F5' => ['method' => 'modulePending', 'params' => ['Inutilizar']],
                'F6' => ['method' => 'modulePending', 'params' => ['Recuperar']],
                'F7' => ['method' => 'modulePending', 'params' => ['Imprimir DANFE']],
                'F8' => ['method' => 'modulePending', 'params' => ['Carta de Correção']],
                'F9' => ['method' => 'modulePending', 'params' => ['Email']],
                'F10' => ['method' => 'modulePending', 'params' => ['Relatório']],
                'F11' => ['method' => 'modulePending', 'params' => ['WhatsApp']],
                'F12' => ['method' => 'modulePending', 'params' => ['Fechar Mês']],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(NfeResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['cliente', 'venda']);

        if ($this->statusFilter !== 'todas') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->clienteFilter !== 'todos' && is_numeric($this->clienteFilter)) {
            $query->where('cliente_id', (int) $this->clienteFilter);
        }

        if (filled($this->periodoDeApplied)) {
            $query->whereDate('data_emissao', '>=', $this->periodoDeApplied);
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereDate('data_emissao', '<=', $this->periodoAteApplied);
        }

        if (filled($this->chaveFilterApplied)) {
            $digits = preg_replace('/\D/', '', $this->chaveFilterApplied) ?? '';
            $query->where('chave', 'like', '%'.$digits.'%');
        }

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, ['numero', 'cliente', 'chave', 'protocolo'], true)
            ? $this->searchColumn
            : 'cliente';

        $like = '%'.$term.'%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $like),
            'cliente' => $query->whereHas('cliente', fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $like)),
            'chave' => $query->where('chave', 'like', $like),
            'protocolo' => $query->where('protocolo', 'like', $like),
        };
    }

    protected function normalizeStatusFilter(string $filter): string
    {
        $allowed = [
            'todas',
            Nfe::STATUS_ABERTA,
            Nfe::STATUS_TRANSMITIDA,
            Nfe::STATUS_CANCELADA,
            Nfe::STATUS_DUPLICIDADE,
            Nfe::STATUS_INUTILIZADA,
            Nfe::STATUS_DENEGADA,
            Nfe::STATUS_CONTINGENCIA,
        ];

        return in_array($filter, $allowed, true) ? $filter : Nfe::STATUS_ABERTA;
    }

    #[Computed]
    public function empresaNome(): string
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        $empresa = $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : Empresa::query()->where('ativo', true)->orderBy('id')->first();

        if (! $empresa) {
            return '—';
        }

        return $empresa->fantasia ?: ($empresa->nome ?: $empresa->razao_social);
    }

    #[Computed]
    public function clientesOptions(): array
    {
        return Person::query()
            ->where('is_cliente', true)
            ->where('ativo', true)
            ->orderBy('nome_razao')
            ->pluck('nome_razao', 'id')
            ->all();
    }

    #[Computed]
    public function filteredTotal(): float
    {
        return (float) $this->buildListQuery()->sum('total');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.nfe.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.nfe.footer-total'),
                View::make('filament.components.erp.nfe.action-bar'),
                View::make('filament.components.erp.nfe.lancamento-modal'),
            ]);
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
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

    public function applyChaveFilter(): void
    {
        $this->chaveFilterApplied = trim($this->chaveFilter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedClienteFilter(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function applyFooterSearch(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }
}
