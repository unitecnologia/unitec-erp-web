<?php

namespace App\Filament\Resources\CaixaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\CaixaResource;
use App\Filament\Resources\CaixaResource\Pages\Concerns\ManagesCaixaViewModal;
use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Pdv\PdvCaixaFechamentoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListCaixa extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesCaixaViewModal;

    protected static string $resource = CaixaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    #[Url(as: 'conta')]
    public string $contaFilter = 'todas';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Caixa');

        // Sessões PDV fechadas antes da correção: gera o lançamento faltante no Livro Caixa.
        app(PdvCaixaFechamentoService::class)->backfillSessoesRecentes();

        // Sem filtro de período padrão: campos vazios = listar todos os lançamentos.
        // O usuário aplica o intervalo explicitamente em "Filtrar Período".
        // (Antes o mês atual escondia lançamentos migrados de outros períodos.)
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-caixa-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um lançamento';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-caixa__input',
            'create' => 'createLancamento',
            'edit' => 'editLancamento',
            'delete' => 'deleteLancamento',
            'extraKeys' => [
                'F6' => ['method' => 'modulePending', 'params' => ['Imprimir']],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(CaixaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['conta']);

        $this->applyContaFilter($query);

        if (filled($this->periodoDeApplied)) {
            $query->whereDate('emissao', '>=', $this->periodoDeApplied);
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereDate('emissao', '<=', $this->periodoAteApplied);
        }

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function buildSaldoQuery(): Builder
    {
        $query = CaixaLancamento::query();

        $this->applyContaFilter($query);

        return $query;
    }

    protected function applyContaFilter(Builder $query): void
    {
        if ($this->contaFilter === 'todas') {
            return;
        }

        if (is_numeric($this->contaFilter)) {
            $query->where('caixa_conta_id', (int) $this->contaFilter);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return ['codigo', 'emissao', 'documento', 'historico', 'plano_contas', 'conta', 'entrada', 'saida'];
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)
            ? $this->searchColumn
            : 'codigo';

        $like = '%' . $term . '%';

        match ($column) {
            'codigo' => $this->applyLocalSearchByCodigo($query, $term),
            'emissao' => $this->applyLocalSearchByEmissao($query, $term),
            'documento' => $query->where('documento', 'like', $like),
            'historico' => $query->where('historico', 'like', $like),
            'plano_contas' => $query->where('plano_contas', 'like', $like),
            'conta' => $query->whereHas('conta', fn (Builder $contaQuery): Builder => $contaQuery->where('nome', 'like', $like)),
            'entrada' => $this->applyLocalSearchByMoney($query, $term, 'entrada'),
            'saida' => $this->applyLocalSearchByMoney($query, $term, 'saida'),
        };
    }

    protected function applyLocalSearchByCodigo(Builder $query, string $term): void
    {
        $digits = preg_replace('/\D/', '', $term) ?? '';

        if ($digits !== '' && is_numeric($digits)) {
            $query->where('codigo', 'like', '%' . $digits . '%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw('CAST(codigo AS TEXT) LIKE ?', ['%' . $term . '%']);

            return;
        }

        $query->whereRaw('CAST(codigo AS CHAR) LIKE ?', ['%' . $term . '%']);
    }

    protected function applyLocalSearchByEmissao(Builder $query, string $term): void
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $term, $matches)) {
            $query->whereDate('emissao', "{$matches[3]}-{$matches[2]}-{$matches[1]}");

            return;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $term)) {
            $query->whereDate('emissao', $term);

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("strftime('%d/%m/%Y', emissao) LIKE ?", ['%' . $term . '%']);

            return;
        }

        $query->whereRaw("DATE_FORMAT(emissao, '%d/%m/%Y') LIKE ?", ['%' . $term . '%']);
    }

    protected function applyLocalSearchByMoney(Builder $query, string $term, string $column): void
    {
        $normalized = str_replace(['R$', ' '], '', $term);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            if ($this->databaseDriver($query) === 'sqlite') {
                $query->whereRaw("CAST({$column} AS TEXT) LIKE ?", ['%' . $normalized . '%']);

                return;
            }

            $query->where($column, 'like', '%' . $normalized . '%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', {$column}), '.', ',') LIKE ?", ['%' . $term . '%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT({$column}, 2), '.', ',') LIKE ?", ['%' . $term . '%']);
    }

    protected function databaseDriver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
    }

    #[Computed]
    public function contasOptions(): array
    {
        return CaixaConta::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    #[Computed]
    public function saldoAnterior(): float
    {
        if (! filled($this->periodoDeApplied)) {
            return 0.0;
        }

        $query = $this->buildSaldoQuery()
            ->whereDate('emissao', '<', $this->periodoDeApplied);

        return (float) $query->sum('entrada') - (float) $query->sum('saida');
    }

    #[Computed]
    public function totalEntrada(): float
    {
        return (float) $this->buildListQuery()->sum('entrada');
    }

    #[Computed]
    public function totalSaida(): float
    {
        return (float) $this->buildListQuery()->sum('saida');
    }

    #[Computed]
    public function saldoAtual(): float
    {
        return $this->saldoAnterior + $this->totalEntrada - $this->totalSaida;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.caixa.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.caixa.footer-summary'),
                View::make('filament.components.erp.caixa.action-bar'),
                View::make('filament.components.erp.caixa.view-modal'),
            ]);
    }

    public function applyPeriodFilter(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Período filtrado.')
            ->success()
            ->send();
    }

    public function updatedContaFilter(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'codigo';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function search(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function createLancamento(): void
    {
        $this->modulePending('Cadastro de lançamento (Fase 2)');
    }

    public function editLancamento(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $this->modulePending('Alteração de lançamento (Fase 2)');
    }

    public function deleteLancamento(): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        CaixaLancamento::query()->whereKey($recordId)->delete();

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Lançamento excluído.')
            ->success()
            ->send();
    }
}
