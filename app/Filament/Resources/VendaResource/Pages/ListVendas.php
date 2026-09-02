<?php

namespace App\Filament\Resources\VendaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\VendaResource;
use App\Livewire\Erp\VendaListTable;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Venda;
use App\Support\Erp\ErpDataSyncVersion;
use App\Support\Erp\Pdv\PdvCupomPrinter;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use App\Support\Erp\Queries\VendaListQueryBuilder;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Vendas\EstornarVendaService;
use DomainException;
use Filament\Notifications\Notification;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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
    public string $searchColumn = 'data';

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

    public bool $cancelVendaModalOpen = false;

    public ?int $cancelVendaId = null;

    public string $cancelVendaMotivo = '';

    public ?string $cancelVendaNumero = null;

    public function mount(): void
    {
        // Datas padrão antes do parent::mount (loadTable / query).
        $this->applyTodayDateFilter();

        parent::mount();

        ErpScreen::set('Vendas');

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->tipoFilter = $this->normalizeTipoFilter($this->tipoFilter);
        // Sempre abre em DATA de/até = hoje (amanhã vira o dia seguinte).
        $this->applyTodayDateFilter();
        $this->dispatch('erp-masks-refresh');
    }

    public function booted(): void
    {
        if ($this->searchColumn === 'data'
            && ($this->localSearchDe === '' || $this->localSearchAte === '')) {
            $this->applyTodayDateFilter();
        }
    }

    public function mountInteractsWithTable(): void
    {
    }

    public function erpListSyncPollEnabled(): bool
    {
        if (! config('unitec.erp_list_sync_poll_enabled', true)) {
            return false;
        }

        return $this->erpListSyncChannel() !== null;
    }

    protected function applyTodayDateFilter(): void
    {
        $hoje = ErpTimezone::toLocal()->toDateString();
        $this->searchColumn = 'data';
        $this->localSearch = '';
        $this->localSearchDe = $hoje;
        $this->localSearchAte = $hoje;
        $this->localSearchHoraDe = '';
        $this->localSearchHoraAte = '';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-vendas-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma venda';
    }

    protected function erpListSyncChannel(): ?string
    {
        return ErpDataSyncVersion::CHANNEL_SALES;
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-vendas__search-text, .erp-vendas__search-value-select, .erp-vendas__search-date-from, .erp-vendas__search-time-from, .erp-field-dd__btn',
            'searchFocusKey' => 'F8',
            'edit' => 'editVenda',
            'extraKeys' => [
                'F4' => ['method' => 'cancelVenda'],
                'F6' => ['method' => 'printVendas'],
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
            applyDefaultOrder: false,
        ))->buildForList();
    }

    protected function listQueryBuilder(): VendaListQueryBuilder
    {
        return new VendaListQueryBuilder(
            statusFilter: $this->statusFilter,
            tipoFilter: $this->tipoFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            localSearchDe: $this->localSearchDe,
            localSearchAte: $this->localSearchAte,
            localSearchHoraDe: $this->localSearchHoraDe,
            localSearchHoraAte: $this->localSearchHoraAte,
        );
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
        return $this->listQueryBuilder()->sumFilteredTotal();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.vendas.screen'),
                View::make('filament.components.erp.vendas.table-host')
                    ->columnSpanFull(),
                View::make('filament.components.erp.vendas.footer-total'),
                View::make('filament.components.erp.vendas.action-bar'),
                View::make('filament.components.erp.vendas.itens-modal'),
                View::make('filament.components.erp.vendas.cancel-modal'),
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

    #[On('erp-venda-open-itens')]
    public function onErpVendaOpenItens(int $vendaId): void
    {
        $this->openVendaItens($vendaId);
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
        $this->pushVendaListRefresh();
    }

    public function setTipoFilter(string $filter): void
    {
        $this->tipoFilter = $this->normalizeTipoFilter($filter);
        $this->clearListSelection();
        $this->pushVendaListRefresh();
    }

    public function setSearchColumn(string $column): void
    {
        $normalized = $this->normalizeSearchColumn($column);
        $hadSearch = filled($this->localSearch)
            || filled($this->localSearchDe)
            || filled($this->localSearchAte);
        $wasData = $this->searchColumn === 'data';
        $changed = $normalized !== $this->searchColumn;

        $this->searchColumn = $normalized;
        $this->localSearch = '';
        $this->localSearchDe = '';
        $this->localSearchAte = '';
        $this->localSearchHoraDe = '';
        $this->localSearchHoraAte = '';

        if ($this->searchColumn === 'data') {
            $this->applyTodayDateFilter();
            $this->clearListSelection();
            $this->dispatch('erp-masks-refresh');
            $this->pushVendaListRefresh(resetSort: true);

            return;
        }

        $this->clearListSelection();

        if ($wasData || $hadSearch) {
            $this->dispatch('erp-masks-refresh');
            $this->pushVendaListRefresh(resetSort: true);

            return;
        }

        if (! $changed) {
            $this->skipRender();

            return;
        }

        $this->skipRender();
        $this->dispatch('erp-masks-refresh');
    }

    public function clearSearch(): void
    {
        $this->applyTodayDateFilter();
        $this->clearListSelection();
        $this->dispatch('erp-masks-refresh');
        $this->pushVendaListRefresh(resetSort: true);
    }

    public function updatedSearchColumn(): void
    {
        $this->setSearchColumn($this->searchColumn);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->pushVendaListRefresh();
    }

    public function search(): void
    {
        $this->clearListSelection();
        $this->pushVendaListRefresh(resetSort: true);
    }

    protected function normalizeSearchColumn(mixed $value): string
    {
        $allowed = [
            'numero',
            'data',
            'cliente',
            'vendedor',
            'plataforma',
            'meio_pagamento',
            'total',
            'situacao',
            'tipo',
            'hora',
        ];

        return in_array($value, $allowed, true) ? (string) $value : 'data';
    }

    public function createVenda(): void
    {
        if (! $this->erpAuthorizeOrNotify('vendas.create')) {
            return;
        }

        $this->modulePending('Cadastro de venda (Fase 2)');
    }

    public function editVenda(int | string | null $recordId = null): void
    {
        if (! $this->erpAuthorizeOrNotify('vendas.update')) {
            return;
        }

        $resolvedId = filled($recordId) ? (int) $recordId : $this->highlightedRecordId;

        if (! $resolvedId) {
            $this->highlightedRecordIdOrNotify('edit');

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

        if ($venda->temOrigemPdv()) {
            Notification::make()
                ->title('Não é possível cancelar esta venda pelo ERP.')
                ->body('Vendas do PDV devem ser canceladas no próprio PDV (Consulta de vendas → Estorno).')
                ->warning()
                ->send();

            return;
        }

        $numero = ltrim((string) $venda->numero, '0') ?: '0';

        $this->cancelVendaId = (int) $venda->id;
        $this->cancelVendaNumero = $numero;
        $this->cancelVendaMotivo = '';
        $this->cancelVendaModalOpen = true;
    }

    public function confirmCancelVenda(): void
    {
        if (! $this->cancelVendaModalOpen || ! $this->cancelVendaId) {
            return;
        }

        $motivo = PdvEstornoMotivo::normalize($this->cancelVendaMotivo);
        $erroMotivo = PdvEstornoMotivo::validate($motivo);

        if ($erroMotivo !== null) {
            Notification::make()
                ->title('Cancelar venda')
                ->body($erroMotivo)
                ->warning()
                ->send();

            return;
        }

        $venda = Venda::query()->find($this->cancelVendaId);

        if (! $venda) {
            $this->closeCancelVendaModal();

            return;
        }

        if ($venda->status === Venda::STATUS_CANCELADO) {
            Notification::make()
                ->title('Venda já está cancelada.')
                ->warning()
                ->send();
            $this->closeCancelVendaModal();

            return;
        }

        if ($venda->temOrigemPdv()) {
            Notification::make()
                ->title('Não é possível cancelar esta venda pelo ERP.')
                ->body('Vendas do PDV devem ser canceladas no próprio PDV (Consulta de vendas → Estorno).')
                ->warning()
                ->send();
            $this->closeCancelVendaModal();

            return;
        }

        try {
            $result = (new EstornarVendaService())->fromVenda(
                $venda,
                $motivo,
                EstornarVendaService::ORIGEM_LISTA_VENDAS,
            );
        } catch (DomainException $exception) {
            Notification::make()
                ->title('Não foi possível cancelar a venda.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        } catch (FiscalEngineException $exception) {
            Notification::make()
                ->title('Falha no cancelamento fiscal.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        if ($result->alreadyCancelled) {
            Notification::make()
                ->title('Venda já está cancelada.')
                ->warning()
                ->send();
            $this->closeCancelVendaModal();

            return;
        }

        $this->closeCancelVendaModal();
        $this->clearListSelection();
        $this->pushVendaListRefresh();

        $body = $result->protocoloCancelamento
            ? 'Protocolo NFC-e: '.$result->protocoloCancelamento
            : null;

        Notification::make()
            ->title('Venda cancelada / estornada.')
            ->body($body)
            ->success()
            ->send();
    }

    public function closeCancelVendaModal(): void
    {
        $this->cancelVendaModalOpen = false;
        $this->cancelVendaId = null;
        $this->cancelVendaMotivo = '';
        $this->cancelVendaNumero = null;
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
        $this->pushVendaListRefresh();
    }

    public function refreshTable(): void
    {
        $this->syncErpListSyncVersionFromStore();
        $this->pushVendaListRefresh();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    protected function pushVendaListRefresh(bool $resetSort = false): void
    {
        $total = $this->listQueryBuilder()->sumFilteredTotal();

        $this->dispatch(
            'erp-venda-list-refresh',
            statusFilter: $this->statusFilter,
            tipoFilter: $this->tipoFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            localSearchDe: $this->localSearchDe,
            localSearchAte: $this->localSearchAte,
            localSearchHoraDe: $this->localSearchHoraDe,
            localSearchHoraAte: $this->localSearchHoraAte,
            perPage: (int) ($this->tableRecordsPerPage ?? 50),
            resetSort: $resetSort,
        )->to(VendaListTable::class);

        $this->skipRender();

        $this->js(sprintf(
            '(() => { const el = document.querySelector(".erp-vendas__total-value"); if (el) el.textContent = %s; })()',
            json_encode('R$ '.number_format($total, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
        ));
    }
}
