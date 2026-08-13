<?php

namespace App\Filament\Resources\BoletoRetornoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\BoletoRetornoResource;
use App\Models\BoletoRetorno;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListBoletoRetornos extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;

    protected static string $resource = BoletoRetornoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'arquivo_nome';

    public bool $titulosModalOpen = false;

    public string $titulosModalTitulo = '';

    /** @var array<int, array<string, string>> */
    public array $titulosModalRows = [];

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Boleto — Retorno');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-boleto-retorno-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um retorno';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-unidades__input';
    }

    protected function erpSimpleListDefaultSearchColumn(): string
    {
        return 'arquivo_nome';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'importarRetorno';
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
        return $this->applyErpListSelection(BoletoRetornoResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $term = trim($this->localSearch);
            $column = in_array($this->searchColumn, ['id', 'arquivo_nome', 'arquivo_numero'], true)
                ? $this->searchColumn
                : 'arquivo_nome';

            $query->where($column, 'like', '%'.$term.'%');
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.boleto.retorno-screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.boleto.retorno-action-bar'),
                View::make('filament.components.erp.boleto.titulos-modal'),
            ]);
    }

    public function importarRetorno(): void
    {
        $this->modulePending('Importação de arquivo de retorno (CNAB)');
    }

    public function excluirPendente(): void
    {
        $this->modulePending('Exclusão de retorno');
    }

    public function verTitulos(): void
    {
        $id = $this->highlightedRecordIdOrNotify('edit');

        if (! $id) {
            return;
        }

        $retorno = BoletoRetorno::query()->with('titulos')->find($id);

        if (! $retorno) {
            return;
        }

        $this->titulosModalTitulo = 'Títulos do retorno #'.$retorno->id;
        $this->titulosModalRows = $retorno->titulos
            ->map(fn ($t): array => [
                'vencimento' => optional($t->data_ocorrencia)?->format('d/m/Y') ?? '—',
                'valor' => number_format((float) $t->valor_pago, 2, ',', '.'),
                'cliente' => mb_strtoupper((string) ($t->tipo_ocorrencia_desc ?: $t->historico ?: '—'), 'UTF-8'),
                'documento' => (string) ($t->nosso_numero ?? $t->seu_numero ?? '—'),
                'numero' => (string) ($t->titulo_legado ?? '—'),
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
