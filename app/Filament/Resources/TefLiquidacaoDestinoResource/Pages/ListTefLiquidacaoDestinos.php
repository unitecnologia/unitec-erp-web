<?php

namespace App\Filament\Resources\TefLiquidacaoDestinoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\TefLiquidacaoDestinoResource;
use App\Filament\Resources\TefLiquidacaoDestinoResource\Pages\Concerns\ManagesTefLiquidacaoDestinoDeleteConfirm;
use App\Filament\Resources\TefLiquidacaoDestinoResource\Pages\Concerns\ManagesTefLiquidacaoDestinoFormModal;
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

class ListTefLiquidacaoDestinos extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesTefLiquidacaoDestinoDeleteConfirm;
    use ManagesTefLiquidacaoDestinoFormModal;

    protected static string $resource = TefLiquidacaoDestinoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'terminal';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);

        ErpScreen::set('SuperTEF — Destinos de liquidação');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['terminal', 'conta', 'cnpj', 'id'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'terminal';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-documentos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um destino';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-documentos__search-text',
            'create' => 'createDestino',
            'edit' => 'editDestino',
            'delete' => 'deleteDestino',
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(TefLiquidacaoDestinoResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'inativos', 'todos'], true) ? (string) $value : 'ativos';
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
            'ativos' => $query->where('ativo', true),
            'inativos' => $query->where('ativo', false),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applyDestinoSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyDestinoSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');
        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->erpAllowedSearchColumns(), true)
            ? $this->searchColumn
            : 'terminal';

        if ($column === 'id') {
            if (is_numeric($term)) {
                $query->whereKey((int) $term);

                return;
            }

            $like = '%'.$term.'%';
            if ($query->getConnection()->getDriverName() === 'sqlite') {
                $query->whereRaw('CAST(id AS TEXT) LIKE ?', [$like]);

                return;
            }

            $query->whereRaw('CAST(id AS CHAR) LIKE ?', [$like]);

            return;
        }

        if ($column === 'cnpj') {
            $digits = preg_replace('/\D+/', '', $term) ?? '';
            if ($digits === '') {
                $query->where('acquirer_cnpj', '');

                return;
            }

            $query->where('acquirer_cnpj', 'like', '%'.$digits.'%');

            return;
        }

        if ($column === 'conta') {
            $like = '%'.$term.'%';
            $query->whereHas('caixaConta', function (Builder $q) use ($like): void {
                $q->where('nome', 'like', $like)
                    ->orWhere('codigo', 'like', $like);
            });

            return;
        }

        $like = '%'.$term.'%';
        $query->whereHas('terminal', function (Builder $q) use ($like): void {
            $q->where('nome', 'like', $like)
                ->orWhere('numero_logico_terminal', 'like', $like);
        });
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.tef-liquidacao-destinos.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.tef-liquidacao-destinos.status-filters'),
                View::make('filament.components.erp.tef-liquidacao-destinos.action-bar'),
                View::make('filament.components.erp.tef-liquidacao-destinos.form-modal'),
                View::make('filament.components.erp.tef-liquidacao-destinos.confirm-delete-modal'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        if (filled($this->localSearch) && in_array($this->searchColumn, ['terminal', 'conta'], true)) {
            $this->localSearch = mb_strtoupper(trim($this->localSearch), 'UTF-8');
        }

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
        return ErpAccess::currentCan('tef_liquidacao_destino.access');
    }
}
