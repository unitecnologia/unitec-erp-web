<?php

namespace App\Filament\Resources\ExpedicaoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Pages\ExpedicaoBipagemPage;
use App\Filament\Resources\ExpedicaoResource;
use App\Models\Entrega;
use App\Models\Venda;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Expedicao\ExpedicaoConfig;
use App\Support\Logistica\ExpedicaoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListExpedicaoControle extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = ExpedicaoResource::class;

    protected static ?string $title = '';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'pendentes';

    #[Url(as: 'pedido')]
    public string $numeroPedido = '';

    /** @var array<int, string> */
    public array $selecionados = [];

    public bool $limitePedidosAvisoOpen = false;

    public int $limitePedidosAvisoMax = 0;

    public ?string $selecionadoLimitePendente = null;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Controle de Expedição');

        $hoje = ErpTimezone::toLocal();

        if ($this->periodoDe === '') {
            $this->periodoDe = $hoje->format('Y-m-d');
        }

        if ($this->periodoAte === '') {
            $this->periodoAte = $hoje->format('Y-m-d');
        }

        if ($this->periodoDeApplied === '') {
            $this->periodoDeApplied = $this->periodoDe;
        }

        if ($this->periodoAteApplied === '') {
            $this->periodoAteApplied = $this->periodoAte;
        }

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-expedicao-controle-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um pedido';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-expedicao__pedido-input',
            'extraKeys' => [
                'F5' => ['method' => 'refreshTable'],
                'F9' => ['method' => 'confirmarSelecionados'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return ExpedicaoResource::table($table)
            ->defaultSort(null)
            ->recordUrl(null)
            ->recordAction('highlightRecord')
            ->recordClasses(function (Model $record): string {
                $classes = ['erp-expedicao-row--' . ($record->status ?? 'pendente')];

                if ($this->highlightedRecordId === $record->getKey()) {
                    $classes[] = 'erp-row-selected';
                }

                if (in_array((string) $record->getKey(), $this->selecionados, true)) {
                    $classes[] = 'erp-expedicao-row--checked';
                }

                return implode(' ', $classes);
            });
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['venda', 'usuarioExpedicao'])
            ->whereIn((new Entrega)->qualifyColumn('status'), Entrega::statusControleFiltro($this->statusFilter));

        if (filled($this->periodoDeApplied)) {
            $query->whereHas('venda', fn (Builder $v): Builder => $v->whereDate('data', '>=', $this->periodoDeApplied));
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereHas('venda', fn (Builder $v): Builder => $v->whereDate('data', '<=', $this->periodoAteApplied));
        }

        if (filled(trim($this->numeroPedido))) {
            $term = ltrim(trim($this->numeroPedido), '0') ?: '0';
            $query->whereHas('venda', fn (Builder $v): Builder => $v->where('numero', 'like', '%' . $term . '%'));
        }

        $entregaTable = (new Entrega)->getTable();
        $vendaTable = (new Venda)->getTable();

        return $query
            ->leftJoin("{$vendaTable} as expedicao_controle_vendas", 'expedicao_controle_vendas.id', '=', "{$entregaTable}.venda_id")
            ->reorder()
            ->orderByDesc('expedicao_controle_vendas.numero')
            ->orderByDesc("{$entregaTable}.id")
            ->select("{$entregaTable}.*");
    }

    #[Computed]
    public function totalListaFormatado(): string
    {
        $total = (float) Venda::query()
            ->whereIn('id', $this->getTableQuery()->select((new Entrega)->qualifyColumn('venda_id')))
            ->sum('total');

        return 'R$ ' . number_format($total, 2, ',', '.');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.expedicao.controle-screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.expedicao.controle-footer'),
            ]);
    }

    public function consultar(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->selecionados = [];
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedStatusFilter(): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->selecionados = [];
        $this->resetTable();
    }

    public function updatedNumeroPedido(): void
    {
        $this->selecionados = [];
        $this->resetTable();
    }

    public function desmarcarTodos(): void
    {
        $this->selecionados = [];
    }

    public function marcarTodos(): void
    {
        $max = ExpedicaoConfig::make()->maxPedidosControle();
        $ids = $this->getTableQuery()
            ->pluck((new Entrega)->qualifyColumn('id'))
            ->map(fn ($id): string => (string) $id)
            ->take($max)
            ->all();

        $this->selecionados = $ids;

        if ($this->getTableQuery()->count() > $max) {
            $this->notifyLimitePedidosSelecionados($max);
        }
    }

    public function toggleSelecionado(int $id): void
    {
        $key = (string) $id;

        if (in_array($key, $this->selecionados, true)) {
            $this->selecionados = array_values(array_filter(
                $this->selecionados,
                fn (string $value): bool => $value !== $key,
            ));

            return;
        }

        $max = ExpedicaoConfig::make()->maxPedidosControle();

        if (count($this->selecionados) >= $max) {
            $this->notifyLimitePedidosSelecionados($max, $id);

            return;
        }

        $this->selecionados[] = $key;
    }

    public function confirmarSelecionados(): void
    {
        if (! ErpAccess::currentCan('logistica.update')) {
            Notification::make()->title('Sem permissão.')->danger()->send();

            return;
        }

        $ids = array_values(array_filter(array_map('intval', $this->selecionados)));

        if ($ids === []) {
            Notification::make()
                ->title('Selecione ao menos um pedido.')
                ->warning()
                ->send();

            return;
        }

        $max = ExpedicaoConfig::make()->maxPedidosControle();

        if (count($ids) > $max) {
            $this->notifyLimitePedidosSelecionados($max);

            return;
        }

        (new ExpedicaoService())->iniciarSessao($ids);

        $this->redirect(ExpedicaoBipagemPage::getUrl(['ids' => implode(',', $ids)]));
    }

    public function refreshTable(): void
    {
        $this->resetTable();

        Notification::make()->title('Lista atualizada.')->success()->send();
    }

    private function normalizeStatusFilter(string $status): string
    {
        return in_array($status, ['pendentes', 'expedidos', 'todos'], true) ? $status : 'pendentes';
    }

    private function notifyLimitePedidosSelecionados(int $max, ?int $idTentativa = null): void
    {
        $this->limitePedidosAvisoMax = $max;
        $this->selecionadoLimitePendente = $idTentativa !== null ? (string) $idTentativa : null;
        $this->limitePedidosAvisoOpen = true;
    }

    public function fecharLimitePedidosAviso(): void
    {
        $this->enforceLimiteSelecionados($this->selecionadoLimitePendente);
        $this->selecionadoLimitePendente = null;
        $this->limitePedidosAvisoOpen = false;
        $this->resetTable();
    }

    private function enforceLimiteSelecionados(?string $removerId = null): void
    {
        $max = ExpedicaoConfig::make()->maxPedidosControle();

        if ($removerId !== null) {
            $this->selecionados = array_values(array_filter(
                $this->selecionados,
                fn (string $value): bool => $value !== $removerId,
            ));
        }

        if (count($this->selecionados) > $max) {
            $this->selecionados = array_values(array_slice($this->selecionados, 0, $max));
        }
    }
}
