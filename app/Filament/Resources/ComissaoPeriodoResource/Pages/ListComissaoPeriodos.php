<?php

namespace App\Filament\Resources\ComissaoPeriodoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\ComissaoPeriodoResource;
use App\Filament\Resources\ContaPagarResource;
use App\Models\ComissaoPeriodo;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ComissaoPeriodoService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Url;

class ListComissaoPeriodos extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;

    protected static string $resource = ComissaoPeriodoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'todos';

    #[Url(as: 'vendedor')]
    public string $vendedorFilter = 'todos';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public string $calcularVendedorId = '';

    public bool $cancelModalOpen = false;

    public string $cancelMotivo = '';

    public function mount(): void
    {
        parent::mount();
        ErpScreen::set('Comissões');
        $this->aplicarPeriodoMensalPadrao();
        $this->syncPagasPendentes();
    }

    protected function aplicarPeriodoMensalPadrao(): void
    {
        if ($this->periodoDe !== '' || $this->periodoAte !== '') {
            return;
        }

        $hoje = ErpTimezone::toLocal();
        $inicio = $hoje->copy()->startOfMonth()->toDateString();
        $fim = $hoje->copy()->endOfMonth()->toDateString();
        $this->periodoDe = $inicio;
        $this->periodoAte = $fim;
        $this->periodoDeApplied = $inicio;
        $this->periodoAteApplied = $fim;
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-comissoes-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma comissão';
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(
            ComissaoPeriodoResource::table($table)
        );
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['vendedor', 'contaPagar', 'fechadoPor']);

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        if ($this->statusFilter !== '' && $this->statusFilter !== 'todos') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->vendedorFilter !== '' && $this->vendedorFilter !== 'todos' && is_numeric($this->vendedorFilter)) {
            $query->where('vendedor_id', (int) $this->vendedorFilter);
        }

        if (filled($this->periodoDeApplied) && filled($this->periodoAteApplied)) {
            // Interseção com o filtro de histórico (não só igualdade)
            $query->whereDate('periodo_de', '<=', $this->periodoAteApplied)
                ->whereDate('periodo_ate', '>=', $this->periodoDeApplied);
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.components.erp.comissoes.screen'),
            EmbeddedTable::make(),
            View::make('filament.components.erp.comissoes.detalhe'),
            View::make('filament.components.erp.comissoes.action-bar'),
            View::make('filament.components.erp.comissoes.cancel-modal'),
        ]);
    }

    public function consultar(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedStatusFilter(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedVendedorFilter(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    /**
     * Filtro e "Calcular para": só operadores atuais (ativo + efetua_venda + RH + empresa).
     * Value = vendedores.id; label = código/nome do RH.
     *
     * @return array<int|string, string>
     */
    public function vendedorOptions(): array
    {
        $empresaId = ErpContext::currentEmpresaId();

        $query = Vendedor::query()
            ->where('ativo', true)
            ->where('efetua_venda', true)
            ->whereHas('rhFuncionario')
            ->with('rhFuncionario');

        if ($empresaId) {
            $query->whereHas(
                'empresas',
                fn ($q) => $q->where('empresas.id', (int) $empresaId)
            );
        }

        return $query
            ->get(['id', 'codigo', 'nome'])
            ->sortBy(fn (Vendedor $v): int => (int) preg_replace('/\D/', '', (string) ($v->rhFuncionario?->codigo ?? '0')))
            ->mapWithKeys(function (Vendedor $v): array {
                $rh = $v->rhFuncionario;
                $codigo = trim((string) ($rh?->codigo ?? $v->codigo ?? ''));
                $nome = trim((string) ($rh?->nome ?? $v->nome ?? ''));
                $label = trim(($codigo !== '' ? $codigo.' - ' : '').$nome);

                return [
                    (string) $v->id => $label !== '' ? $label : (string) ($v->nome ?? ''),
                ];
            })
            ->all();
    }

    public function calcularComissao(): void
    {
        if (! $this->erpAuthorizeOrNotify('comissoes.create')) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            Notification::make()->title('Selecione a empresa.')->warning()->send();

            return;
        }

        $vendedorId = (int) ($this->calcularVendedorId !== '' && $this->calcularVendedorId !== 'todos'
            ? $this->calcularVendedorId
            : ($this->vendedorFilter !== 'todos' ? $this->vendedorFilter : 0));

        if ($vendedorId <= 0) {
            Notification::make()->title('Selecione o vendedor para calcular.')->warning()->send();

            return;
        }

        $de = Carbon::parse($this->periodoDe ?: $this->periodoDeApplied)->startOfDay();
        $ate = Carbon::parse($this->periodoAte ?: $this->periodoAteApplied)->startOfDay();

        try {
            $periodo = app(ComissaoPeriodoService::class)->calcular($empresaId, $vendedorId, $de, $ate);
            $this->highlightedRecordId = (int) $periodo->id;
            $this->resetTable();
            Notification::make()
                ->title('Comissão calculada (Aberta).')
                ->body('Total: R$ '.number_format((float) $periodo->comissao_total, 2, ',', '.'))
                ->success()
                ->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }

    public function fecharComissao(): void
    {
        if (! $this->erpAuthorizeOrNotify('comissoes.update')) {
            return;
        }

        $id = $this->highlightedRecordIdOrNotify('update');
        if (! $id) {
            return;
        }

        try {
            $periodo = app(ComissaoPeriodoService::class)->fechar($id);
            $this->highlightedRecordId = (int) $periodo->id;
            $this->resetTable();
            $cap = $periodo->contaPagar?->numero;
            Notification::make()
                ->title('Comissão fechada.')
                ->body($cap ? 'CAP '.$cap.' gerado automaticamente.' : 'Fechamento concluído.')
                ->success()
                ->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Não foi possível fechar.')->body($e->getMessage())->danger()->send();
        }
    }

    public function abrirCancelarComissao(): void
    {
        if (! $this->highlightedRecordIdOrNotify('update')) {
            return;
        }
        $this->cancelMotivo = '';
        $this->cancelModalOpen = true;
    }

    public function confirmarCancelarComissao(): void
    {
        if (! $this->erpAuthorizeOrNotify('comissoes.update')) {
            return;
        }

        $id = $this->highlightedRecordId;
        if (! $id) {
            return;
        }

        try {
            app(ComissaoPeriodoService::class)->cancelar((int) $id, $this->cancelMotivo);
            $this->cancelModalOpen = false;
            $this->resetTable();
            Notification::make()->title('Comissão cancelada.')->success()->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function abrirCap(): void
    {
        $id = $this->highlightedRecordIdOrNotify('update');
        if (! $id) {
            return;
        }

        $periodo = ComissaoPeriodo::query()->find($id);
        $capId = (int) ($periodo?->conta_pagar_id ?? 0);
        if ($capId <= 0) {
            Notification::make()->title('Esta comissão ainda não possui Conta a Pagar.')->warning()->send();

            return;
        }

        $this->redirect(ContaPagarResource::getUrl('index', ['q' => $periodo?->contaPagar?->numero]));
    }

    /**
     * @return list<array{venda_id: int, numero: string, data: string, base: string, tipo: string, percentual: string, comissao: string}>
     */
    public function snapshotLinhas(): array
    {
        if (! $this->highlightedRecordId) {
            return [];
        }

        $periodo = ComissaoPeriodo::query()
            ->with(['vendas.venda'])
            ->find($this->highlightedRecordId);

        if (! $periodo) {
            return [];
        }

        return $periodo->vendas
            ->sortBy('data')
            ->values()
            ->map(fn ($linha): array => [
                'venda_id' => (int) $linha->venda_id,
                'numero' => (string) ($linha->venda?->numero ?? $linha->venda_id),
                'data' => $linha->data?->format('d/m/Y') ?? '—',
                'base' => number_format((float) $linha->base, 2, ',', '.'),
                'tipo' => strtoupper((string) $linha->tipo),
                'percentual' => number_format((float) $linha->percentual, 2, ',', '.').'%',
                'comissao' => number_format((float) $linha->comissao, 2, ',', '.'),
            ])
            ->all();
    }

    private function syncPagasPendentes(): void
    {
        $service = app(ComissaoPeriodoService::class);
        ComissaoPeriodo::query()
            ->where('status', ComissaoPeriodo::STATUS_FECHADA)
            ->whereNotNull('conta_pagar_id')
            ->with('contaPagar')
            ->limit(100)
            ->get()
            ->each(function (ComissaoPeriodo $p) use ($service): void {
                if ($p->contaPagar) {
                    $service->syncStatusPagaFromContaPagar($p->contaPagar);
                }
            });
    }
}
