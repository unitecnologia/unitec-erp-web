<?php

namespace App\Filament\Resources\VendaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\VendaResource;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Venda;
use App\Support\Erp\Pdv\PdvCupomPrinter;
use App\Support\Erp\Queries\VendaListQueryBuilder;
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

class ListVendas extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;

    protected static string $resource = VendaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    public string $localSearchHoraDe = '';

    public string $localSearchHoraAte = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'cliente';

    #[Url(as: 'status')]
    public string $statusFilter = 'todos';

    #[Url(as: 'tipo')]
    public string $tipoFilter = 'todos';

    public bool $itensModalOpen = false;

    public ?int $itensModalVendaId = null;

    public ?int $itensModalPdvVendaId = null;

    public string $itensModalTitulo = '';

    /** @var array<int, array<string, string>> */
    public array $itensModalRows = [];

    public string $itensModalTotalFormatted = 'R$ 0,00';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Vendas');

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->tipoFilter = $this->normalizeTipoFilter($this->tipoFilter);
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-vendas-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma venda';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-vendas__search-text, .erp-vendas__search-value-select, .erp-vendas__search-date-from, .erp-vendas__search-time-from',
            'searchFocusKey' => 'F8',
            'create' => 'createVenda',
            'edit' => 'editVenda',
            'extraKeys' => [
                'F4' => ['method' => 'cancelVenda'],
                'F6' => ['method' => 'printVendas'],
                'F9' => ['method' => 'modulePending', 'params' => ['E-mail']],
                'F10' => ['method' => 'modulePending', 'params' => ['WhatsApp']],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(VendaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        return (new VendaListQueryBuilder(
            statusFilter: $this->statusFilter,
            tipoFilter: $this->tipoFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            localSearchDe: $this->localSearchDe,
            localSearchAte: $this->localSearchAte,
            localSearchHoraDe: $this->localSearchHoraDe,
            localSearchHoraAte: $this->localSearchHoraAte,
        ))->build();
    }

    protected function isDateSearchColumn(): bool
    {
        return $this->searchColumn === 'data';
    }

    protected function isTimeSearchColumn(): bool
    {
        return $this->searchColumn === 'hora';
    }

    protected function isOptionSearchColumn(): bool
    {
        return in_array($this->searchColumn, ['meio_pagamento', 'plataforma', 'situacao', 'tipo'], true);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function meioPagamentoFilterOptions(): array
    {
        $options = FormaPagamento::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->pluck('descricao')
            ->map(fn (mixed $descricao): string => mb_strtoupper(trim((string) $descricao), 'UTF-8'))
            ->filter(fn (string $descricao): bool => $descricao !== '')
            ->unique()
            ->values();

        $fromVendas = Venda::query()
            ->whereNotNull('forma_pagamento')
            ->where('forma_pagamento', '!=', '')
            ->distinct()
            ->orderBy('forma_pagamento')
            ->pluck('forma_pagamento')
            ->map(fn (mixed $descricao): string => mb_strtoupper(trim((string) $descricao), 'UTF-8'))
            ->filter(fn (string $descricao): bool => $descricao !== '');

        return $options
            ->merge($fromVendas)
            ->unique()
            ->sort()
            ->values()
            ->all();
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
    public function filteredTotal(): float
    {
        return (float) $this->buildListQuery()->sum('total');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.vendas.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.vendas.footer-total'),
                View::make('filament.components.erp.vendas.action-bar'),
                View::make('filament.components.erp.vendas.itens-modal'),
            ]);
    }

    public function openVendaItens(int $vendaId): void
    {
        $venda = Venda::query()
            ->with(['itens.product', 'cliente'])
            ->find($vendaId);

        if (! $venda) {
            Notification::make()
                ->title('Venda não encontrada.')
                ->danger()
                ->send();

            return;
        }

        $numero = ltrim((string) $venda->numero, '0') ?: '0';
        $cliente = $venda->cliente?->nome_razao ?? '—';

        $this->itensModalVendaId = $venda->id;
        $this->itensModalPdvVendaId = PdvCupomPrinter::findPdvVendaIdForVenda($venda->id);
        $this->itensModalTitulo = 'Itens da venda nº ' . $numero . ' — ' . $cliente;
        $this->itensModalRows = $this->buildItensModalRows($venda);
        $this->itensModalTotalFormatted = 'R$ ' . number_format(
            (float) $venda->itens->sum('total'),
            2,
            ',',
            '.',
        );
        $this->itensModalOpen = true;
    }

    public function closeVendaItens(): void
    {
        $this->itensModalOpen = false;
        $this->itensModalVendaId = null;
        $this->itensModalPdvVendaId = null;
        $this->itensModalTitulo = '';
        $this->itensModalRows = [];
        $this->itensModalTotalFormatted = 'R$ 0,00';
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function buildItensModalRows(Venda $venda): array
    {
        $rows = [];
        $index = 1;

        foreach ($venda->itens as $item) {
            $bruto = (float) $item->quantidade * (float) $item->valor_item;
            $desconto = max(0, round($bruto - (float) $item->total, 2));
            $codigo = $item->product?->codigo;
            $codigoFormatado = '—';

            if ($codigo !== null && $codigo !== '') {
                $trimmed = ltrim((string) $codigo, '0');
                $codigoFormatado = $trimmed !== '' ? $trimmed : '0';
            }

            $rows[] = [
                'item' => (string) $index++,
                'codigo' => $codigoFormatado,
                'produto' => $item->product?->descricao ?? '—',
                'qtd' => number_format((float) $item->quantidade, 3, ',', '.'),
                'preco' => 'R$ ' . number_format((float) $item->valor_item, 2, ',', '.'),
                'valor_item' => 'R$ ' . number_format($bruto, 2, ',', '.'),
                'desconto' => 'R$ ' . number_format($desconto, 2, ',', '.'),
                'total' => 'R$ ' . number_format((float) $item->total, 2, ',', '.'),
            ];
        }

        return $rows;
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        $allowed = [
            'todos',
            Venda::STATUS_ABERTO,
            Venda::STATUS_GRAVADO,
            Venda::STATUS_FECHADO,
            Venda::STATUS_CANCELADO,
        ];

        return in_array($value, $allowed, true) ? (string) $value : 'todos';
    }

    protected function normalizeTipoFilter(mixed $value): string
    {
        $allowed = ['todos', Venda::TIPO_PEDIDO, Venda::TIPO_CUPOM];

        return in_array($value, $allowed, true) ? (string) $value : 'todos';
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function setTipoFilter(string $filter): void
    {
        $this->tipoFilter = $this->normalizeTipoFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->localSearchDe = '';
        $this->localSearchAte = '';
        $this->localSearchHoraDe = '';
        $this->localSearchHoraAte = '';
        $this->searchColumn = 'cliente';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->localSearchDe = '';
        $this->localSearchAte = '';
        $this->localSearchHoraDe = '';
        $this->localSearchHoraAte = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearch(): void
    {
        if ($this->isDateSearchColumn() || $this->isTimeSearchColumn()) {
            return;
        }

        if ($this->isOptionSearchColumn() && ! filled($this->localSearch)) {
            $this->clearListSelection();
            $this->resetTable();

            return;
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearchDe(): void
    {
        if (! $this->isDateSearchColumn()) {
            return;
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearchAte(): void
    {
        if (! $this->isDateSearchColumn()) {
            return;
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearchHoraDe(): void
    {
        if (! $this->isTimeSearchColumn()) {
            return;
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearchHoraAte(): void
    {
        if (! $this->isTimeSearchColumn()) {
            return;
        }

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

    public function createVenda(): void
    {
        if (! $this->erpAuthorizeOrNotify('vendas.create')) {
            return;
        }

        $this->modulePending('Cadastro de venda (Fase 2)');
    }

    public function editVenda(): void
    {
        if (! $this->erpAuthorizeOrNotify('vendas.update')) {
            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $this->modulePending('Alteração de venda (Fase 2)');
    }

    public function cancelVenda(): void
    {
        if (! $this->erpAuthorizeOrNotify('vendas.cancel')) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('cancel');

        if (! $recordId) {
            return;
        }

        $venda = Venda::query()->find($recordId);

        if (! $venda) {
            return;
        }

        if ($venda->status === Venda::STATUS_CANCELADO) {
            Notification::make()
                ->title('Venda já está cancelada.')
                ->warning()
                ->send();

            return;
        }

        $venda->update(['status' => Venda::STATUS_CANCELADO]);

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Venda cancelada.')
            ->success()
            ->send();
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'cancel' => 'uma venda para cancelar',
            default => $this->defaultErpListSelectPrompt($action),
        };
    }

    public function printVendas(): void
    {
        if (! $this->erpAuthorizeOrNotify('vendas.print')) {
            return;
        }

        $builder = new VendaListQueryBuilder(
            statusFilter: $this->statusFilter,
            tipoFilter: $this->tipoFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            localSearchDe: $this->localSearchDe,
            localSearchAte: $this->localSearchAte,
            localSearchHoraDe: $this->localSearchHoraDe,
            localSearchHoraAte: $this->localSearchHoraAte,
        );

        $params = array_filter(
            $builder->reportFilters(),
            fn ($value): bool => filled($value),
        );

        $url = route('erp.reports.vendas-listagem', $params);

        $this->redirect($url, navigate: false);
    }

    public function reimprimirVendaItens(): void
    {
        if (! $this->erpAuthorizeOrNotify('vendas.reprint_cupom')) {
            return;
        }

        if (! $this->itensModalPdvVendaId) {
            Notification::make()
                ->title('Cupom PDV não encontrado para esta venda.')
                ->warning()
                ->send();

            return;
        }

        $this->js(PdvCupomPrinter::livewireOpenJs($this->itensModalPdvVendaId, 1));

        Notification::make()
            ->title('Segunda via enviada para impressão.')
            ->success()
            ->send();
    }
}
