<?php

namespace App\Filament\Resources\ForcaVendasMonitorResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Filament\Resources\OrcamentoResource;
use App\Models\ContaReceber;
use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use App\Models\Person;
use App\Models\Vendedor;
use App\Models\Venda;
use App\Support\Erp\ErpFormReturnUrl;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueReservaService;
use App\Support\ForcaVendas\ForcaVendasFaturamentoService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListForcaVendasMonitor extends ListRecords
{
    use InteractsWithErpListPage;

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
     * IDs dos pedidos marcados (check-boxes) para faturar/estornar em lote.
     *
     * @var array<int, string>
     */
    public array $selecionados = [];

    public bool $financeiroModalOpen = false;

    public ?int $financeiroOrderId = null;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Monitor de Vendas');

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
            'extraKeys' => [],
        ];
    }

    public function table(Table $table): Table
    {
        return ForcaVendasMonitorResource::table($table)
            ->recordUrl(null)
            ->recordAction('highlightRecord')
            ->recordClasses(function (Model $record): string {
                $situacao = $record->situacao ?? ForcaVendasOrder::SITUACAO_PENDENTE;
                $tint = 'erp-fv-mon--' . $situacao;

                $classes = [$tint];

                if ($this->highlightedRecordId === $record->getKey()) {
                    $classes[] = 'erp-row-selected';
                }

                if (in_array((string) $record->getKey(), $this->selecionados, true)) {
                    $classes[] = 'erp-fv-mon-row--checked';
                }

                return implode(' ', $classes);
            });
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->where('tipo', 'pedido')
            ->with(['user', 'orcamento', 'cliente']);

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

        // Período usa a data da venda no app (client_created_at), não a sincronização.
        if (filled($this->periodoDeApplied)) {
            $inicio = Carbon::parse($this->periodoDeApplied, ErpTimezone::DEFAULT)->startOfDay()->utc();
            $query->where(function (Builder $q) use ($inicio): void {
                $q->where('client_created_at', '>=', $inicio)
                    ->orWhere(fn (Builder $q2) => $q2
                        ->whereNull('client_created_at')
                        ->where('received_at', '>=', $inicio));
            });
        }

        if (filled($this->periodoAteApplied)) {
            $fim = Carbon::parse($this->periodoAteApplied, ErpTimezone::DEFAULT)->endOfDay()->utc();
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
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.origem')) != 'vendas_internas'");
            });

            return;
        }

        // Marketplaces ainda não geram pedidos no monitor.
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
                $query->whereHas('orcamento', fn (Builder $s) => $s->where('numero', 'like', '%' . $valor . '%'));
                break;

            case 'identificacao':
                $query->where('identificacao', 'like', '%' . $valor . '%');
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
                $query->whereHas('orcamento', fn (Builder $s) => $s->where('subtotal', '>=', $num));
                break;

            case 'desconto':
                $num = $this->parseNumeroFiltro($valor);
                $query->whereHas('orcamento', fn (Builder $s) => $s->where('desconto_valor', '>=', $num));
                break;

            case 'acrescimo':
                $num = $this->parseNumeroFiltro($valor);
                $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.frete')) AS DECIMAL(15,2)) >= ?", [$num]);
                break;
        }
    }

    /**
     * Limites UTC (início/fim) de um dia local informado como Y-m-d.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function intervaloDoDia(string $data): array
    {
        return [
            Carbon::parse($data, ErpTimezone::DEFAULT)->startOfDay()->utc(),
            Carbon::parse($data, ErpTimezone::DEFAULT)->endOfDay()->utc(),
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
    public function selecionado(): ?ForcaVendasOrder
    {
        if (! $this->highlightedRecordId) {
            return null;
        }

        return ForcaVendasOrder::query()
            ->with(['orcamento.itens.product', 'orcamento.itens.grade', 'cliente', 'user', 'vendedor'])
            ->find($this->highlightedRecordId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function itensSelecionado(): array
    {
        $order = $this->selecionado;
        $orcamento = $order?->orcamento;

        if (! $orcamento) {
            return [];
        }

        $vendedor = $order?->vendedor?->nome ?? $order?->user?->name ?? '—';

        return $orcamento->itens
            ->map(fn ($item): array => [
                'codigo' => $item->product?->codigo ?? '',
                'codigo_barras' => $item->product?->codigo_barras ?? '',
                'descricao' => $item->descricao
                    ?: ($item->product?->descricao ?? 'Item'),
                'quantidade' => (float) $item->quantidade,
                'preco_unitario' => (float) $item->preco_unitario,
                'desconto' => (float) $item->desconto,
                'total' => (float) $item->total,
                'vendedor' => $vendedor,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function pagamentosSelecionado(): array
    {
        $order = $this->selecionado;

        if (! $order) {
            return [];
        }

        $documento = $this->documentoReceber($order);

        $contas = ContaReceber::query()
            ->where(fn (Builder $q) => $q
                ->where('documento', $documento)
                ->orWhere('documento', 'like', $documento . '/%'))
            ->orderBy('vencimento')
            ->get();

        if ($contas->isNotEmpty()) {
            return $contas
                ->map(fn (ContaReceber $c, int $i): array => [
                    'meio' => ContaReceber::formaLabels()[$c->forma] ?? mb_strtoupper((string) $c->forma, 'UTF-8'),
                    'parcela' => $i + 1,
                    'vencimento' => optional($c->vencimento)->format('d/m/Y') ?? '—',
                    'valor' => (float) $c->valor,
                ])
                ->all();
        }

        // Pedido pendente: prévia das parcelas conforme enviado pelo app.
        return $this->pagamentosPrevistos($order);
    }

    /**
     * Prévia de parcelas antes do faturamento (forma, prazos e valores do payload).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function pagamentosPrevistos(ForcaVendasOrder $order): array
    {
        $total = round((float) ($order->orcamento?->total ?? $order->total), 2);

        if ($total <= 0) {
            return [];
        }

        $dias = $this->diasParcelas($order);
        $n = count($dias);
        $forma = trim((string) ($order->payload['forma_pagamento'] ?? ''));
        $meio = $forma !== ''
            ? $forma
            : (ContaReceber::formaLabels()[$this->formaContaReceber($order)] ?? '—');
        $hoje = ErpTimezone::toLocal()->startOfDay();
        $parcelaBase = floor($total / $n * 100) / 100;

        $linhas = [];

        foreach (array_values($dias) as $i => $dia) {
            $valor = $i === $n - 1
                ? round($total - ($parcelaBase * ($n - 1)), 2)
                : $parcelaBase;

            $linhas[] = [
                'meio' => $meio,
                'parcela' => $i + 1,
                'vencimento' => $hoje->copy()->addDays(max(0, (int) $dia))->format('d/m/Y'),
                'valor' => $valor,
            ];
        }

        return $linhas;
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
                View::make('filament.components.erp.forca-vendas.monitor-detail'),
                View::make('filament.components.erp.forca-vendas.monitor-action-bar'),
                View::make('filament.components.erp.forca-vendas.monitor-financeiro-modal'),
            ]);
    }

    #[Computed]
    public function financeiroPedido(): ?ForcaVendasOrder
    {
        if (! $this->financeiroOrderId) {
            return null;
        }

        return ForcaVendasOrder::query()
            ->with(['orcamento', 'cliente'])
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

    public function updatedPeriodoDe(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedPeriodoAte(): void
    {
        $this->periodoAteApplied = $this->periodoAte;
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSituacaoFilter(): void
    {
        $this->clearListSelection();
        $this->resetTable();
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
            'dav' => 'Nº DAV',
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
            'identificacao' => 'Identificação',
        ];
    }

    /**
     * Tipo de controle do valor para o campo atual: select | date | number | text.
     */
    public function filtroCampoTipo(): string
    {
        return match ($this->filtroCampo) {
            'cliente', 'vendedor', 'status' => 'select',
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
            default => [],
        };
    }

    /**
     * Opções do filtro dedicado de plataforma/canal de venda.
     *
     * @return array<string, string>
     */
    public function plataformaOptions(): array
    {
        return [
            'mobile' => 'Vendas Mobile (todos)',
            'vi' => 'Vendas Internas',
            'fv' => 'Força de Vendas',
            'meli' => 'Mercado Livre',
            'shopee' => 'Shopee',
            'magalu' => 'Magalu',
            'amazon' => 'Amazon Brasil',
            'ali' => 'AliExpress',
            'casasbahia' => 'Casas Bahia',
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
     * Marca todos os pedidos pendentes do filtro atual.
     */
    public function selecionarPendentes(): void
    {
        $this->selecionados = $this->getTableQuery()
            ->where('situacao', ForcaVendasOrder::SITUACAO_PENDENTE)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if ($this->selecionados === []) {
            $this->avisa('Nenhum pedido pendente no filtro atual.', 'info');
        }
    }

    public function limparSelecao(): void
    {
        $this->selecionados = [];
    }

    /**
     * @return \Illuminate\Support\Collection<int, ForcaVendasOrder>
     */
    protected function pedidosSelecionados(): \Illuminate\Support\Collection
    {
        $ids = collect($this->selecionados)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return ForcaVendasOrder::query()
            ->whereIn('id', $ids)
            ->with('orcamento')
            ->get();
    }

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

        $faturados = 0;
        $ignorados = 0;
        $erros = 0;
        $serv = new ForcaVendasFaturamentoService();

        foreach ($orders as $order) {
            if ($order->situacao === ForcaVendasOrder::SITUACAO_FATURADO || $order->venda_id) {
                $ignorados++;

                continue;
            }

            if ($order->situacao === ForcaVendasOrder::SITUACAO_FINANCEIRO) {
                $ignorados++;

                continue;
            }

            if ($order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO || ! $order->orcamento) {
                $ignorados++;

                continue;
            }

            try {
                DB::transaction(fn () => $serv->faturar($order, $order->orcamento));
                $faturados++;
            } catch (\Throwable $e) {
                $erros++;
            }
        }

        $this->selecionados = [];

        $msg = "Faturados: {$faturados}."
            . ($ignorados > 0 ? " Ignorados: {$ignorados}." : '')
            . ($erros > 0 ? " Com erro: {$erros}." : '');

        $this->avisa($msg, $erros > 0 ? 'warning' : 'success');
    }

    /**
     * Estorna em lote os pedidos faturados selecionados: devolve o estoque,
     * cancela a venda e apaga as contas a receber (pula os com título recebido).
     */
    public function estornarSelecionados(): void
    {
        $orders = $this->pedidosSelecionados();

        if ($orders->isEmpty()) {
            $this->avisa('Selecione ao menos um pedido para estornar.', 'warning');

            return;
        }

        $estornados = 0;
        $ignorados = 0;
        $bloqueados = 0;
        $serv = new ForcaVendasFaturamentoService();

        foreach ($orders as $order) {
            if (! $order->venda_id || $order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO) {
                $ignorados++;

                continue;
            }

            try {
                $serv->estornar($order);

                if ($order->orcamento && $order->orcamento->status !== Orcamento::STATUS_CANCELADO) {
                    $order->orcamento->update(['status' => Orcamento::STATUS_CANCELADO]);
                }

                $estornados++;
            } catch (\RuntimeException $e) {
                $bloqueados++;
            }
        }

        $this->selecionados = [];

        $msg = "Estornados: {$estornados}."
            . ($ignorados > 0 ? " Ignorados: {$ignorados}." : '')
            . ($bloqueados > 0 ? " Bloqueados (título recebido): {$bloqueados}." : '');

        $this->avisa($msg, $bloqueados > 0 ? 'warning' : 'success');
    }

    /**
     * Reabre um pedido estornado (volta para "Pendente"), permitindo faturar de novo.
     */
    public function reabrirPedido(): void
    {
        $order = $this->pedidoSelecionadoOuAvisa();

        if (! $order) {
            return;
        }

        $venda = $order->venda_id ? Venda::query()->find($order->venda_id) : null;

        if ($venda && $venda->status !== Venda::STATUS_CANCELADO) {
            $this->avisa('Pedido faturado. Estorne antes de reabrir.', 'warning');

            return;
        }

        try {
            DB::transaction(function () use ($order): void {
                $order->update([
                    'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
                    'venda_id' => null,
                    'confirmed_at' => null,
                    'faturado_at' => null,
                    'canceled_at' => null,
                ]);

                if ($order->orcamento && $order->orcamento->status === Orcamento::STATUS_CANCELADO) {
                    $order->orcamento->update(['status' => Orcamento::STATUS_ABERTO]);
                }

                $order->loadMissing('orcamento.itens.product', 'user');

                if ($order->tipo === ForcaVendasOrder::TIPO_PEDIDO
                    && $order->orcamento
                    && $order->user) {
                    (new EstoqueReservaService())->reservarPedido(
                        $order,
                        $order->orcamento,
                        $order->user,
                    );
                }
            });

            $this->avisa('Pedido reaberto (pendente).', 'success');
        } catch (\Throwable $e) {
            $this->avisa($e->getMessage(), 'warning');
        }
    }

    /**
     * Cancela pedido pendente e libera reservas de estoque.
     */
    public function cancelarPedidoPendente(): void
    {
        $order = $this->pedidoSelecionadoOuAvisa();

        if (! $order) {
            return;
        }

        try {
            (new ForcaVendasFaturamentoService())->cancelarPendente($order);
            $this->avisa('Pedido cancelado. Reserva de estoque liberada.', 'success');
        } catch (\Throwable $e) {
            $this->avisa($e->getMessage(), 'warning');
        }
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

        $numeroPedido = $order->orcamento?->numero ?? ('#' . $order->id);
        $forma = $this->formaContaReceber($order);
        $dias = $this->diasParcelas($order);
        $parcelas = count($dias);
        $total = round((float) $order->total, 2);

        // Distribui o total entre as parcelas, jogando o resto de centavos na última.
        $valorBase = floor(($total / $parcelas) * 100) / 100;

        foreach (array_values($dias) as $i => $dia) {
            $valorParcela = ($i === $parcelas - 1)
                ? round($total - ($valorBase * ($parcelas - 1)), 2)
                : $valorBase;

            ContaReceber::query()->create([
                'numero' => ContaReceber::nextNumero(),
                'emissao' => Carbon::today(),
                'historico' => 'PEDIDO APP ' . $numeroPedido
                    . ($parcelas > 1 ? ' (' . ($i + 1) . '/' . $parcelas . ')' : ''),
                'documento' => $parcelas > 1 ? $documento . '/' . ($i + 1) : $documento,
                'cliente_id' => $order->cliente_id,
                'vencimento' => Carbon::today()->addDays(max(0, $dia)),
                'valor' => $valorParcela,
                'forma' => $forma,
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

    /**
     * Dias de vencimento de cada parcela, a partir da tabela de prazo do pedido
     * (ex.: "30,60,90"). Sem tabela definida, gera uma única parcela à vista (hoje).
     *
     * @return array<int, int>
     */
    protected function diasParcelas(ForcaVendasOrder $order): array
    {
        $diasStr = (string) ($order->payload['tabela_prazo_dias'] ?? '');

        $dias = collect(explode(',', $diasStr))
            ->map(fn ($d): int => (int) trim((string) $d))
            ->filter(fn ($d): bool => $d >= 0)
            ->values()
            ->all();

        return $dias === [] ? [0] : $dias;
    }

    /**
     * Mapeia a forma de pagamento textual do app para a forma da conta a receber.
     */
    protected function formaContaReceber(ForcaVendasOrder $order): string
    {
        $forma = mb_strtolower((string) ($order->payload['forma_pagamento'] ?? ''), 'UTF-8');

        return match (true) {
            str_contains($forma, 'boleto') => ContaReceber::FORMA_BOLETO,
            str_contains($forma, 'cheque') => ContaReceber::FORMA_CHEQUE,
            str_contains($forma, 'cart') => ContaReceber::FORMA_CARTAO,
            default => ContaReceber::FORMA_CARTEIRA,
        };
    }

    public function telaVenda(): void
    {
        $monitorReturn = ForcaVendasMonitorResource::getUrl('index');

        if ($this->highlightedRecordId) {
            $order = ForcaVendasOrder::query()->find($this->highlightedRecordId);

            if ($order?->orcamento_id) {
                $this->redirect(ErpFormReturnUrl::appendToUrl(
                    OrcamentoResource::getUrl('edit', ['record' => $order->orcamento_id]),
                    $monitorReturn,
                ));

                return;
            }
        }

        $this->redirect(ErpFormReturnUrl::appendToUrl(
            OrcamentoResource::getUrl('create'),
            $monitorReturn,
        ));
    }

    protected function documentoReceber(ForcaVendasOrder $order): string
    {
        return 'FV-' . $order->id;
    }

    protected function pedidoSelecionadoOuAvisa(): ?ForcaVendasOrder
    {
        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return null;
        }

        $order = ForcaVendasOrder::query()->with('orcamento')->find($recordId);

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
