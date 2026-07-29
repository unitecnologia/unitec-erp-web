<?php

namespace App\Filament\Resources\ErpOperacaoLogResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\ErpOperacaoLogResource;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListErpOperacaoLogs extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;

    protected static string $resource = ErpOperacaoLogResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    public string $localSearchDeApplied = '';

    public string $localSearchAteApplied = '';

    #[Url(as: 'operacao')]
    public string $operacaoFilter = 'todas';

    #[Url(as: 'resultado')]
    public string $resultadoFilter = 'todos';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Log de Operações');

        if ($this->localSearchDe === '') {
            $this->localSearchDe = now()->startOfMonth()->format('Y-m-d');
        }

        if ($this->localSearchAte === '') {
            $this->localSearchAte = now()->format('Y-m-d');
        }

        if ($this->localSearchDeApplied === '') {
            $this->localSearchDeApplied = $this->localSearchDe;
        }

        if ($this->localSearchAteApplied === '') {
            $this->localSearchAteApplied = $this->localSearchAte;
        }
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-operacao-logs-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um log de operação';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'create' => null,
            'edit' => null,
            'delete' => null,
            'extraKeys' => [
                'F8' => ['method' => 'search'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return ErpOperacaoLogResource::table($table)
            ->recordUrl(null)
            ->recordAction(null);
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearchDeApplied)) {
            $query->whereDate('ocorrido_em', '>=', $this->localSearchDeApplied);
        }

        if (filled($this->localSearchAteApplied)) {
            $query->whereDate('ocorrido_em', '<=', $this->localSearchAteApplied);
        }

        if ($this->operacaoFilter !== 'todas') {
            $query->where('operacao', mb_strtoupper($this->operacaoFilter, 'UTF-8'));
        }

        if ($this->resultadoFilter !== 'todos') {
            $query->where('resultado', $this->resultadoFilter);
        }

        $term = trim($this->localSearch);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('resumo', 'like', $like)
                    ->orWhere('documento_numero', 'like', $like)
                    ->orWhere('user_nome', 'like', $like)
                    ->orWhere('origem', 'like', $like)
                    ->orWhere('operacao', 'like', $like);
            });
        }

        return $query->orderByDesc('ocorrido_em')->orderByDesc('id');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.operacao-logs.screen'),
                EmbeddedTable::make()->columnSpanFull(),
            ]);
    }

    public function search(): void
    {
        $this->localSearchDeApplied = $this->localSearchDe;
        $this->localSearchAteApplied = $this->localSearchAte;
        $this->resetTable();

        Notification::make()
            ->title('Filtro aplicado.')
            ->success()
            ->send();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->localSearchDe = now()->startOfMonth()->format('Y-m-d');
        $this->localSearchAte = now()->format('Y-m-d');
        $this->localSearchDeApplied = $this->localSearchDe;
        $this->localSearchAteApplied = $this->localSearchAte;
        $this->operacaoFilter = 'todas';
        $this->resultadoFilter = 'todos';
        $this->resetTable();
    }

    public function updatedOperacaoFilter(): void
    {
        $this->resetTable();
    }

    public function updatedResultadoFilter(): void
    {
        $this->resetTable();
    }
}
