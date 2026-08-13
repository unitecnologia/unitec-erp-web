<?php

namespace App\Filament\Resources\BoletoRemessaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\BoletoRemessaResource;
use App\Models\BoletoRemessa;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListBoletoRemessas extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;

    protected static string $resource = BoletoRemessaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'id';

    public bool $titulosModalOpen = false;

    public string $titulosModalTitulo = '';

    /** @var array<int, array<string, string>> */
    public array $titulosModalRows = [];

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Boleto — Remessa');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-boleto-remessa-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma remessa';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-unidades__input';
    }

    protected function erpSimpleListDefaultSearchColumn(): string
    {
        return 'id';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'gerarRemessa';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'verTitulos';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'excluirPendente';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(BoletoRemessaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $term = trim($this->localSearch);
            $column = in_array($this->searchColumn, ['id', 'agencia', 'conta', 'carteira'], true)
                ? $this->searchColumn
                : 'id';

            $query->where($column, 'like', '%'.$term.'%');
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.boleto.remessa-screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.boleto.remessa-action-bar'),
                View::make('filament.components.erp.boleto.titulos-modal'),
            ]);
    }

    public function gerarRemessa(): void
    {
        $this->modulePending('Geração de arquivo de remessa (CNAB)');
    }

    public function excluirPendente(): void
    {
        $this->modulePending('Exclusão de remessa');
    }

    public function verTitulos(): void
    {
        $id = $this->highlightedRecordIdOrNotify('edit');

        if (! $id) {
            return;
        }

        $remessa = BoletoRemessa::query()->with('titulos')->find($id);

        if (! $remessa) {
            return;
        }

        $this->titulosModalTitulo = 'Títulos da remessa #'.$remessa->id;
        $this->titulosModalRows = $remessa->titulos
            ->map(fn ($t): array => [
                'vencimento' => optional($t->vencimento)?->format('d/m/Y') ?? '—',
                'valor' => number_format((float) $t->valor, 2, ',', '.'),
                'cliente' => mb_strtoupper((string) ($t->cliente_razao ?? '—'), 'UTF-8'),
                'documento' => (string) ($t->cliente_documento ?? '—'),
                'numero' => (string) ($t->numero_boleto ?? '—'),
            ])
            ->all();
        $this->titulosModalOpen = true;
    }

    public function closeTitulosModal(): void
    {
        $this->titulosModalOpen = false;
        $this->titulosModalTitulo = '';
        $this->titulosModalRows = [];
    }
}
