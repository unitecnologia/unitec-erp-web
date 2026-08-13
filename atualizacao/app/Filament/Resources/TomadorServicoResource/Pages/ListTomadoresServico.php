<?php

namespace App\Filament\Resources\TomadorServicoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Concerns\ManagesErpCodigoNomeModal;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\TomadorServicoResource;
use App\Models\TomadorServico;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListTomadoresServico extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;
    use ManagesErpCodigoNomeModal;
    use ManagesErpSearchColumn {
        ManagesErpSearchColumn::updatedSearchColumn insteadof InteractsWithErpSimpleListPage;
    }

    protected static string $resource = TomadorServicoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);

        ErpScreen::set('Tomador de Serviço');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['codigo', 'nome'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'codigo';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-tomadores-servico-page';
    }

    protected function erpCnModelClass(): string
    {
        return TomadorServico::class;
    }

    protected function erpCnEntityLabel(): string
    {
        return 'Tomador de serviço';
    }

    protected function erpCnNomeFieldLabel(): string
    {
        return 'Tomador';
    }

    protected function erpListEntityName(): string
    {
        return 'um tomador de serviço';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-tomadores-servico__search-text';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createErpCn';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editErpCn';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteErpCn';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(TomadorServicoResource::table($table));
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

        $query = match ($this->statusFilter) {
            'ativos' => $query->where('ativo', true),
            'inativos' => $query->where('ativo', false),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applySimpleLocalSearch($query, $this->localSearch, ['codigo', 'nome']);
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.shared.logistica-simple-screen')
                    ->viewData([
                        'pageClass' => 'erp-tomadores-servico',
                        'nomeSearchLabel' => 'TOMADOR',
                        'deleteHint' => 'tomador de serviço',
                    ]),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.shared.erp-status-filters')
                    ->viewData(['pageClass' => 'erp-tomadores-servico']),
                View::make('filament.components.erp.shared.logistica-simple-action-bar')
                    ->viewData([
                        'pageClass' => 'erp-tomadores-servico',
                        'createMethod' => 'createErpCn',
                        'editMethod' => 'editErpCn',
                        'deleteMethod' => 'deleteErpCn',
                    ]),
                View::make('filament.components.erp.shared.codigo-nome-form-modal')
                    ->viewData([
                        'modalTitle' => 'Tomador de serviço',
                        'nomeLabel' => 'Tomador',
                    ]),
            ]);
    }
}
