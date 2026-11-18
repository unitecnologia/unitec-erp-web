<?php

namespace App\Filament\Resources\RhDocumentoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhDocumentoResource;
use App\Filament\Resources\RhDocumentoResource\Pages\Concerns\ManagesRhDocumentoDeleteConfirm;
use App\Filament\Resources\RhDocumentoResource\Pages\Concerns\ManagesRhDocumentoFormModal;
use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListRhDocumentos extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesRhDocumentoDeleteConfirm;
    use ManagesRhDocumentoFormModal;

    protected static string $resource = RhDocumentoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'titulo';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    #[Url(as: 'validade')]
    public string $validadeFilter = 'todos';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->validadeFilter = $this->normalizeValidadeFilter($this->validadeFilter);

        ErpScreen::set('RH — Documentos');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['titulo', 'funcionario', 'id'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'titulo';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-documentos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um documento';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-documentos__search-text',
            'create' => 'createDocumento',
            'edit' => 'editDocumento',
            'delete' => 'deleteDocumento',
            'extraKeys' => [
                'F4' => ['method' => 'imprimirDocumentos', 'params' => []],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(RhDocumentoResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'inativos', 'todos'], true) ? (string) $value : 'ativos';
    }

    protected function normalizeValidadeFilter(mixed $value): string
    {
        return in_array($value, ['todos', 'vencendo', 'vencidos'], true) ? (string) $value : 'todos';
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function setValidadeFilter(string $filter): void
    {
        $this->validadeFilter = $this->normalizeValidadeFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $query = match ($this->statusFilter) {
            'ativos' => $query->where('ativo', true),
            'inativos' => $query->where('ativo', false),
            default => $query,
        };

        if ($this->validadeFilter === 'vencendo') {
            $hoje = now()->toDateString();
            $limite = now()->addDays(30)->toDateString();
            $query->whereNotNull('valido_ate')
                ->whereDate('valido_ate', '>=', $hoje)
                ->whereDate('valido_ate', '<=', $limite);
        } elseif ($this->validadeFilter === 'vencidos') {
            $query->whereNotNull('valido_ate')
                ->whereDate('valido_ate', '<', now()->toDateString());
        }

        if (filled($this->localSearch)) {
            $this->applyDocumentoSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyDocumentoSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->erpAllowedSearchColumns(), true)
            ? $this->searchColumn
            : 'titulo';

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

        if ($column === 'funcionario') {
            $like = '%'.$term.'%';
            $query->whereHasMorph('anexavel', [RhFuncionario::class], function (Builder $q) use ($like): void {
                $q->where(function (Builder $inner) use ($like): void {
                    $inner->where('nome', 'like', $like)
                        ->orWhere('codigo', 'like', $like);
                });
            });

            return;
        }

        $query->where('titulo', 'like', '%'.$term.'%');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.rh-documentos.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.rh-documentos.status-filters'),
                View::make('filament.components.erp.rh-documentos.action-bar'),
                View::make('filament.components.erp.rh-documentos.form-modal'),
                View::make('filament.components.erp.rh-documentos.confirm-delete-modal'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        if (filled($this->localSearch) && in_array($this->searchColumn, ['titulo', 'funcionario'], true)) {
            $this->localSearch = mb_strtoupper(trim($this->localSearch), 'UTF-8');
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'titulo';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function abrirAnexoSelecionado(): void
    {
        if (! ErpAccess::currentCan('rh.documentos.access')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('open')) {
            return;
        }

        $record = RhAnexo::query()->documentos()->find($this->highlightedRecordId);

        if (! $record || ! filled($record->caminho)) {
            Notification::make()->title('Arquivo não disponível.')->warning()->send();

            return;
        }

        $this->dispatch('erp-open-url', url: route('erp.rh.anexo.download', ['anexo' => $record->getKey()]));
    }

    public function imprimirDocumentos(): void
    {
        if (! ErpAccess::currentCan('rh.documentos.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        $params = array_filter([
            'status' => $this->statusFilter !== 'ativos' ? $this->statusFilter : null,
            'validade' => $this->validadeFilter !== 'todos' ? $this->validadeFilter : null,
            'campo' => $this->searchColumn !== 'titulo' ? $this->searchColumn : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'auto' => 1,
        ], fn ($v): bool => filled($v));

        $url = route('erp.reports.rh-documentos-listagem', $params);

        $this->redirect($url, navigate: false);
    }
}
