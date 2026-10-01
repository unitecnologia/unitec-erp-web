<?php

namespace App\Filament\Resources\CargaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\CargaResource;
use App\Filament\Resources\CargaResource\Pages\Concerns\ManagesCargaFormModal;
use App\Models\Carga;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

class ListCargas extends ListRecords
{
    use InteractsWithErpListPage {
        refreshTable as private erpListRefreshTable;
    }
    use ManagesCargaFormModal;

    protected static string $resource = CargaResource::class;

    protected static ?string $title = '';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'todos';

    #[Url(as: 'numero')]
    public string $numeroCarga = '';

    public string $numeroCargaApplied = '';

    /** @var array<int, string> */
    public array $selecionados = [];

    /** Sessão temporária: só restaura seleção ao voltar de F4/F6. */
    public const SESSION_SELECAO_RETORNO_IMPRESSAO = 'erp_cargas_selecionados_retorno_impressao';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Carga / Romaneio');

        $hoje = ErpTimezone::toLocal();
        $inicioMes = $hoje->copy()->startOfMonth()->format('Y-m-d');
        $hojeStr = $hoje->format('Y-m-d');

        if ($this->periodoDe === '') {
            $this->periodoDe = $inicioMes;
        }

        if ($this->periodoAte === '') {
            $this->periodoAte = $hojeStr;
        }

        if ($this->periodoDeApplied === '') {
            $this->periodoDeApplied = $this->periodoDe;
        }

        if ($this->periodoAteApplied === '') {
            $this->periodoAteApplied = $this->periodoAte;
        }

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->numeroCargaApplied = trim($this->numeroCarga);
        $this->restoreSelecionadosDoRetornoImpressao();
    }

    /**
     * Consome IDs gravados antes de F4/F6 (mesma aba). F5/Consultar não usam isto.
     */
    protected function restoreSelecionadosDoRetornoImpressao(): void
    {
        $pending = session()->pull(self::SESSION_SELECAO_RETORNO_IMPRESSAO);

        if (! is_array($pending) || $pending === []) {
            return;
        }

        $this->selecionados = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => (string) (int) $id,
            $pending,
        ))));
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-cargas-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma carga';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-cargas__numero-input',
            'edit' => 'editCarga',
            'extraKeys' => [
                'F2' => ['method' => 'createCarga'],
                'F3' => ['method' => 'editCarga'],
                'F4' => ['method' => 'imprimirRomaneioSelecionado'],
                'F5' => ['method' => 'refreshTable'],
                'F6' => ['method' => 'imprimirPedidosSelecionados'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        // recordClasses local: em todo render real a <tr> recebe erp-cargas-row--checked
        // a partir de $selecionados (não só o Alpine do clique). Highlight continua visual.
        return $this->applyErpListSelection(
            CargaResource::table($table)
        )->recordClasses(function (Model $record): array {
            $classes = [];

            if (in_array((string) (int) $record->getKey(), $this->selecionados, true)) {
                $classes['erp-cargas-row--checked'] = true;
            }

            if ((int) ($this->highlightedRecordId ?? 0) === (int) $record->getKey()) {
                $classes['erp-row-selected'] = true;
            }

            return $classes;
        });
    }

    /**
     * @return array<int, string>
     */
    protected function erpListRecordClasses(Model $record): array
    {
        if (in_array((string) (int) $record->getKey(), $this->selecionados, true)) {
            return ['erp-cargas-row--checked'];
        }

        return [];
    }

    public function syncSelecionado(int $id, bool $checked): void
    {
        $key = (string) (int) $id;
        $this->selecionados = array_values(array_unique(array_map(
            static fn (mixed $item): string => (string) (int) $item,
            $this->selecionados,
        )));

        $has = in_array($key, $this->selecionados, true);

        if ($checked && ! $has) {
            $this->selecionados[] = $key;
        } elseif (! $checked && $has) {
            $this->selecionados = array_values(array_filter(
                $this->selecionados,
                static fn (string $item): bool => $item !== $key,
            ));
        }

        // Highlight permanece só visual (clique na linha). Não decide ações de negócio.
        $this->skipRender();
    }

    public function refreshTable(): void
    {
        $this->selecionados = [];
        $this->highlightedRecordId = null;
        session()->forget(self::SESSION_SELECAO_RETORNO_IMPRESSAO);
        $this->erpListRefreshTable();
    }

    public function consultar(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->numeroCargaApplied = trim($this->numeroCarga);
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->selecionados = [];
        session()->forget(self::SESSION_SELECAO_RETORNO_IMPRESSAO);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $query = parent::getTableQuery()
            ->with(['motorista:id,proprietario,apelido', 'entregador:id,name', 'veiculo:id,placa,descricao'])
            ->withCount('pedidos')
            ->withSum('pedidos', 'total');

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        if ($this->statusFilter !== 'todos') {
            $query->where('status', $this->statusFilter);
        }

        if (filled($this->periodoDeApplied)) {
            $query->whereDate('data', '>=', $this->periodoDeApplied);
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereDate('data', '<=', $this->periodoAteApplied);
        }

        if (filled($this->numeroCargaApplied)) {
            $term = ltrim(trim($this->numeroCargaApplied), '0') ?: '0';
            $query->where('numero', 'like', '%'.$term.'%');
        }

        return $query;
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        $allowed = ['todos', Carga::STATUS_ABERTA, Carga::STATUS_FECHADA, Carga::STATUS_CANCELADA];

        return in_array($value, $allowed, true) ? (string) $value : 'todos';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.cargas.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.cargas.action-bar'),
                View::make('filament.components.erp.cargas.form-modal'),
            ]);
    }
}
