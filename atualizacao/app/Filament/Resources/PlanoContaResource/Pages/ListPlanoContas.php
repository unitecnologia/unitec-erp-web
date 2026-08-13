<?php

namespace App\Filament\Resources\PlanoContaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\PlanoContaResource;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListPlanoContas extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;

    protected static string $resource = PlanoContaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'descricao';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Plano de Contas');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-planos-contas-page';
    }

    /**
     * Reutiliza o layout de janela central com fundo esmaecido (mesmo padrão Contas Caixa).
     *
     * @return array<int, string>
     */
    protected function erpListExtraPageClasses(): array
    {
        return ['erp-contas-caixa-page'];
    }

    protected function erpListEntityName(): string
    {
        return 'um plano de contas';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-unidades__input';
    }

    protected function erpSimpleListDefaultSearchColumn(): string
    {
        return 'descricao';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createPlanoConta';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editPlanoConta';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deletePlanoConta';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(PlanoContaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
            $column = in_array($this->searchColumn, ['codigo', 'descricao'], true)
                ? $this->searchColumn
                : 'descricao';

            if ($column === 'codigo') {
                $query->where('codigo', 'like', '%' . preg_replace('/\D/', '', $term) . '%');
            } else {
                $query->where('descricao', 'like', '%' . $term . '%');
            }
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.planos-contas.titlebar'),
                View::make('filament.components.erp.planos-contas.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.planos-contas.action-bar'),
            ]);
    }

    public function createPlanoConta(): void
    {
        $this->modulePending('Cadastro de plano de contas (Fase 2)');
    }

    public function editPlanoConta(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $this->modulePending('Alteração de plano de contas (Fase 2)');
    }

    public function deletePlanoConta(): void
    {
        if (! $this->highlightedRecordIdOrNotify('delete')) {
            return;
        }

        $this->modulePending('Exclusão de plano de contas (Fase 2)');
    }
}
