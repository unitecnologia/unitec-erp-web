<?php

namespace App\Filament\Resources\AjustaEstoqueGrupoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\AjustaEstoqueGrupoResource;
use App\Models\Grupo;
use App\Models\Marca;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListAjustaEstoqueGrupo extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = AjustaEstoqueGrupoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'grupo')]
    public string $grupoFilter = 'todos';

    #[Url(as: 'marca')]
    public string $marcaFilter = 'todos';

    #[Url(as: 'estoque')]
    public string $estoqueFilter = 'atual';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativo';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Ajusta Estoque - Por Grupo');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-ajusta-estoque-grupo-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um produto';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'create' => null,
            'edit' => null,
            'delete' => null,
            'extraKeys' => [
                'F5' => ['method' => 'pesquisar'],
            ],
        ];
    }

    #[Computed]
    public function gruposOptions(): array
    {
        return Grupo::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'nome')->all();
    }

    #[Computed]
    public function marcasOptions(): array
    {
        return Marca::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'nome')->all();
    }

    public function table(Table $table): Table
    {
        return AjustaEstoqueGrupoResource::table($table)
            ->recordUrl(null)
            ->recordAction(null);
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if ($this->grupoFilter !== 'todos') {
            $query->where('grupo', $this->grupoFilter);
        }

        if ($this->marcaFilter !== 'todos') {
            $query->where('marca', $this->marcaFilter);
        }

        match ($this->estoqueFilter) {
            'zerado' => $query->where('estoque', '<=', 0),
            'negativo' => $query->where('estoque', '<', 0),
            default => $query,
        };

        match ($this->statusFilter) {
            'inativo' => $query->where('ativo', false),
            default => $query->where('ativo', true),
        };

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.ajusta-estoque-grupo.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.ajusta-estoque-grupo.action-bar'),
            ]);
    }

    public function pesquisar(): void
    {
        $this->resetTable();
    }

    public function updatedGrupoFilter(): void
    {
        $this->resetTable();
    }

    public function updatedMarcaFilter(): void
    {
        $this->resetTable();
    }

    public function updatedEstoqueFilter(): void
    {
        $this->resetTable();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetTable();
    }
}
