<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\CompraResource;
use App\Models\Compra;
use App\Models\Empresa;
use App\Support\Erp\ErpScreen;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListCompras extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = CompraResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'fornecedor';

    #[Url(as: 'status')]
    public string $statusFilter = 'todas';

    public bool $lancamentoModalOpen = false;

    public ?int $lancamentoModalCompraId = null;

    public string $lancamentoModalStatus = '';

    /** @var array<string, string> */
    public array $lancamentoModalHeader = [];

    /** @var array<int, array<string, string>> */
    public array $lancamentoModalRows = [];

    public string $lancamentoModalValorCompra = '0,0000';

    public string $lancamentoModalValorMargem = '0,0000';

    public string $lancamentoModalValorVenda = '0,0000';

    /** @var array<string, string> */
    public array $lancamentoModalTotais = [];

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Compras');

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-compras-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma compra';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-compras__search-text, .erp-compras__search-date-from',
            'searchFocusKey' => 'F8',
            'create' => 'createCompra',
            'edit' => 'editCompra',
            'extraKeys' => [
                'F4' => ['method' => 'cancelCompra'],
                'F6' => ['method' => 'modulePending', 'params' => ['Ler XML']],
                'F9' => ['method' => 'modulePending', 'params' => ['Fechar Mês']],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(CompraResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['fornecedor']);

        if ($this->statusFilter !== 'todas') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->isDateSearchColumn()) {
            $this->applyLocalSearchByDateRange($query);
        } elseif (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function isDateSearchColumn(): bool
    {
        return in_array($this->searchColumn, ['data_emissao', 'data_entrada'], true);
    }

    protected function applyLocalSearchByDateRange(Builder $query): void
    {
        if (! filled($this->localSearchDe) && ! filled($this->localSearchAte)) {
            return;
        }

        $column = $this->searchColumn === 'data_entrada' ? 'data_entrada' : 'data_emissao';

        if (filled($this->localSearchDe)) {
            $query->whereDate($column, '>=', $this->localSearchDe);
        }

        if (filled($this->localSearchAte)) {
            $query->whereDate($column, '<=', $this->localSearchAte);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return ['numero', 'data_emissao', 'data_entrada', 'numero_nota', 'fornecedor', 'chave', 'total'];
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)
            ? $this->searchColumn
            : 'fornecedor';

        $like = '%' . $term . '%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $like),
            'numero_nota' => $query->where('numero_nota', 'like', $like),
            'fornecedor' => $query->whereHas('fornecedor', fn (Builder $fornecedorQuery): Builder => $fornecedorQuery->where('nome_razao', 'like', $like)),
            'chave' => $query->where('chave_nfe', 'like', $like),
            'total' => $this->applyLocalSearchByTotal($query, $term),
            default => null,
        };
    }

    protected function applyLocalSearchByTotal(Builder $query, string $term): void
    {
        $normalized = str_replace(['R$', ' '], '', $term);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            if ($this->databaseDriver($query) === 'sqlite') {
                $query->whereRaw('CAST(total AS TEXT) LIKE ?', ['%' . $normalized . '%']);

                return;
            }

            $query->where('total', 'like', '%' . $normalized . '%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', total), '.', ',') LIKE ?", ['%' . $term . '%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT(total, 2), '.', ',') LIKE ?", ['%' . $term . '%']);
    }

    protected function databaseDriver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
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
                View::make('filament.components.erp.compras.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.compras.footer-total'),
                View::make('filament.components.erp.compras.action-bar'),
                View::make('filament.components.erp.compras.lancamento-modal'),
            ]);
    }

    public function openCompraLancamento(int $compraId): void
    {
        $compra = Compra::query()
            ->with(['itens.product', 'fornecedor'])
            ->find($compraId);

        if (! $compra) {
            Notification::make()
                ->title('Compra não encontrada.')
                ->danger()
                ->send();

            return;
        }

        $status = mb_strtoupper(Compra::statusLabels()[$compra->status] ?? $compra->status, 'UTF-8');
        $nfeKeyParts = $this->extractNfeKeyParts($compra->chave_nfe);
        $margem = $this->buildLancamentoModalMargem($compra);

        $this->lancamentoModalCompraId = $compra->id;
        $this->lancamentoModalStatus = $status;
        $this->lancamentoModalHeader = [
            'numero' => $this->formatCompraNumero($compra->numero),
            'empresa' => $this->empresaNome,
            'fornecedor' => $compra->fornecedor?->nome_razao ?? '—',
            'uf' => $compra->fornecedor?->uf ?: '—',
            'cnpj' => $this->formatCpfCnpj($compra->fornecedor?->cpf_cnpj),
            'chave' => $compra->chave_nfe ?: '—',
            'nota' => $compra->numero_nota ?: '—',
            'modelo' => $nfeKeyParts['modelo'],
            'serie' => $nfeKeyParts['serie'],
            'data_emissao' => $compra->data_emissao?->format('d/m/Y') ?? '—',
            'data_entrada' => $compra->data_entrada?->format('d/m/Y') ?? '—',
        ];
        $this->lancamentoModalRows = $this->buildLancamentoModalRows($compra);
        $this->lancamentoModalValorCompra = $margem['compra'];
        $this->lancamentoModalValorMargem = $margem['margem'];
        $this->lancamentoModalValorVenda = $margem['venda'];
        $this->lancamentoModalTotais = $this->buildLancamentoModalTotais($compra);
        $this->lancamentoModalOpen = true;
    }

    public function closeCompraLancamento(): void
    {
        $this->lancamentoModalOpen = false;
        $this->lancamentoModalCompraId = null;
        $this->lancamentoModalStatus = '';
        $this->lancamentoModalHeader = [];
        $this->lancamentoModalRows = [];
        $this->lancamentoModalValorCompra = '0,0000';
        $this->lancamentoModalValorMargem = '0,0000';
        $this->lancamentoModalValorVenda = '0,0000';
        $this->lancamentoModalTotais = [];
    }

    public function printCompraDanfe(): void
    {
        if (! $this->lancamentoModalCompraId) {
            Notification::make()
                ->title('Nenhuma compra selecionada.')
                ->warning()
                ->send();

            return;
        }

        $this->dispatch(
            'erp-compras-open-danfe',
            url: route('erp.reports.compra-danfe', ['compra' => $this->lancamentoModalCompraId]),
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function buildLancamentoModalRows(Compra $compra): array
    {
        $rows = [];
        $index = 1;

        foreach ($compra->itens as $item) {
            $codigo = $item->product?->codigo;
            $codigoFormatado = '—';

            if ($codigo !== null && $codigo !== '') {
                $trimmed = ltrim((string) $codigo, '0');
                $codigoFormatado = $trimmed !== '' ? $trimmed : '0';
            }

            $referencia = trim((string) ($item->product?->referencia ?? ''));

            $rows[] = [
                'item' => (string) $index++,
                'codigo' => $codigoFormatado,
                'referencia' => $referencia !== '' ? $referencia : '—',
                'produto' => $item->product?->descricao ?? '—',
                'qtd' => number_format((float) $item->quantidade, 3, ',', '.'),
                'preco' => number_format((float) $item->valor_unitario, 4, ',', '.'),
                'total' => number_format((float) $item->total, 2, ',', '.'),
                'preco_venda' => number_format((float) ($item->product?->preco_venda ?? 0), 4, ',', '.'),
            ];
        }

        return $rows;
    }

    /**
     * @return array{compra: string, margem: string, venda: string}
     */
    protected function buildLancamentoModalMargem(Compra $compra): array
    {
        $item = $compra->itens->first();

        if (! $item) {
            return [
                'compra' => '0,0000',
                'margem' => '0,0000',
                'venda' => '0,0000',
            ];
        }

        $valorCompra = (float) $item->valor_unitario;
        $valorVenda = (float) ($item->product?->preco_venda ?? $valorCompra);
        $valorMargem = $valorVenda - $valorCompra;

        return [
            'compra' => number_format($valorCompra, 4, ',', '.'),
            'margem' => number_format($valorMargem, 4, ',', '.'),
            'venda' => number_format($valorVenda, 4, ',', '.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function buildLancamentoModalTotais(Compra $compra): array
    {
        $subtotal = (float) $compra->itens->sum('total');
        $total = (float) $compra->total;
        $zero = '0,00';

        return [
            'subtotal' => number_format($subtotal, 2, ',', '.'),
            'base_icms' => $zero,
            'valor_icms' => $zero,
            'base_ipi' => $zero,
            'valor_ipi' => $zero,
            'base_cofins' => $zero,
            'valor_cofins' => $zero,
            'base_pis' => $zero,
            'valor_pis' => $zero,
            'base_st' => $zero,
            'valor_st' => $zero,
            'desconto' => $zero,
            'frete' => $zero,
            'seguro' => $zero,
            'outras' => $zero,
            'total' => number_format($total > 0 ? $total : $subtotal, 2, ',', '.'),
        ];
    }

    /**
     * @return array{modelo: string, serie: string}
     */
    protected function extractNfeKeyParts(?string $chave): array
    {
        $digits = preg_replace('/\D/', '', (string) $chave) ?? '';

        if (strlen($digits) !== 44) {
            return [
                'modelo' => '—',
                'serie' => '—',
            ];
        }

        $modelo = ltrim(substr($digits, 20, 2), '0');
        $serie = ltrim(substr($digits, 22, 3), '0');

        return [
            'modelo' => $modelo !== '' ? $modelo : '0',
            'serie' => $serie !== '' ? $serie : '0',
        ];
    }

    protected function formatCompraNumero(?string $numero): string
    {
        if ($numero === null || $numero === '') {
            return '—';
        }

        $trimmed = ltrim($numero, '0');

        return $trimmed !== '' ? $trimmed : '0';
    }

    protected function formatCpfCnpj(?string $value): string
    {
        if (! filled($value)) {
            return '—';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digits) ?: $value;
        }

        return $value;
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        $allowed = [
            'todas',
            Compra::STATUS_ABERTA,
            Compra::STATUS_FECHADA,
            Compra::STATUS_CANCELADA,
        ];

        return in_array($value, $allowed, true) ? (string) $value : 'todas';
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->localSearchDe = '';
        $this->localSearchAte = '';
        $this->searchColumn = 'fornecedor';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->localSearchDe = '';
        $this->localSearchAte = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearch(): void
    {
        if ($this->isDateSearchColumn()) {
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

    public function createCompra(): void
    {
        $this->modulePending('Cadastro de compra (Fase 2)');
    }

    public function editCompra(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $this->modulePending('Alteração de compra (Fase 2)');
    }

    public function cancelCompra(): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('cancel');

        if (! $recordId) {
            return;
        }

        $compra = Compra::query()->find($recordId);

        if (! $compra) {
            return;
        }

        if ($compra->status === Compra::STATUS_CANCELADA) {
            Notification::make()
                ->title('Compra já está cancelada.')
                ->warning()
                ->send();

            return;
        }

        $compra->update(['status' => Compra::STATUS_CANCELADA]);

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Compra cancelada.')
            ->success()
            ->send();
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'cancel' => 'uma compra para cancelar',
            default => $this->defaultErpListSelectPrompt($action),
        };
    }
}
