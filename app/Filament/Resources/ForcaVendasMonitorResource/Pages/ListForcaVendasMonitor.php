<?php

namespace App\Filament\Resources\ForcaVendasMonitorResource\Pages;

use App\Filament\Pages\ForcaVendasTelaVendaPage;
use App\Livewire\Erp\ForcaVendasMonitorDetalhePanel;
use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Filament\Resources\NfeResource;
use App\Models\ContaReceber;
use App\Models\ErpUserPreference;
use App\Models\ForcaVendasOrder;
use App\Models\FormaPagamento;
use App\Models\Pedido;
use App\Models\Person;
use App\Models\PixCobranca;
use App\Models\Vendedor;
use App\Models\Venda;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaReceberJurosCarteira;
use App\Support\Erp\Nfe\NfeMonitorEmitenteResolver;
use App\Support\Erp\Nfe\NfeVendaLoteEmissionService;
use App\Support\Erp\Nfe\NfeVendaMercadoriaService;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use App\Support\ForcaVendas\ForcaVendasCancelamentoBoletoPendenteException;
use App\Support\ForcaVendas\ForcaVendasFaturamentoService;
use App\Support\ForcaVendas\ForcaVendasMonitorCancelamentoService;
use App\Support\ForcaVendas\ForcaVendasTelaVendaService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListForcaVendasMonitor extends ListRecords
{
    use InteractsWithErpListPage;
    use Concerns\ManagesForcaVendasMonitorEmailModal;
    use Concerns\ManagesForcaVendasMonitorNfeEmitenteModal;
    use Concerns\ProvidesForcaVendasMonitorSelecaoUi;

    protected static string $resource = ForcaVendasMonitorResource::class;

    protected static ?string $title = '';

    #[Url(as: 'situacao')]
    public string $situacaoFilter = 'pendente';

    #[Url(as: 'campo')]
    public string $filtroCampo = 'cliente';

    #[Url(as: 'busca')]
    public string $filtroValor = 'todos';

    #[Url(as: 'plataforma')]
    public string $plataformaFilter = 'todos';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    /**
     * IDs dos pedidos marcados (check-boxes) para faturar/cancelar em lote.
     *
     * @var array<int, string>
     */
    public array $selecionados = [];

    public bool $financeiroModalOpen = false;

    public ?int $financeiroOrderId = null;

    public bool $cancelarPedidosModalOpen = false;

    public bool $clonarPedidoModalOpen = false;

    public ?int $clonarPedidoOrderId = null;

    public bool $clonarProgressOpen = false;

    public bool $clonarOk = false;

    public ?int $clonarNovoId = null;

    public string $clonarEtapa = '';

    public string $clonarErro = '';

    public string $cancelarPedidosMotivo = '';

    public int $cancelarPedidosQuantidade = 0;

    public bool $cancelarProgressOpen = false;

    public int $cancelarAtual = 0;

    public int $cancelarTotal = 0;

    public string $cancelarProgressStatus = '';

    public string $cancelarMotivoAplicado = '';

    /** @var list<array{id: int, numero: string, pendente: bool}> */
    public array $cancelarFila = [];

    public int $cancelarFilaIndex = 0;

    public ?int $cancelarBoletoAutorizadoId = null;

    /** @var list<array{id: string, ok: bool, numero: string, etapa: string, erro: ?string}> */
    public array $cancelarResultados = [];

    public bool $reabrirProgressOpen = false;

    public ?int $reabrirOrderId = null;

    public string $reabrirNumero = '';

    public string $reabrirProgressStatus = '';

    public ?int $reabrirBoletoAutorizadoId = null;

    public bool $reabrirOk = false;

    public bool $reabrirSemVenda = false;

    public bool $reabrirTemBoleto = false;

    public string $reabrirEtapa = '';

    public string $reabrirErro = '';

    /** Overlay de transmissão em lote (permanece no Monitor). */
    public bool $nfeLoteProgressOpen = false;

    public bool $nfeLoteResumoOpen = false;

    public int $nfeLoteAtual = 0;

    public int $nfeLoteTotal = 0;

    public int $nfeLoteProgressStep = 0;

    public string $nfeLoteProgressStatus = '';

    public string $nfeLoteResumoTitulo = '';

    public string $nfeLoteResumoTexto = '';

    /** @var list<int> */
    public array $nfeLoteFilaVendaIds = [];

    public int $nfeLoteFilaIndex = 0;

    /** @var list<array{ok: bool, venda_id: int, numero: ?string, erro: ?string}> */
    public array $nfeLoteResultados = [];

    /**
     * Emitente fixada para todo o lote (Monitor multi-empresa).
     * Null = fluxo antigo (empresa da sessão).
     */
    public ?int $nfeLoteEmpresaEmitenteId = null;

    public bool $faturarProgressOpen = false;

    public int $faturarAtual = 0;

    public int $faturarTotal = 0;

    public string $faturarProgressStatus = '';

    /** @var list<array{id: int, numero: string}> */
    public array $faturarFila = [];

    public int $faturarFilaIndex = 0;

    public int $faturarIgnorados = 0;

    /** @var list<array{id: string, ok: bool, numero: string, etapa: string, erro: ?string}> */
    public array $faturarResultados = [];

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Monitor de Vendas');

        $this->situacaoFilter = $this->resolveSituacaoFilterOnMount();

        // Padrão: do dia de hoje até o fim do mês. Usa o fuso local
        // (America/Sao_Paulo); senão as datas saem +1 dia, pois o servidor é UTC.
        $hoje = ErpTimezone::toLocal();

        if ($this->periodoDe === '') {
            $this->periodoDe = $hoje->format('Y-m-d');
        }

        if ($this->periodoAte === '') {
            $this->periodoAte = $hoje->copy()->endOfMonth()->format('Y-m-d');
        }

        if ($this->periodoDeApplied === '') {
            $this->periodoDeApplied = $this->periodoDe;
        }

        if ($this->periodoAteApplied === '') {
            $this->periodoAteApplied = $this->periodoAte;
        }
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-fv-monitor-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um pedido';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => null,
            'create' => null,
            'edit' => null,
            'delete' => null,
            'refresh' => null,
            'rowSelection' => false,
            'extraKeys' => [
                'F9' => ['method' => 'openEmailModal'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        // Seleção do pedido é feita apenas pela flag da linha; clicar em
        // qualquer outra célula não altera nada.
        return ForcaVendasMonitorResource::table($table)
            ->recordUrl(null)
            ->recordAction(null)
            ->recordClasses(function (Model $record): string {
                $situacao = $record->situacao ?? ForcaVendasOrder::SITUACAO_PENDENTE;
                $tint = 'erp-fv-mon--' . $situacao;

                $classes = [$tint];

                if (in_array((string) $record->getKey(), $this->selecionados, true)) {
                    $classes[] = 'erp-row-selected';
                }

                return implode(' ', $classes);
            });
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->where('tipo', 'pedido')
            ->with([
                'user',
                'vendedor',
                'pedido.itens',
                'cliente',
                'venda.nfes',
                'venda.pdvVenda.nfce',
            ]);

        if (array_key_exists($this->situacaoFilter, ForcaVendasOrder::situacaoLabels())) {
            if ($this->situacaoFilter === ForcaVendasOrder::SITUACAO_PENDENTE) {
                // Inclui pedidos aguardando liberação financeira na visão padrão.
                $query->whereIn('situacao', [
                    ForcaVendasOrder::SITUACAO_PENDENTE,
                    ForcaVendasOrder::SITUACAO_FINANCEIRO,
                ]);
            } else {
                $query->where('situacao', $this->situacaoFilter);
            }
        }

        // Período na timezone do ERP (America/Sao_Paulo). As datas em
        // client_created_at / received_at são gravadas no fuso do app — não converter
        // para UTC aqui, senão pedidos entre 00:00 e 02:59 sumem do filtro "hoje".
        if (filled($this->periodoDeApplied)) {
            $inicio = Carbon::parse($this->periodoDeApplied, ErpTimezone::DEFAULT)->startOfDay();
            $query->where(function (Builder $q) use ($inicio): void {
                $q->where('client_created_at', '>=', $inicio)
                    ->orWhere(fn (Builder $q2) => $q2
                        ->whereNull('client_created_at')
                        ->where('received_at', '>=', $inicio));
            });
        }

        if (filled($this->periodoAteApplied)) {
            $fim = Carbon::parse($this->periodoAteApplied, ErpTimezone::DEFAULT)->endOfDay();
            $query->where(function (Builder $q) use ($fim): void {
                $q->where('client_created_at', '<=', $fim)
                    ->orWhere(fn (Builder $q2) => $q2
                        ->whereNull('client_created_at')
                        ->where('received_at', '<=', $fim));
            });
        }

        $this->aplicarFiltroUnificado($query);
        $this->aplicarFiltroPlataforma($query);

        return $query;
    }

    /**
     * Filtro dedicado de plataforma/canal de venda (ao lado do filtro unificado).
     */
    protected function aplicarFiltroPlataforma(Builder $query): void
    {
        $plataforma = trim($this->plataformaFilter);

        if ($plataforma === '' || $plataforma === 'todos') {
            return;
        }

        if ($plataforma === 'mobile') {
            return;
        }

        if ($plataforma === 'vi') {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.origem')) = 'vendas_internas'");

            return;
        }

        if ($plataforma === 'fv') {
            $query->where(function (Builder $q): void {
                $q->whereNull('payload')
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.origem')) IS NULL")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.origem')) NOT IN ('vendas_internas', 'mercado_livre')");
            });

            return;
        }

        if ($plataforma === 'meli') {
            $query->where(function (Builder $q): void {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.origem')) = 'mercado_livre'")
                    ->orWhereNotNull('meli_order_id');
            });

            return;
        }

        // Demais marketplaces ainda não geram pedidos no monitor.
        $query->whereRaw('0 = 1');
    }

    /**
     * Aplica o filtro unificado (campo + valor) à query, conforme o tipo do campo.
     */
    protected function aplicarFiltroUnificado(Builder $query): void
    {
        $campo = $this->filtroCampo;
        $valor = trim((string) $this->filtroValor);

        if ($valor === '' || $valor === 'todos') {
            return;
        }

        switch ($campo) {
            case 'dav':
            case 'pedido':
                $query->whereHas('venda', fn (Builder $s) => $s->where('numero', 'like', '%' . $valor . '%'));
                break;

            case 'meio_pgto':
                $query->where(function (Builder $q) use ($valor): void {
                    $q->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.forma_pagamento'))) = ?", [mb_strtolower($valor, 'UTF-8')])
                        ->orWhereHas('pedido', fn (Builder $s) => $s
                            ->whereRaw('LOWER(forma_pagamento) = ?', [mb_strtolower($valor, 'UTF-8')]));
                });
                break;

            case 'cliente':
                if (is_numeric($valor)) {
                    $query->where('cliente_id', (int) $valor);
                }
                break;

            case 'vendedor':
                if (is_numeric($valor)) {
                    $query->where('vendedor_id', (int) $valor);
                }
                break;

            case 'status':
                if (array_key_exists($valor, ForcaVendasOrder::situacaoLabels())) {
                    $query->where('situacao', $valor);
                }
                break;

            case 'data_abert':
                [$ini, $fim] = $this->intervaloDoDia($valor);
                $query->where(function (Builder $q) use ($ini, $fim): void {
                    $q->whereBetween('client_created_at', [$ini, $fim])
                        ->orWhere(fn (Builder $q2) => $q2
                            ->whereNull('client_created_at')
                            ->whereBetween('received_at', [$ini, $fim]));
                });
                break;

            case 'sincronizado':
                [$ini, $fim] = $this->intervaloDoDia($valor);
                $query->whereBetween('received_at', [$ini, $fim]);
                break;

            case 'data_fech':
                [$ini, $fim] = $this->intervaloDoDia($valor);
                $query->where(function (Builder $q) use ($ini, $fim): void {
                    $q->whereBetween('faturado_at', [$ini, $fim])
                        ->orWhere(fn (Builder $q2) => $q2->whereNull('faturado_at')->whereBetween('confirmed_at', [$ini, $fim]));
                });
                break;

            case 'tt_liquido':
                $query->where('total', '>=', $this->parseNumeroFiltro($valor));
                break;

            case 'tt_bruto':
                $num = $this->parseNumeroFiltro($valor);
                $query->whereHas('pedido', fn (Builder $s) => $s->where('subtotal', '>=', $num));
                break;

            case 'desconto':
                $num = $this->parseNumeroFiltro($valor);
                $query->whereHas('pedido', fn (Builder $s) => $s->where('desconto_valor', '>=', $num));
                break;

            case 'acrescimo':
                $num = $this->parseNumeroFiltro($valor);
                $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.frete')) AS DECIMAL(15,2)) >= ?", [$num]);
                break;
        }
    }

    /**
     * Limites (início/fim) de um dia local informado como Y-m-d, no fuso do ERP.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function intervaloDoDia(string $data): array
    {
        return [
            Carbon::parse($data, ErpTimezone::DEFAULT)->startOfDay(),
            Carbon::parse($data, ErpTimezone::DEFAULT)->endOfDay(),
        ];
    }

    protected function parseNumeroFiltro(string $valor): float
    {
        $normalizado = str_replace(['.', ' '], '', $valor);
        $normalizado = str_replace(',', '.', $normalizado);

        return (float) $normalizado;
    }

    #[Computed]
    public function clientesOptions(): array
    {
        return Person::query()
            ->where('is_cliente', true)
            ->whereIn('id', ForcaVendasOrder::query()->whereNotNull('cliente_id')->distinct()->pluck('cliente_id'))
            ->orderBy('nome_razao')
            ->pluck('nome_razao', 'id')
            ->all();
    }

    #[Computed]
    public function vendedoresOptions(): array
    {
        return Vendedor::query()
            ->whereIn('id', ForcaVendasOrder::query()->whereNotNull('vendedor_id')->distinct()->pluck('vendedor_id'))
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    #[Computed]
    public function totalFiltrado(): float
    {
        return (float) $this->getTableQuery()->sum('total');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.forca-vendas.monitor-screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.forca-vendas.monitor-detalhe-host'),
                View::make('filament.components.erp.forca-vendas.monitor-cancel-modal'),
                View::make('filament.components.erp.forca-vendas.monitor-clonar-pedido-modal'),
                View::make('filament.components.erp.forca-vendas.monitor-clonar-progress'),
                View::make('filament.components.erp.forca-vendas.monitor-financeiro-modal'),
                View::make('filament.components.erp.forca-vendas.monitor-nfe-lote-progress'),
                View::make('filament.components.erp.forca-vendas.monitor-faturar-progress'),
                View::make('filament.components.erp.forca-vendas.monitor-cancelar-progress'),
                View::make('filament.components.erp.forca-vendas.monitor-reabrir-progress'),
                View::make('filament.components.erp.forca-vendas.monitor-nfe-emitente-modal'),
                View::make('filament.components.erp.forca-vendas.monitor-email-modal'),
            ]);
    }

    #[Computed]
    public function financeiroPedido(): ?ForcaVendasOrder
    {
        if (! $this->financeiroOrderId) {
            return null;
        }

        return ForcaVendasOrder::query()
            ->with(['pedido', 'cliente'])
            ->find($this->financeiroOrderId);
    }

    public function abrirLiberacaoFinanceira(int $orderId): void
    {
        $order = ForcaVendasOrder::query()->find($orderId);
        if (! $order || $order->situacao !== ForcaVendasOrder::SITUACAO_FINANCEIRO) {
            $this->avisa('Pedido não está aguardando liberação financeira.', 'warning');

            return;
        }

        $this->financeiroOrderId = $orderId;
        $this->financeiroModalOpen = true;
    }

    public function fecharLiberacaoFinanceira(): void
    {
        $this->financeiroModalOpen = false;
        $this->financeiroOrderId = null;
    }

    public function aprovarLiberacaoFinanceira(): void
    {
        $order = $this->financeiroPedido;
        if (! $order) {
            return;
        }

        try {
            (new ForcaVendasFaturamentoService())->liberarFinanceiro($order, auth()->user());
            $this->fecharLiberacaoFinanceira();
            $this->avisa('Pedido liberado. Status: Pendente (Enviado no app).', 'success');
            $this->resetTable();
        } catch (\Throwable $e) {
            $this->avisa($e->getMessage(), 'warning');
        }
    }

    public function negarLiberacaoFinanceira(): void
    {
        $order = $this->financeiroPedido;
        if (! $order) {
            return;
        }

        try {
            (new ForcaVendasFaturamentoService())->cancelarPendente($order);
            $this->fecharLiberacaoFinanceira();
            $this->avisa('Pedido negado e cancelado.', 'success');
            $this->resetTable();
        } catch (\Throwable $e) {
            $this->avisa($e->getMessage(), 'warning');
        }
    }

    public function consultar(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->clearListSelection();
        $this->resetTable();
    }

    /**
     * Aplica o período após o Flatpickr gravar periodoDe/periodoAte
     * (um único resetTable — sem remount dos inputs, que ficam em wire:ignore).
     */
    public function applyPeriodFilterAuto(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSituacaoFilter(): void
    {
        $normalized = $this->normalizeSituacaoFilter($this->situacaoFilter)
            ?? ForcaVendasOrder::SITUACAO_PENDENTE;
        $this->situacaoFilter = $normalized;
        $this->persistSituacaoFilterPreference($normalized);

        $this->clearListSelection();
        $this->resetTable();
    }

    /**
     * URL válida só nesta abertura; senão preferência user/empresa; senão pendente.
     */
    private function resolveSituacaoFilterOnMount(): string
    {
        if (request()->has('situacao')) {
            $fromUrl = $this->normalizeSituacaoFilter(request()->query('situacao'));

            if ($fromUrl !== null) {
                return $fromUrl;
            }
        }

        return $this->restoreSituacaoFilterPreference()
            ?? ForcaVendasOrder::SITUACAO_PENDENTE;
    }

    private function normalizeSituacaoFilter(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '' || ! in_array($value, $this->allowedSituacaoFilters(), true)) {
            return null;
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function allowedSituacaoFilters(): array
    {
        return [
            'todos',
            ...array_keys(ForcaVendasOrder::situacaoLabels()),
        ];
    }

    private function restoreSituacaoFilterPreference(): ?string
    {
        $userId = (int) (Auth::id() ?? 0);
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? Auth::user()?->empresa_id ?? 0);

        if ($userId <= 0 || $empresaId <= 0) {
            return null;
        }

        return $this->normalizeSituacaoFilter(ErpUserPreference::getValue(
            $userId,
            $empresaId,
            ErpUserPreference::KEY_MONITOR_VENDAS_SITUACAO_FILTER
        ));
    }

    private function persistSituacaoFilterPreference(string $value): void
    {
        $userId = (int) (Auth::id() ?? 0);
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? Auth::user()?->empresa_id ?? 0);

        if ($userId <= 0 || $empresaId <= 0) {
            return;
        }

        ErpUserPreference::putValue(
            $userId,
            $empresaId,
            ErpUserPreference::KEY_MONITOR_VENDAS_SITUACAO_FILTER,
            $value
        );
    }

    public function updatedFiltroCampo(): void
    {
        // Ao trocar o campo, reinicia o valor conforme o tipo de controle.
        $this->filtroValor = $this->filtroCampoTipo() === 'select' ? 'todos' : '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedFiltroValor(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function clearListSelection(): void
    {
        // Trait InteractsWithErpListPage (não usar parent:: — ListRecords não tem o método).
        $this->highlightedRecordId = null;
        $this->pushSelecaoToDetalhePanel();
    }

    public function updatedPlataformaFilter(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    /**
     * Rótulos dos campos do filtro unificado (espelha as colunas do grid).
     *
     * @return array<string, string>
     */
    public function filtroCamposOptions(): array
    {
        return [
            'pedido' => 'Nº Pedido',
            'status' => 'Status',
            'cliente' => 'Cliente',
            'vendedor' => 'Vendedor',
            'data_abert' => 'Data Abertura',
            'data_fech' => 'Data Fechamento',
            'sincronizado' => 'Sincronizado',
            'desconto' => 'Desconto',
            'acrescimo' => 'Acréscimo',
            'tt_bruto' => 'TT Bruto',
            'tt_liquido' => 'TT Líquido',
            'meio_pgto' => 'Meio Pgto',
        ];
    }

    /**
     * Tipo de controle do valor para o campo atual: select | date | number | text.
     */
    public function filtroCampoTipo(): string
    {
        return match ($this->filtroCampo) {
            'cliente', 'vendedor', 'status', 'meio_pgto' => 'select',
            'data_abert', 'data_fech', 'sincronizado' => 'date',
            'desconto', 'acrescimo', 'tt_bruto', 'tt_liquido' => 'number',
            default => 'text',
        };
    }

    /**
     * Dados para o combobox de busca de clientes (nome / CNPJ) no filtro.
     *
     * @return array<int, array{id: string, nome: string, busca: string}>
     */
    public function clientesLookupData(): array
    {
        return Person::query()
            ->where('is_cliente', true)
            ->whereIn('id', ForcaVendasOrder::query()->whereNotNull('cliente_id')->distinct()->pluck('cliente_id'))
            ->orderBy('nome_razao')
            ->get(['id', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj'])
            ->map(fn (Person $p): array => [
                'id' => (string) $p->id,
                'nome' => (string) $p->nome_razao,
                'busca' => mb_strtolower(trim(
                    ($p->nome_razao ?? '') . ' '
                    . ($p->apelido_fantasia ?? '') . ' '
                    . ($p->cpf_cnpj ?? '') . ' '
                    . preg_replace('/\D/', '', (string) $p->cpf_cnpj)
                ), 'UTF-8'),
            ])
            ->values()
            ->all();
    }

    /**
     * Nome do cliente atualmente selecionado no filtro (para exibir no combobox).
     */
    public function filtroClienteNome(): string
    {
        if ($this->filtroCampo !== 'cliente' || ! is_numeric($this->filtroValor)) {
            return '';
        }

        return (string) (Person::query()->whereKey((int) $this->filtroValor)->value('nome_razao') ?? '');
    }

    #[Computed]
    public function meiosPagamentoOptions(): array
    {
        $formas = FormaPagamento::query()
            ->where('ativo', true)
            ->orderBy('descricao')
            ->pluck('descricao', 'descricao')
            ->all();

        // Meios já usados em pedidos (mesmo inativos / textos do app).
        $usados = ForcaVendasOrder::query()
            ->where('tipo', 'pedido')
            ->whereNotNull('payload')
            ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(payload, '$.forma_pagamento')) as forma")
            ->pluck('forma')
            ->filter(fn ($forma): bool => filled($forma) && $forma !== 'null')
            ->map(fn ($forma): string => trim((string) $forma))
            ->filter()
            ->all();

        foreach ($usados as $forma) {
            $formas[$forma] = $forma;
        }

        asort($formas, SORT_NATURAL | SORT_FLAG_CASE);

        $rotulos = [];

        foreach ($formas as $forma) {
            $rotulos[$forma] = $this->rotuloMeioPagamento($forma);
        }

        return $rotulos;
    }

    private function rotuloMeioPagamento(string $descricao): string
    {
        $chave = mb_strtoupper(trim($descricao), 'UTF-8');
        $chave = str_replace(['Á', 'À', 'Ã', 'Â', 'É', 'Ê', 'Í', 'Ó', 'Ô', 'Õ', 'Ú', 'Ç'], ['A', 'A', 'A', 'A', 'E', 'E', 'I', 'O', 'O', 'O', 'U', 'C'], $chave);

        return match ($chave) {
            'DINHEIRO' => 'Dinheiro',
            'PIX' => 'PIX',
            'POS DEBITO' => 'POS Débito',
            'POS CREDITO' => 'POS Crédito',
            'CREDIARIO' => 'Crediário',
            'CARTAO', 'CARTAO DE CREDITO' => 'Cartão',
            'BOLETO' => 'Boleto',
            'CHEQUE' => 'Cheque',
            default => mb_convert_case(mb_strtolower(trim($descricao), 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
        };
    }

    /**
     * Opções de valor para o campo atual quando ele for do tipo "select".
     *
     * @return array<int|string, string>
     */
    public function filtroValorOptions(): array
    {
        return match ($this->filtroCampo) {
            'cliente' => $this->clientesOptions,
            'vendedor' => $this->vendedoresOptions,
            'status' => ForcaVendasOrder::situacaoLabels(),
            'meio_pgto' => $this->meiosPagamentoOptions,
            default => [],
        };
    }

    /**
     * Dados do combo compacto do filtro unificado (select).
     *
     * @return array{itens: list<array{id: string, nome: string}>, valor: string, rotulo: string, todos: string}
     */
    public function filtroSelectCombo(): array
    {
        return $this->montarSelectCombo(
            $this->filtroValorOptions(),
            (string) $this->filtroValor,
            '<todos>',
        );
    }

    /**
     * Dados do combo compacto de plataforma.
     *
     * @return array{itens: list<array{id: string, nome: string}>, valor: string, rotulo: string, todos: string}
     */
    public function plataformaSelectCombo(): array
    {
        return $this->montarSelectCombo(
            $this->plataformaOptions(),
            (string) $this->plataformaFilter,
            '<todas>',
        );
    }

    /**
     * @param  array<int|string, string>  $options
     * @return array{itens: list<array{id: string, nome: string}>, valor: string, rotulo: string, todos: string}
     */
    private function montarSelectCombo(array $options, string $valorAtual, string $todosLabel): array
    {
        $itens = [];

        foreach ($options as $id => $nome) {
            $itens[] = [
                'id' => (string) $id,
                'nome' => (string) $nome,
            ];
        }

        $valor = $valorAtual === '' ? 'todos' : $valorAtual;
        $rotulo = $valor === 'todos'
            ? $todosLabel
            : (string) ($options[$valor] ?? $valor);

        return [
            'itens' => $itens,
            'valor' => $valor,
            'rotulo' => $rotulo,
            'todos' => $todosLabel,
        ];
    }

    /**
     * Opções do filtro dedicado de plataforma/canal de venda.
     *
     * @return array<string, string>
     */
    public function plataformaOptions(): array
    {
        return [
            'mobile' => 'Vendas Mobile',
            'vi' => 'Vendas Internas',
            'fv' => 'Força de Vendas',
            'meli' => 'Mercado Livre',
            'ifood' => 'Ifood',
            'ecommerce' => 'Ecommerce',
        ];
    }

    /**
     * Atualização automática silenciosa (sem notificação).
     */
    public function pollRefresh(): void
    {
        $this->resetTable();
    }

    // ---- Seleção em lote ---------------------------------------------------

    /**
     * Marca/desmarca a flag da linha e acompanha o destaque do pedido.
     * skipRender: não remonta a EmbeddedTable; o painel filho atualiza detalhe/barra.
     */
    public function alternarSelecionado(int|string $recordId): void
    {
        $key = (string) $recordId;
        $index = array_search($key, $this->selecionados, true);

        if ($index !== false) {
            unset($this->selecionados[$index]);
            $this->selecionados = array_values($this->selecionados);

            if ((int) $this->highlightedRecordId === (int) $recordId) {
                $this->highlightedRecordId = null;
            }

            $this->pushSelecaoToDetalhePanel();
            $this->skipRender();

            return;
        }

        $this->selecionados[] = $key;
        $this->highlightedRecordId = (int) $recordId;
        $this->pushSelecaoToDetalhePanel();
        $this->skipRender();
    }

    /**
     * Compat: antes fazia full render (~1 MB). Agora só sincroniza o painel filho.
     * Mantido para rollback / chamadas legadas; o checkbox não usa mais debounce.
     */
    public function syncSelecaoUi(): void
    {
        $this->pushSelecaoToDetalhePanel();
        $this->skipRender();
    }

    /**
     * Notifica o Livewire filho (detalhe + barra) sem remontar a tabela do pai.
     */
    protected function pushSelecaoToDetalhePanel(): void
    {
        $this->dispatch(
            'fv-monitor-selecao',
            selecionados: $this->selecionados,
            highlightedRecordId: $this->highlightedRecordId,
            nfeLoteProgressOpen: $this->nfeLoteProgressOpen,
            faturarProgressOpen: $this->faturarProgressOpen,
            cancelarProgressOpen: $this->cancelarProgressOpen,
            reabrirProgressOpen: $this->reabrirProgressOpen,
            clonarProgressOpen: $this->clonarProgressOpen,
        )->to(ForcaVendasMonitorDetalhePanel::class);
    }

    /**
     * Imprime os pedidos marcados em um único relatório A4 (vários blocos por folha).
     * Prefere DAV/orçamento; se faturado sem DAV, usa a venda.
     */
    public function imprimirSelecionados(): void
    {
        $orders = $this->pedidosSelecionados();

        if ($orders->isEmpty()) {
            $this->avisa('Selecione ao menos um pedido para imprimir.', 'warning');

            return;
        }

        $ids = [];
        $semDocumento = 0;

        foreach ($orders as $order) {
            $pedidoId = (int) ($order->pedido_id ?? 0);
            $vendaId = (int) ($order->venda_id ?? 0);

            if ($pedidoId <= 0 && $vendaId <= 0) {
                $semDocumento++;

                continue;
            }

            $ids[] = (int) $order->id;
        }

        if ($ids === []) {
            $this->avisa('Nenhum dos pedidos selecionados possui DAV/orçamento ou venda para imprimir.', 'warning');

            return;
        }

        $url = route('erp.reports.monitor-pedidos', [
            'ids' => implode(',', $ids),
            'auto' => 1,
        ]);

        $this->js('window.location.assign('.Js::from($url).');');

        if ($semDocumento > 0) {
            $this->avisa(
                'Abrindo impressão de '.count($ids).' pedido(s). Sem documento: '.$semDocumento.'.',
                'warning',
            );
        }
    }

    /**
     * Estado do botão Emitir NF-e / Lote conforme a seleção.
     *
     * @return array{enabled: bool, label: string, title: string, mode: string, venda_ids: list<int>}
     */
    // ---- Ações -------------------------------------------------------------

    /**
     * Fatura em lote os pedidos pendentes selecionados: para cada um gera a
     * Venda de retaguarda, dá baixa no estoque e cria as contas a receber.
     */
    public function faturarSelecionados(): void
    {
        $orders = $this->pedidosSelecionados();

        if ($orders->isEmpty()) {
            $this->avisa('Selecione ao menos um pedido para faturar.', 'warning');

            return;
        }

        $candidatos = [];

        foreach ($orders as $order) {
            if ($order->situacao === ForcaVendasOrder::SITUACAO_FATURADO || $order->venda_id) {
                continue;
            }

            if ($order->situacao === ForcaVendasOrder::SITUACAO_FINANCEIRO) {
                continue;
            }

            if ($order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO || ! $order->pedido) {
                continue;
            }

            $candidatos[] = $order;
        }

        if ($candidatos === []) {
            $this->avisa('Nenhum pedido pendente selecionado para faturar.', 'warning');

            return;
        }

        foreach ($candidatos as $order) {
            if (! $this->pedidoTemFormaPagamento($order)) {
                $this->avisa(
                    'DAV '.($order->pedido?->numero ?? $order->id).': sem forma de pagamento. Não é possível faturar.',
                    'warning',
                );

                return;
            }
        }

        if ($this->faturarProgressOpen) {
            return;
        }

        $this->faturarFila = [];

        foreach ($candidatos as $order) {
            $this->faturarFila[] = [
                'id' => (int) $order->getKey(),
                'numero' => $this->numeroPedidoFaturamento($order),
            ];
        }

        $this->faturarIgnorados = $orders->count() - count($candidatos);
        $this->faturarFilaIndex = 0;
        $this->faturarResultados = [];
        $this->faturarAtual = 0;
        $this->faturarTotal = count($this->faturarFila);
        $this->faturarProgressStatus = 'Preparando faturamento...';
        $this->faturarProgressOpen = true;
        $this->pushSelecaoToDetalhePanel();
        $this->js(<<<'JS'
            queueMicrotask(() => {
                if (window.__erpFvMonFaturarRun) {
                    window.__erpFvMonFaturarRun($wire);
                }
            });
        JS);
    }

    /**
     * @return array{done: bool, atual: int, total: int, numero: string}
     */
    public function prepararProximoFaturamento(): array
    {
        if (! $this->faturarProgressOpen || $this->faturarFila === []) {
            return ['done' => true, 'atual' => 0, 'total' => 0, 'numero' => ''];
        }

        if ($this->faturarFilaIndex >= count($this->faturarFila)) {
            return [
                'done' => true,
                'atual' => $this->faturarTotal,
                'total' => $this->faturarTotal,
                'numero' => '',
            ];
        }

        $item = $this->faturarFila[$this->faturarFilaIndex];
        $this->faturarAtual = $this->faturarFilaIndex + 1;
        $this->faturarProgressStatus = 'Faturando pedido '.$item['numero'].' ('.$this->faturarAtual.' de '.$this->faturarTotal.')';

        return [
            'done' => false,
            'atual' => $this->faturarAtual,
            'total' => $this->faturarTotal,
            'numero' => $item['numero'],
        ];
    }

    /**
     * @return array{ok: bool, done: bool, atual: int, total: int, numero: string, etapa: string, erro: ?string}
     */
    public function processarProximoFaturamento(): array
    {
        $vazio = [
            'ok' => false,
            'done' => true,
            'atual' => $this->faturarTotal,
            'total' => $this->faturarTotal,
            'numero' => '',
            'etapa' => '',
            'erro' => null,
        ];

        if (! $this->faturarProgressOpen || $this->faturarFilaIndex >= count($this->faturarFila)) {
            return $vazio;
        }

        $item = $this->faturarFila[$this->faturarFilaIndex];
        $numero = (string) $item['numero'];
        $etapa = 'Validando pedido';
        $ok = false;
        $erro = null;

        $order = ForcaVendasOrder::query()->with('pedido')->find((int) $item['id']);

        if (! $order instanceof ForcaVendasOrder || $order->pedido === null) {
            $erro = 'Pedido não encontrado.';
        } else {
            try {
                $serv = new ForcaVendasFaturamentoService();
                DB::transaction(function () use ($serv, $order, &$etapa): void {
                    $serv->faturar($order, $order->pedido, function (string $nome) use (&$etapa): void {
                        $etapa = $nome;
                    });
                });
                $ok = true;
                $etapa = 'Pedido '.$numero.' faturado';
            } catch (\Throwable $e) {
                $erro = $e->getMessage();
            }
        }

        $this->faturarResultados[] = [
            'id' => (string) $item['id'],
            'ok' => $ok,
            'numero' => $numero,
            'etapa' => $etapa,
            'erro' => $erro,
        ];
        $this->faturarFilaIndex++;
        $this->faturarAtual = min($this->faturarFilaIndex, $this->faturarTotal);

        if (! $ok) {
            $this->faturarProgressStatus = 'Pedido '.$numero.' — falha em '.$etapa.($erro ? ': '.$erro : '');
        }

        return [
            'ok' => $ok,
            'done' => $this->faturarFilaIndex >= count($this->faturarFila),
            'atual' => $this->faturarAtual,
            'total' => $this->faturarTotal,
            'numero' => $numero,
            'etapa' => $etapa,
            'erro' => $erro,
        ];
    }

    public function concluirFaturamentoMonitor(): void
    {
        $faturadosIds = [];
        $erros = 0;
        $ultimoErro = '';

        foreach ($this->faturarResultados as $resultado) {
            if ($resultado['ok']) {
                $faturadosIds[] = (string) $resultado['id'];

                continue;
            }

            $erros++;
            $ultimoErro = trim(($resultado['etapa'] ?? '').($resultado['erro'] ? ': '.$resultado['erro'] : ''));
        }

        $faturados = count($faturadosIds);
        $ignorados = $this->faturarIgnorados;

        if ($faturados > 0) {
            $this->selecionados = $erros === 0
                ? []
                : array_values(array_filter(
                    $this->selecionados,
                    fn (mixed $id): bool => ! in_array((string) $id, $faturadosIds, true),
                ));

            if ($this->highlightedRecordId !== null
                && ($erros === 0 || in_array((string) $this->highlightedRecordId, $faturadosIds, true))) {
                $this->highlightedRecordId = null;
            }

            $this->js(
                'requestAnimationFrame(() => {'
                .'const todos = '.Js::from($erros === 0).';'
                .'const ids = '.Js::from($faturadosIds).';'
                .'document.querySelectorAll(".erp-fv-monitor-page .erp-fv-mon__check").forEach((el) => {'
                .'if (!todos && !ids.includes(String(el.value))) return;'
                .'el.checked = false;'
                .'el.removeAttribute("checked");'
                .'el.closest(".fi-ta-row")?.classList.remove("erp-row-selected");'
                .'});'
                .'});'
            );
        }

        $this->faturarProgressOpen = false;
        $this->faturarFila = [];
        $this->faturarFilaIndex = 0;
        $this->faturarResultados = [];
        $this->faturarAtual = 0;
        $this->faturarTotal = 0;
        $this->faturarProgressStatus = '';

        $msg = "Faturados: {$faturados}."
            . ($ignorados > 0 ? " Ignorados: {$ignorados}." : '')
            . ($erros > 0 ? " Com erro: {$erros}." : '')
            . ($erros > 0 && $ultimoErro !== '' ? ' '.$ultimoErro : '');

        $this->avisa($msg, $erros > 0 ? 'warning' : 'success');
        $this->pushSelecaoToDetalhePanel();
    }

    private function numeroPedidoFaturamento(ForcaVendasOrder $order): string
    {
        $dav = preg_replace('/\D/', '', (string) ($order->pedido?->numero ?? ''));
        $dav = ltrim((string) $dav, '0');

        if ($dav !== '') {
            return $dav;
        }

        return (string) $order->getKey();
    }

    /**
     * Abre o modal de motivo para cancelar os pedidos faturados selecionados.
     */
    public function pedirCancelarSelecionados(): void
    {
        if ($this->cancelarProgressOpen) {
            return;
        }

        $orders = $this->pedidosSelecionados();

        if ($orders->isEmpty()) {
            $this->avisa('Selecione ao menos um pedido para cancelar.', 'warning');

            return;
        }

        $empresa = ErpContext::currentEmpresa();
        $this->cancelarPedidosQuantidade = $orders->count();
        $this->cancelarPedidosMotivo = ($empresa && (bool) $empresa->param_fiscal_motivo_estorno_automatico)
            ? PdvEstornoMotivo::MOTIVO_AUTOMATICO
            : '';
        $this->cancelarPedidosModalOpen = true;
    }

    public function closeCancelarPedidosModal(): void
    {
        $this->cancelarPedidosModalOpen = false;
        $this->cancelarPedidosMotivo = '';
        $this->cancelarPedidosQuantidade = 0;
    }

    /**
     * Monta a fila e abre o progresso. Cada pedido é cancelado numa chamada seguinte.
     */
    public function confirmCancelarSelecionados(): void
    {
        if ($this->cancelarProgressOpen || ! $this->cancelarPedidosModalOpen) {
            return;
        }

        $motivo = PdvEstornoMotivo::normalize($this->cancelarPedidosMotivo);
        $erroMotivo = PdvEstornoMotivo::validate($motivo, PdvEstornoMotivo::MIN_LENGTH_MONITOR_FV);

        if ($erroMotivo !== null) {
            $this->avisa($erroMotivo, 'warning');

            return;
        }

        $orders = $this->pedidosSelecionados();

        if ($orders->isEmpty()) {
            $this->closeCancelarPedidosModal();
            $this->avisa('Selecione ao menos um pedido para cancelar.', 'warning');

            return;
        }

        $this->cancelarMotivoAplicado = $motivo;
        $this->cancelarFila = [];

        foreach ($orders as $order) {
            $this->cancelarFila[] = [
                'id' => (int) $order->getKey(),
                'numero' => $this->numeroPedidoFaturamento($order),
                'pendente' => ! $order->venda_id,
            ];
        }

        $this->cancelarFilaIndex = 0;
        $this->cancelarResultados = [];
        $this->cancelarBoletoAutorizadoId = null;
        $this->cancelarAtual = 0;
        $this->cancelarTotal = count($this->cancelarFila);
        $this->cancelarProgressStatus = 'Preparando cancelamento...';
        $this->cancelarProgressOpen = true;
        $this->closeCancelarPedidosModal();
        $this->pushSelecaoToDetalhePanel();
        $this->js(<<<'JS'
            queueMicrotask(() => {
                if (window.__erpFvMonCancelarRun) {
                    window.__erpFvMonCancelarRun($wire);
                }
            });
        JS);
    }

    /**
     * @return array{done: bool, atual: int, total: int, numero: string, pendente: bool}
     */
    public function prepararProximoCancelamento(): array
    {
        if (! $this->cancelarProgressOpen || $this->cancelarFilaIndex >= count($this->cancelarFila)) {
            return [
                'done' => true,
                'atual' => $this->cancelarTotal,
                'total' => $this->cancelarTotal,
                'numero' => '',
                'pendente' => false,
            ];
        }

        $item = $this->cancelarFila[$this->cancelarFilaIndex];
        $this->cancelarAtual = $this->cancelarFilaIndex + 1;
        $this->cancelarProgressStatus = 'Cancelando pedido '.$item['numero'].' ('.$this->cancelarAtual.' de '.$this->cancelarTotal.')';

        return [
            'done' => false,
            'atual' => $this->cancelarAtual,
            'total' => $this->cancelarTotal,
            'numero' => $item['numero'],
            'pendente' => (bool) $item['pendente'],
        ];
    }

    /**
     * @return array{ok: bool, done: bool, aguardandoBoleto: bool, atual: int, total: int, numero: string, etapa: string, erro: ?string, mensagem: string}
     */
    public function processarProximoCancelamento(): array
    {
        $vazio = $this->respostaCancelamento(false, true, false, '', '', null, '');

        if (! $this->cancelarProgressOpen || $this->cancelarFilaIndex >= count($this->cancelarFila)) {
            return $vazio;
        }

        $item = $this->cancelarFila[$this->cancelarFilaIndex];
        $numero = (string) $item['numero'];
        $etapa = 'Validando pedido';
        $order = ForcaVendasOrder::query()->with('pedido')->find((int) $item['id']);

        if (! $order instanceof ForcaVendasOrder) {
            return $this->registrarResultadoCancelamento($item, false, $etapa, 'Pedido não encontrado.');
        }

        try {
            (new ForcaVendasMonitorCancelamentoService())->cancelar(
                $order,
                $this->cancelarMotivoAplicado,
                $numero,
                (int) $this->cancelarBoletoAutorizadoId === (int) $order->getKey(),
                function (string $nome) use (&$etapa): void {
                    $etapa = $nome;
                },
            );
        } catch (ForcaVendasCancelamentoBoletoPendenteException $e) {
            $this->cancelarProgressStatus = $e->getMessage();

            return $this->respostaCancelamento(
                false,
                false,
                true,
                $numero,
                'Verificando boleto bancário',
                null,
                $e->getMessage(),
            );
        } catch (\Throwable $e) {
            return $this->registrarResultadoCancelamento($item, false, $etapa, $e->getMessage());
        }

        $this->cancelarBoletoAutorizadoId = null;

        return $this->registrarResultadoCancelamento($item, true, 'Finalizando pedido', null);
    }

    public function autorizarBaixaBoletoCancelamento(): void
    {
        if (! $this->cancelarProgressOpen || $this->cancelarFilaIndex >= count($this->cancelarFila)) {
            return;
        }

        $this->cancelarBoletoAutorizadoId = (int) $this->cancelarFila[$this->cancelarFilaIndex]['id'];
    }

    /**
     * @return array{ok: bool, done: bool, aguardandoBoleto: bool, atual: int, total: int, numero: string, etapa: string, erro: ?string, mensagem: string}
     */
    public function recusarBaixaBoletoCancelamento(): array
    {
        if (! $this->cancelarProgressOpen || $this->cancelarFilaIndex >= count($this->cancelarFila)) {
            return $this->respostaCancelamento(false, true, false, '', '', null, '');
        }

        $item = $this->cancelarFila[$this->cancelarFilaIndex];

        return $this->registrarResultadoCancelamento(
            $item,
            false,
            'Verificando boleto bancário',
            'Baixa do boleto não confirmada.',
        );
    }

    public function concluirCancelamentoMonitor(): void
    {
        $canceladosIds = [];
        $falhas = [];

        foreach ($this->cancelarResultados as $resultado) {
            if ($resultado['ok']) {
                $canceladosIds[] = (string) $resultado['id'];

                continue;
            }

            $falhas[] = 'Pedido '.$resultado['numero'].' — '
                .trim((string) ($resultado['etapa'] ?? ''))
                .($resultado['erro'] ? ': '.$resultado['erro'] : '');
        }

        $cancelados = count($canceladosIds);

        if ($cancelados > 0) {
            $this->selecionados = $falhas === []
                ? []
                : array_values(array_filter(
                    $this->selecionados,
                    fn (mixed $id): bool => ! in_array((string) $id, $canceladosIds, true),
                ));

            if ($this->highlightedRecordId !== null
                && ($falhas === [] || in_array((string) $this->highlightedRecordId, $canceladosIds, true))) {
                $this->highlightedRecordId = null;
            }

            $this->js(
                'requestAnimationFrame(() => {'
                .'const todos = '.Js::from($falhas === []).';'
                .'const ids = '.Js::from($canceladosIds).';'
                .'document.querySelectorAll(".erp-fv-monitor-page .erp-fv-mon__check").forEach((el) => {'
                .'if (!todos && !ids.includes(String(el.value))) return;'
                .'el.checked = false;'
                .'el.removeAttribute("checked");'
                .'el.closest(".fi-ta-row")?.classList.remove("erp-row-selected");'
                .'});'
                .'});'
            );
        }

        $this->cancelarProgressOpen = false;
        $this->cancelarFila = [];
        $this->cancelarFilaIndex = 0;
        $this->cancelarResultados = [];
        $this->cancelarBoletoAutorizadoId = null;
        $this->cancelarAtual = 0;
        $this->cancelarTotal = 0;
        $this->cancelarProgressStatus = '';
        $this->cancelarMotivoAplicado = '';

        $msg = "Cancelados: {$cancelados}.";

        if ($falhas !== []) {
            $msg .= ' '.implode(' ', $falhas);
        }

        $this->avisa($msg, $falhas === [] ? 'success' : 'warning');
        $this->pushSelecaoToDetalhePanel();
    }

    /**
     * @param  array{id: int, numero: string, pendente: bool}  $item
     * @return array{ok: bool, done: bool, aguardandoBoleto: bool, atual: int, total: int, numero: string, etapa: string, erro: ?string, mensagem: string}
     */
    private function registrarResultadoCancelamento(array $item, bool $ok, string $etapa, ?string $erro): array
    {
        $numero = (string) $item['numero'];
        $this->cancelarResultados[] = [
            'id' => (string) $item['id'],
            'ok' => $ok,
            'numero' => $numero,
            'etapa' => $etapa,
            'erro' => $erro,
        ];
        $this->cancelarBoletoAutorizadoId = null;
        $this->cancelarFilaIndex++;
        $this->cancelarAtual = min($this->cancelarFilaIndex, $this->cancelarTotal);

        if (! $ok) {
            $this->cancelarProgressStatus = 'Pedido '.$numero.' — falha em '.$etapa.($erro ? ': '.$erro : '');
        }

        return $this->respostaCancelamento(
            $ok,
            $this->cancelarFilaIndex >= count($this->cancelarFila),
            false,
            $numero,
            $etapa,
            $erro,
            '',
        );
    }

    /**
     * @return array{ok: bool, done: bool, aguardandoBoleto: bool, atual: int, total: int, numero: string, etapa: string, erro: ?string, mensagem: string}
     */
    private function respostaCancelamento(
        bool $ok,
        bool $done,
        bool $aguardandoBoleto,
        string $numero,
        string $etapa,
        ?string $erro,
        string $mensagem,
    ): array {
        return [
            'ok' => $ok,
            'done' => $done,
            'aguardandoBoleto' => $aguardandoBoleto,
            'atual' => $this->cancelarAtual,
            'total' => $this->cancelarTotal,
            'numero' => $numero,
            'etapa' => $etapa,
            'erro' => $erro,
            'mensagem' => $mensagem,
        ];
    }

    /**
     * @deprecated Use pedirCancelarSelecionados / confirmCancelarSelecionados.
     */
    public function estornarSelecionados(): void
    {
        $this->pedirCancelarSelecionados();
    }

    /**
     * Reabre um pedido por vez. Faturado estorna como o Cancelar e volta a pendente.
     */
    public function reabrirPedido(): void
    {
        if ($this->reabrirProgressOpen) {
            return;
        }

        if (count($this->selecionados) > 1) {
            $this->avisa('Reabrir apenas um pedido por vez. Desmarque a seleção em lote.', 'warning');

            return;
        }

        $orderId = count($this->selecionados) === 1
            ? (int) $this->selecionados[0]
            : (int) ($this->highlightedRecordId ?? 0);

        if ($orderId <= 0) {
            $this->avisa('Selecione um pedido para reabrir.', 'warning');

            return;
        }

        $order = ForcaVendasOrder::query()->with('pedido')->find($orderId);

        if (! $order instanceof ForcaVendasOrder) {
            $this->avisa('Pedido não encontrado.', 'warning');

            return;
        }

        $this->reabrirOrderId = (int) $order->getKey();
        $this->reabrirNumero = $this->numeroPedidoFaturamento($order);
        $this->reabrirBoletoAutorizadoId = null;
        $this->reabrirOk = false;
        $this->reabrirSemVenda = false;
        $this->reabrirTemBoleto = false;
        $this->reabrirEtapa = '';
        $this->reabrirErro = '';
        $this->reabrirProgressStatus = 'Preparando reabertura...';
        $this->reabrirProgressOpen = true;
        $this->pushSelecaoToDetalhePanel();
        $this->js(<<<'JS'
            queueMicrotask(() => {
                if (window.__erpFvMonReabrirRun) {
                    window.__erpFvMonReabrirRun($wire);
                }
            });
        JS);
    }

    /**
     * @return array{ok: bool, aguardandoBoleto: bool, numero: string, etapa: string, erro: ?string, mensagem: string, semVenda: bool, temBoleto: bool}
     */
    public function processarReabertura(): array
    {
        $vazio = $this->respostaReabertura(false, false, '', '', null, '', false, false);

        if (! $this->reabrirProgressOpen || ! $this->reabrirOrderId) {
            return $vazio;
        }

        $numero = $this->reabrirNumero;
        $etapa = 'Validando pedido';
        $order = ForcaVendasOrder::query()->with('pedido')->find($this->reabrirOrderId);

        if (! $order instanceof ForcaVendasOrder) {
            return $this->guardarReabertura(false, $numero, $etapa, 'Pedido não encontrado.', false, false);
        }

        try {
            $info = (new ForcaVendasMonitorCancelamentoService())->reabrir(
                $order,
                $numero,
                (int) $this->reabrirBoletoAutorizadoId === (int) $order->getKey(),
                function (string $nome) use (&$etapa): void {
                    $etapa = $nome;
                },
            );
        } catch (ForcaVendasCancelamentoBoletoPendenteException $e) {
            $this->reabrirTemBoleto = true;
            $this->reabrirProgressStatus = $e->getMessage();

            return $this->respostaReabertura(
                false,
                true,
                $numero,
                'Verificando boleto bancário',
                null,
                $e->getMessage(),
                false,
                true,
            );
        } catch (\Throwable $e) {
            return $this->guardarReabertura(false, $numero, $etapa, $e->getMessage(), false, $this->reabrirTemBoleto);
        }

        $this->reabrirBoletoAutorizadoId = null;

        return $this->guardarReabertura(true, $numero, 'Reabrindo pedido', null, $info['sem_venda'], $info['tem_boleto']);
    }

    public function autorizarBaixaBoletoReabertura(): void
    {
        if (! $this->reabrirProgressOpen || ! $this->reabrirOrderId) {
            return;
        }

        $this->reabrirBoletoAutorizadoId = $this->reabrirOrderId;
    }

    /**
     * @return array{ok: bool, aguardandoBoleto: bool, numero: string, etapa: string, erro: ?string, mensagem: string, semVenda: bool, temBoleto: bool}
     */
    public function recusarBaixaBoletoReabertura(): array
    {
        return $this->guardarReabertura(
            false,
            $this->reabrirNumero,
            'Verificando boleto bancário',
            'Baixa do boleto não confirmada.',
            false,
            true,
        );
    }

    public function concluirReabertura(): void
    {
        $ok = $this->reabrirOk;
        $erro = trim($this->reabrirEtapa.($this->reabrirErro !== '' ? ': '.$this->reabrirErro : ''));
        $numero = $this->reabrirNumero;

        $this->reabrirProgressOpen = false;
        $this->reabrirOrderId = null;
        $this->reabrirNumero = '';
        $this->reabrirProgressStatus = '';
        $this->reabrirBoletoAutorizadoId = null;
        $this->reabrirOk = false;
        $this->reabrirSemVenda = false;
        $this->reabrirTemBoleto = false;
        $this->reabrirEtapa = '';
        $this->reabrirErro = '';

        if ($ok) {
            $this->avisa('Pedido '.$numero.' reaberto.', 'success');
        } else {
            $this->avisa($erro !== '' ? 'Pedido '.$numero.' — '.$erro : 'Não foi possível reabrir o pedido.', 'warning');
        }

        $this->pushSelecaoToDetalhePanel();
    }

    /**
     * @return array{ok: bool, aguardandoBoleto: bool, numero: string, etapa: string, erro: ?string, mensagem: string, semVenda: bool, temBoleto: bool}
     */
    private function guardarReabertura(
        bool $ok,
        string $numero,
        string $etapa,
        ?string $erro,
        bool $semVenda,
        bool $temBoleto,
    ): array {
        $this->reabrirOk = $ok;
        $this->reabrirSemVenda = $semVenda;
        $this->reabrirTemBoleto = $temBoleto;
        $this->reabrirEtapa = $etapa;
        $this->reabrirErro = (string) ($erro ?? '');

        if (! $ok) {
            $this->reabrirProgressStatus = 'Pedido '.$numero.' — falha em '.$etapa.($erro ? ': '.$erro : '');
        }

        return $this->respostaReabertura(ok: $ok, aguardandoBoleto: false, numero: $numero, etapa: $etapa, erro: $erro, mensagem: '', semVenda: $semVenda, temBoleto: $temBoleto);
    }

    /**
     * @return array{ok: bool, aguardandoBoleto: bool, numero: string, etapa: string, erro: ?string, mensagem: string, semVenda: bool, temBoleto: bool}
     */
    private function respostaReabertura(
        bool $ok,
        bool $aguardandoBoleto,
        string $numero,
        string $etapa,
        ?string $erro,
        string $mensagem,
        bool $semVenda,
        bool $temBoleto,
    ): array {
        return [
            'ok' => $ok,
            'aguardandoBoleto' => $aguardandoBoleto,
            'numero' => $numero,
            'etapa' => $etapa,
            'erro' => $erro,
            'mensagem' => $mensagem,
            'semVenda' => $semVenda,
            'temBoleto' => $temBoleto,
        ];
    }

    public function recebimento(): void
    {
        $order = $this->pedidoSelecionadoOuAvisa();

        if (! $order) {
            return;
        }

        if ($order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO) {
            $this->avisa('Pedido cancelado não pode gerar recebimento.', 'warning');

            return;
        }

        if (! $order->cliente_id) {
            $this->avisa('Pedido sem cliente para lançar a receber.', 'warning');

            return;
        }

        $documento = $this->documentoReceber($order);

        $jaExiste = ContaReceber::query()
            ->where(fn (Builder $q) => $q
                ->where('documento', $documento)
                ->orWhere('documento', 'like', $documento . '/%'))
            ->exists();

        if ($jaExiste) {
            $this->avisa('Este pedido já possui título a receber.', 'info');

            return;
        }

        $forma = $this->formaContaReceber($order);

        try {
            $dias = $this->diasParcelas($order);
        } catch (\RuntimeException $e) {
            $this->avisa($e->getMessage(), 'warning');

            return;
        }

        $parcelas = count($dias);
        $total = round((float) $order->total, 2);

        // Distribui o total entre as parcelas, jogando o resto de centavos na última.
        $valorBase = floor(($total / $parcelas) * 100) / 100;

        foreach (array_values($dias) as $i => $dia) {
            $valorParcela = ($i === $parcelas - 1)
                ? round($total - ($valorBase * ($parcelas - 1)), 2)
                : $valorBase;

            ContaReceber::query()->create([
                'empresa_id' => $order->empresa_id ? (int) $order->empresa_id : null,
                'numero' => ContaReceber::nextNumero(),
                'emissao' => Carbon::today(),
                'historico' => 'PEDIDO APP FV'
                    . ($parcelas > 1 ? ' (' . ($i + 1) . '/' . $parcelas . ')' : ''),
                'documento' => $parcelas > 1 ? $documento . '/' . ($i + 1) : $documento,
                'cliente_id' => $order->cliente_id,
                'vencimento' => Carbon::today()->addDays(max(0, $dia)),
                'valor' => $valorParcela,
                'forma' => $forma,
                ...ContaReceberJurosCarteira::atributosParaCreate(
                    $forma,
                    $order->empresa_id ? (int) $order->empresa_id : null
                ),
            ]);
        }

        $order->update([
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
            'faturado_at' => now(),
        ]);

        $this->avisa(
            $parcelas > 1
                ? "Faturado: {$parcelas} parcelas a receber geradas."
                : 'Título a receber gerado.',
            'success'
        );
    }

    public function emitirNfeDoBotao(): void
    {
        $estado = $this->nfeEmitirEstado;

        if (! ($estado['enabled'] ?? false)) {
            Notification::make()
                ->title('Não é possível emitir NF-e.')
                ->body((string) ($estado['title'] ?? 'Selecione pedidos faturados com cliente apto.'))
                ->warning()
                ->send();

            return;
        }

        $vendaIds = array_values(array_map('intval', $estado['venda_ids'] ?? []));

        if ($vendaIds === []) {
            return;
        }

        $mode = (($estado['mode'] ?? '') === 'lote') ? 'lote' : 'single';
        $resolver = app(NfeMonitorEmitenteResolver::class);

        if ($resolver->flagAtivo(ErpContext::currentEmpresa())) {
            $this->abrirModalEmpresaEmitenteNfe($vendaIds, $mode);

            return;
        }

        if ($mode === 'lote') {
            $this->iniciarNfeLote($vendaIds);

            return;
        }

        $this->redirect(NfeResource::getUrl('index').'?venda_id='.$vendaIds[0]);
    }

    /**
     * Fatia 4B1: abre NF-e aberta existente (não cria outra).
     */
    public function abrirNfeAbertaDoBotao(): void
    {
        $estado = $this->nfeAbrirEstado;

        if (! ($estado['enabled'] ?? false) || ! ($estado['nfe_id'] ?? null)) {
            Notification::make()
                ->title('Não é possível abrir a NF-e.')
                ->body((string) ($estado['title'] ?? 'Selecione um pedido com NF-e aberta.'))
                ->warning()
                ->send();

            return;
        }

        $this->redirect(NfeResource::getUrl('index').'?nfe_id='.(int) $estado['nfe_id']);
    }

    /**
     * @param  list<int>  $vendaIds
     */
    public function iniciarNfeLote(array $vendaIds, ?int $empresaEmitenteId = null): void
    {
        $vendaIds = array_values(array_filter(array_map('intval', $vendaIds)));

        if (count($vendaIds) < 2) {
            Notification::make()->title('Selecione ao menos duas vendas para o lote.')->warning()->send();

            return;
        }

        $emitenteId = $empresaEmitenteId && $empresaEmitenteId > 0 ? (int) $empresaEmitenteId : null;

        if ($emitenteId !== null) {
            $resolver = app(NfeMonitorEmitenteResolver::class);
            if (! $resolver->emitentePermitida($emitenteId, ErpContext::currentEmpresa(), Auth::user())) {
                Notification::make()
                    ->title('Empresa emitente inválida.')
                    ->body('A emitente escolhida não está mais autorizada ou acessível. O lote não foi iniciado.')
                    ->warning()
                    ->send();
                $this->nfeLoteEmpresaEmitenteId = null;

                return;
            }
        }

        $this->nfeLoteEmpresaEmitenteId = $emitenteId;
        $this->nfeLoteFilaVendaIds = $vendaIds;
        $this->nfeLoteFilaIndex = 0;
        $this->nfeLoteTotal = count($vendaIds);
        $this->nfeLoteAtual = 0;
        $this->nfeLoteResultados = [];
        $this->nfeLoteResumoOpen = false;
        $this->nfeLoteProgressOpen = true;
        $this->nfeLoteProgressStep = 0;
        $this->nfeLoteProgressStatus = 'Preparando lote…';
        $this->pushSelecaoToDetalhePanel();
        $this->js(<<<'JS'
            queueMicrotask(() => {
                if (window.__erpFvMonNfeLoteRun) {
                    window.__erpFvMonNfeLoteRun($wire);
                }
            });
        JS);
    }

    /**
     * Atualiza UI para a próxima nota (sem transmitir ainda).
     *
     * @return array{done: bool, atual: int, total: int}
     */
    public function prepararProximoItemNfeLote(): array
    {
        if (! $this->nfeLoteProgressOpen || $this->nfeLoteFilaVendaIds === []) {
            return ['done' => true, 'atual' => 0, 'total' => 0];
        }

        if ($this->nfeLoteFilaIndex >= count($this->nfeLoteFilaVendaIds)) {
            $this->finalizarNfeLote();

            return [
                'done' => true,
                'atual' => $this->nfeLoteTotal,
                'total' => $this->nfeLoteTotal,
            ];
        }

        $this->nfeLoteAtual = $this->nfeLoteFilaIndex + 1;
        $this->nfeLoteProgressStep = 0;
        $this->nfeLoteProgressStatus = 'Nota '.$this->nfeLoteAtual.' de '.$this->nfeLoteTotal.' — Validando dados da NF-e…';

        return [
            'done' => false,
            'atual' => $this->nfeLoteAtual,
            'total' => $this->nfeLoteTotal,
        ];
    }

    /**
     * Transmite a nota já preparada na fila (index atual).
     *
     * @return array{done: bool, atual: int, total: int, ok: ?bool, erro: ?string}
     */
    public function processarProximoItemNfeLote(): array
    {
        if (! $this->nfeLoteProgressOpen || $this->nfeLoteFilaVendaIds === []) {
            return ['done' => true, 'atual' => 0, 'total' => 0, 'ok' => null, 'erro' => null];
        }

        if ($this->nfeLoteFilaIndex >= count($this->nfeLoteFilaVendaIds)) {
            $this->finalizarNfeLote();

            return [
                'done' => true,
                'atual' => $this->nfeLoteTotal,
                'total' => $this->nfeLoteTotal,
                'ok' => null,
                'erro' => null,
            ];
        }

        $vendaId = (int) $this->nfeLoteFilaVendaIds[$this->nfeLoteFilaIndex];
        $this->nfeLoteAtual = $this->nfeLoteFilaIndex + 1;

        $venda = Venda::query()
            ->with(['itens.product', 'cliente', 'vendedor', 'forcaVendasOrder.pedido'])
            ->find($vendaId);

        $davLabel = null;

        if (! $venda) {
            $resultado = [
                'ok' => false,
                'venda_id' => $vendaId,
                'nfe_id' => null,
                'numero' => null,
                'erro' => 'Venda #'.$vendaId.' não encontrada.',
            ];
        } else {
            $davLabel = (string) ($venda->forcaVendasOrder?->pedido?->numero
                ?? $venda->numero
                ?? $vendaId);

            $emitenteId = $this->nfeLoteEmpresaEmitenteId && $this->nfeLoteEmpresaEmitenteId > 0
                ? (int) $this->nfeLoteEmpresaEmitenteId
                : null;

            if ($emitenteId !== null) {
                $resolver = app(NfeMonitorEmitenteResolver::class);
                if (! $resolver->emitentePermitida($emitenteId, ErpContext::currentEmpresa(), Auth::user())) {
                    $resultado = [
                        'ok' => false,
                        'venda_id' => $vendaId,
                        'nfe_id' => null,
                        'numero' => null,
                        'protocolo' => null,
                        'erro' => 'Empresa emitente inválida ou sem acesso. A emissão deste pedido foi interrompida sem alterar a empresa da sessão.',
                    ];
                } else {
                    $resultado = app(NfeVendaLoteEmissionService::class)
                        ->criarETransmitir($venda, function (int $step, string $label): void {
                            $this->nfeLoteProgressStep = $step;
                            $this->nfeLoteProgressStatus = 'Nota '.$this->nfeLoteAtual.' de '.$this->nfeLoteTotal.' — '.$label.'…';
                        }, $emitenteId);
                }
            } else {
                $resultado = app(NfeVendaLoteEmissionService::class)
                    ->criarETransmitir($venda, function (int $step, string $label): void {
                        $this->nfeLoteProgressStep = $step;
                        $this->nfeLoteProgressStatus = 'Nota '.$this->nfeLoteAtual.' de '.$this->nfeLoteTotal.' — '.$label.'…';
                    });
            }
        }

        $nfeIdResultado = isset($resultado['nfe_id']) && (int) $resultado['nfe_id'] > 0
            ? (int) $resultado['nfe_id']
            : null;

        $this->nfeLoteResultados[] = [
            'ok' => (bool) ($resultado['ok'] ?? false),
            'venda_id' => (int) ($resultado['venda_id'] ?? $vendaId),
            'nfe_id' => $nfeIdResultado,
            'dav' => $davLabel,
            'numero' => $resultado['numero'] ?? null,
            'erro' => $resultado['erro'] ?? null,
        ];

        $this->nfeLoteFilaIndex++;

        $ok = (bool) ($resultado['ok'] ?? false);

        if ($this->nfeLoteFilaIndex >= count($this->nfeLoteFilaVendaIds)) {
            $this->finalizarNfeLote();

            return [
                'done' => true,
                'atual' => $this->nfeLoteTotal,
                'total' => $this->nfeLoteTotal,
                'ok' => $ok,
                'erro' => $resultado['erro'] ?? null,
            ];
        }

        return [
            'done' => false,
            'atual' => $this->nfeLoteAtual,
            'total' => $this->nfeLoteTotal,
            'ok' => $ok,
            'erro' => $resultado['erro'] ?? null,
        ];
    }

    protected function finalizarNfeLote(): void
    {
        $ok = collect($this->nfeLoteResultados)->where('ok', true)->count();
        $erro = collect($this->nfeLoteResultados)->where('ok', false)->count();

        $this->nfeLoteProgressOpen = false;
        $this->nfeLoteResumoOpen = true;
        $this->nfeLoteResumoTitulo = 'Lote de NF-e concluído';
        $this->nfeLoteResumoTexto = $ok.' autorizada(s), '.$erro.' com erro.';
        // Detalhes de erro + Abrir NF-e (Fatia 4B2) vêm de nfeLoteResultados no Blade.

        $this->selecionados = [];
        $this->pushSelecaoToDetalhePanel();
        $this->resetTable();
        unset($this->nfeEmitirEstado, $this->nfeAbrirEstado);
        $this->nfeLoteEmpresaEmitenteId = null;
    }

    /**
     * Fatia 4B2: link do resumo só se a NF-e ainda existir e estiver aberta.
     * Autorização real fica no deep link ?nfe_id= (Fatia 4B1).
     */
    public function nfeIdAbertaDoResumoLote(?int $nfeId): ?int
    {
        if (! $nfeId || $nfeId <= 0) {
            return null;
        }

        $exists = \App\Models\Nfe::query()
            ->whereKey($nfeId)
            ->where('status', \App\Models\Nfe::STATUS_ABERTA)
            ->exists();

        return $exists ? $nfeId : null;
    }

    public function fecharNfeLoteResumo(): void
    {
        $this->nfeLoteResumoOpen = false;
        $this->nfeLoteFilaVendaIds = [];
        $this->nfeLoteResultados = [];
        $this->nfeLoteFilaIndex = 0;
        $this->nfeLoteAtual = 0;
        $this->nfeLoteTotal = 0;
        $this->nfeLoteEmpresaEmitenteId = null;
    }

    /** @deprecated Use emitirNfeDoBotao */
    public function emitirNfeSelecionado(): void
    {
        $this->emitirNfeDoBotao();
    }

    public function telaVenda(): void
    {
        if ($this->clonarProgressOpen) {
            return;
        }

        if (count($this->selecionados) > 1) {
            $this->avisa('Abra apenas um pedido por vez na Tela de Venda. Desmarque a seleção em lote.', 'warning');

            return;
        }

        // Sem seleção: abre tela em branco (nova venda).
        if (! $this->highlightedRecordId && $this->selecionados === []) {
            $this->redirect(ForcaVendasTelaVendaPage::getUrl());

            return;
        }

        $recordId = $this->highlightedRecordId
            ?: (int) ($this->selecionados[count($this->selecionados) - 1] ?? 0);

        if ($recordId <= 0) {
            Notification::make()
                ->title('Selecione um pedido na lista (flag).')
                ->warning()
                ->send();

            return;
        }

        $order = ForcaVendasOrder::query()->with('pedido')->find($recordId);

        if (! $order) {
            $this->avisa('Pedido não encontrado.', 'warning');

            return;
        }

        if ($order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO) {
            if ($order->tipo !== ForcaVendasOrder::TIPO_PEDIDO) {
                $this->avisa('Somente pedidos podem ser abertos nesta tela.', 'warning');

                return;
            }

            $this->clonarPedidoOrderId = (int) $order->getKey();
            $this->clonarPedidoModalOpen = true;

            return;
        }

        try {
            app(ForcaVendasTelaVendaService::class)->assertEditavel($order);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não é possível editar este pedido.')
                ->body($e->getMessage())
                ->warning()
                ->send();

            return;
        }

        $this->redirect(ForcaVendasTelaVendaPage::getUrl(['pedido' => $order->id]));
    }

    public function confirmarClonarPedidoCancelado(): void
    {
        if ($this->clonarProgressOpen || ! $this->clonarPedidoModalOpen || ! $this->clonarPedidoOrderId) {
            return;
        }

        $this->clonarOk = false;
        $this->clonarNovoId = null;
        $this->clonarEtapa = '';
        $this->clonarErro = '';
        $this->clonarPedidoModalOpen = false;
        $this->clonarProgressOpen = true;
        $this->pushSelecaoToDetalhePanel();
        $this->js(<<<'JS'
            queueMicrotask(() => {
                if (window.__erpFvMonClonarRun) {
                    window.__erpFvMonClonarRun($wire);
                }
            });
        JS);
    }

    /**
     * @return array{ok: bool, etapa: string, erro: ?string, pedidoId: ?int}
     */
    public function processarClonagemPedido(): array
    {
        $vazio = ['ok' => false, 'etapa' => 'Validando pedido cancelado', 'erro' => 'Clonagem não iniciada.', 'pedidoId' => null];

        if (! $this->clonarProgressOpen || ! $this->clonarPedidoOrderId) {
            return $vazio;
        }

        $user = auth()->user();

        if (! $user instanceof \App\Models\User) {
            return $this->guardarClonagem(false, 'Validando pedido cancelado', 'Usuário não autenticado.', null);
        }

        $order = ForcaVendasOrder::query()->with('pedido.itens')->find($this->clonarPedidoOrderId);

        if (! $order instanceof ForcaVendasOrder) {
            return $this->guardarClonagem(false, 'Validando pedido cancelado', 'Pedido não encontrado.', null);
        }

        $etapa = 'Validando pedido cancelado';

        try {
            $novo = app(ForcaVendasTelaVendaService::class)->clonarPedidoCancelado(
                $order,
                $user,
                function (string $nome) use (&$etapa): void {
                    $etapa = $nome;
                },
            );
        } catch (\Throwable $e) {
            return $this->guardarClonagem(false, $etapa, $e->getMessage(), null);
        }

        if (! $novo->id) {
            return $this->guardarClonagem(false, $etapa, 'Não foi possível criar o pedido clonado.', null);
        }

        return $this->guardarClonagem(true, 'Abrindo Tela de Venda', null, (int) $novo->id);
    }

    public function concluirClonagemPedido(): void
    {
        $ok = $this->clonarOk;
        $novoId = $this->clonarNovoId;
        $erro = trim($this->clonarEtapa.($this->clonarErro !== '' ? ': '.$this->clonarErro : ''));

        $this->clonarProgressOpen = false;
        $this->clonarPedidoModalOpen = false;
        $this->clonarPedidoOrderId = null;
        $this->clonarOk = false;
        $this->clonarNovoId = null;
        $this->clonarEtapa = '';
        $this->clonarErro = '';
        $this->pushSelecaoToDetalhePanel();

        if ($ok && $novoId) {
            $this->redirect(ForcaVendasTelaVendaPage::getUrl(['pedido' => $novoId]));

            return;
        }

        $this->avisa($erro !== '' ? $erro : 'Não foi possível clonar o pedido.', 'warning');
    }

    /**
     * @return array{ok: bool, etapa: string, erro: ?string, pedidoId: ?int}
     */
    private function guardarClonagem(bool $ok, string $etapa, ?string $erro, ?int $pedidoId): array
    {
        $this->clonarOk = $ok;
        $this->clonarNovoId = $pedidoId;
        $this->clonarEtapa = $etapa;
        $this->clonarErro = (string) ($erro ?? '');

        return [
            'ok' => $ok,
            'etapa' => $etapa,
            'erro' => $erro,
            'pedidoId' => $pedidoId,
        ];
    }

    public function recusarClonarPedidoCancelado(): void
    {
        $this->clonarPedidoModalOpen = false;
        $this->clonarPedidoOrderId = null;
    }


    protected function pedidoSelecionadoOuAvisa(): ?ForcaVendasOrder
    {
        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return null;
        }

        $order = ForcaVendasOrder::query()->with('pedido')->find($recordId);

        if (! $order) {
            $this->avisa('Pedido não encontrado.', 'warning');

            return null;
        }

        return $order;
    }

    protected function avisa(string $titulo, string $tipo): void
    {
        $notification = Notification::make()->title($titulo);

        match ($tipo) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'danger' => $notification->danger(),
            default => $notification->info(),
        };

        $notification->send();
        $this->resetTable();
    }
}
