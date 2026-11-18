<?php

namespace App\Filament\Resources\TefLiquidacaoLoteResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\TefLiquidacaoLoteResource;
use App\Filament\Resources\TefLiquidacaoLoteResource\Pages\Concerns\ManagesTefLiquidacaoDemonstrativo;
use App\Filament\Resources\TefLiquidacaoLoteResource\Pages\Concerns\ManagesTefLiquidacaoLoteActions;
use App\Filament\Resources\TefLiquidacaoLoteResource\Pages\Concerns\ManagesTefLiquidacaoLoteFormModal;
use App\Filament\Resources\TefLiquidacaoLoteResource\Pages\Concerns\ManagesTefLiquidacaoLoteOfxVinculo;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListTefLiquidacaoLotes extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesTefLiquidacaoDemonstrativo;
    use ManagesTefLiquidacaoLoteActions;
    use ManagesTefLiquidacaoLoteFormModal;
    use ManagesTefLiquidacaoLoteOfxVinculo;

    protected static string $resource = TefLiquidacaoLoteResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'id';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);

        ErpScreen::set('Lotes TEF');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['id', 'terminal', 'cnpj', 'externo'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'id';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-documentos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um lote';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-documentos__search-text',
            'create' => 'createLote',
            'edit' => 'verLote',
            'delete' => 'cancelarLoteAsk',
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(TefLiquidacaoLoteResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'cancelados', 'todos', 'conferidos', 'aguardando'], true)
            ? (string) $value
            : 'ativos';
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $query = match ($this->statusFilter) {
            'ativos' => $query->whereIn('situacao', ['preparacao', 'aguardando', 'conferido']),
            'aguardando' => $query->where('situacao', 'aguardando'),
            'conferidos' => $query->where('situacao', 'conferido'),
            'cancelados' => $query->where('situacao', 'cancelado'),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applyLoteSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyLoteSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');
        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->erpAllowedSearchColumns(), true)
            ? $this->searchColumn
            : 'id';

        if ($column === 'id' && is_numeric($term)) {
            $query->whereKey((int) $term);

            return;
        }

        if ($column === 'cnpj') {
            $digits = preg_replace('/\D+/', '', $term) ?? '';
            $query->where('acquirer_cnpj', 'like', '%'.$digits.'%');

            return;
        }

        if ($column === 'externo') {
            $query->where('identificador_externo', 'like', '%'.$term.'%');

            return;
        }

        if ($column === 'terminal') {
            $like = '%'.$term.'%';
            $query->whereHas('terminal', fn (Builder $q) => $q->where('nome', 'like', $like));

            return;
        }

        if (is_numeric($term)) {
            $query->whereKey((int) $term);
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.tef-liquidacao-lotes.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.tef-liquidacao-lotes.status-filters'),
                View::make('filament.components.erp.tef-liquidacao-lotes.action-bar'),
                View::make('filament.components.erp.tef-liquidacao-lotes.form-modal'),
                View::make('filament.components.erp.tef-liquidacao-lotes.detalhe-modal'),
                View::make('filament.components.erp.tef-liquidacao-lotes.confirm-cancel-modal'),
                View::make('filament.components.erp.tef-liquidacao-lotes.ofx-vinculo-modal'),
                View::make('filament.components.erp.tef-liquidacao-lotes.ofx-historico-modal'),
                View::make('filament.components.erp.tef-liquidacao-lotes.ofx-reverter-modal'),
                View::make('filament.components.erp.tef-liquidacao-lotes.demonstrativo-modal'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        $this->clearListSelection();
        $this->resetPage();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetPage();
        $this->resetTable();
    }

    public function refreshTable(): void
    {
        $this->resetTable();
    }

    public function closeScreen(): void
    {
        $this->redirect(filament()->getUrl());
    }

    public static function canAccess(array $parameters = []): bool
    {
        return ErpAccess::currentCan('tef_liquidacao_lote.access');
    }
}
