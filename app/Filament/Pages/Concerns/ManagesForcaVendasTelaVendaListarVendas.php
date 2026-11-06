<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Models\ForcaVendasOrder;
use App\Models\Product;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueNegativoPolicy;
use App\Support\Erp\EstoqueReservaService;
use App\Support\ForcaVendas\ForcaVendasTelaVendaService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Listar vendas do dia (somente Tela de Venda web): imprimir e copiar produtos.
 */
trait ManagesForcaVendasTelaVendaListarVendas
{
    public bool $fvListarVendasOpen = false;

    public string $fvListarVendasBusca = '';

    public string $fvListarVendasDataDe = '';

    public string $fvListarVendasDataAte = '';

    public int $fvListarVendasPage = 1;

    public bool $fvListarVendasHasMore = false;

    /** @var list<array{id: int, dav: string, data: string, hora: string, cliente: string, total: string, situacao: string, situacao_key: string}> */
    public array $fvListarVendasRows = [];

    public bool $fvListarCopiarConfirmOpen = false;

    public ?int $fvListarCopiarOrderId = null;

    public bool $fvListarImprimirOverlayOpen = false;

    public ?string $fvListarImprimirOverlayUrl = null;

    private const FV_LISTAR_VENDAS_PER_PAGE = 30;

    public function abrirListarVendas(): void
    {
        if ($this->etapa !== 'venda') {
            Notification::make()->title('Volte para a venda para listar as vendas do dia.')->warning()->send();

            return;
        }

        if ($this->fvListarVendasOpen
            || $this->fvImportarOrcamentoOpen
            || $this->descontoModalOpen
            || $this->excluirItemModalOpen
            || $this->fvSairConfirmOpen
        ) {
            return;
        }

        if (! $this->fvListarVendasPodeAcessar()) {
            return;
        }

        $hoje = ErpTimezone::today();
        $this->fvListarVendasBusca = '';
        $this->fvListarVendasDataDe = $hoje;
        $this->fvListarVendasDataAte = $hoje;
        $this->fvListarVendasPage = 1;
        $this->fvListarCopiarConfirmOpen = false;
        $this->fvListarCopiarOrderId = null;
        $this->carregarFvListarVendas(reset: true);
        $this->fvListarVendasOpen = true;
        $this->dispatch('erp-fv-focus-listar-vendas-busca');
    }

    public function fecharListarVendas(): void
    {
        $this->fvListarVendasOpen = false;
        $this->fvListarCopiarConfirmOpen = false;
        $this->fvListarCopiarOrderId = null;
        $this->fvListarImprimirOverlayOpen = false;
        $this->fvListarImprimirOverlayUrl = null;
        $this->fvListarVendasRows = [];
        $this->fvListarVendasBusca = '';
        $this->fvListarVendasDataDe = '';
        $this->fvListarVendasDataAte = '';
        $this->fvListarVendasPage = 1;
        $this->fvListarVendasHasMore = false;
    }

    public function updatedFvListarVendasBusca(): void
    {
        $this->recarregarFvListarVendasFiltro();
    }

    public function updatedFvListarVendasDataDe(): void
    {
        $this->normalizarFvListarVendasPeriodo();
        $this->recarregarFvListarVendasFiltro();
    }

    public function updatedFvListarVendasDataAte(): void
    {
        $this->normalizarFvListarVendasPeriodo();
        $this->recarregarFvListarVendasFiltro();
    }

    private function recarregarFvListarVendasFiltro(): void
    {
        if (! $this->fvListarVendasOpen) {
            return;
        }

        $this->fvListarVendasPage = 1;
        $this->carregarFvListarVendas(reset: true);
    }

    private function normalizarFvListarVendasPeriodo(): void
    {
        $hoje = ErpTimezone::today();
        $de = trim($this->fvListarVendasDataDe);
        $ate = trim($this->fvListarVendasDataAte);

        if ($de === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
            $de = $hoje;
        }
        if ($ate === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
            $ate = $hoje;
        }
        if ($de > $ate) {
            [$de, $ate] = [$ate, $de];
        }

        $this->fvListarVendasDataDe = $de;
        $this->fvListarVendasDataAte = $ate;
    }

    public function carregarMaisFvListarVendas(): void
    {
        if (! $this->fvListarVendasOpen || ! $this->fvListarVendasHasMore) {
            return;
        }

        $this->fvListarVendasPage++;
        $this->carregarFvListarVendas(reset: false);
    }

    public function imprimirFvListarVenda(int $orderId): void
    {
        $order = $this->fvListarVendaAutorizada($orderId);

        if ($order === null) {
            return;
        }

        $pedidoId = (int) ($order->pedido_id ?? 0);
        $vendaId = (int) ($order->venda_id ?? 0);

        if ($pedidoId <= 0 && $vendaId <= 0) {
            Notification::make()
                ->title('Esta venda não possui DAV/orçamento ou documento para imprimir.')
                ->warning()
                ->send();

            return;
        }

        $this->fvListarImprimirOverlayUrl = route('erp.reports.monitor-pedidos', [
            'ids' => (string) $order->id,
            'auto' => 1,
        ]);
        $this->fvListarImprimirOverlayOpen = true;
    }

    public function fecharFvListarImprimirOverlay(): void
    {
        $this->fvListarImprimirOverlayOpen = false;
        $this->fvListarImprimirOverlayUrl = null;
    }

    public function solicitarCopiarProdutosFvListarVenda(int $orderId): void
    {
        $order = $this->fvListarVendaAutorizada($orderId);

        if ($order === null) {
            return;
        }

        $this->fvListarCopiarOrderId = (int) $order->id;

        if ($this->itens !== []) {
            $this->fvListarCopiarConfirmOpen = true;
            $this->dispatch('erp-fv-focus-listar-copiar-confirm');

            return;
        }

        $this->executarCopiarProdutosFvListarVenda((int) $order->id);
    }

    public function cancelarCopiarProdutosFvListarVenda(): void
    {
        $this->fvListarCopiarConfirmOpen = false;
        $this->fvListarCopiarOrderId = null;
    }

    public function confirmarCopiarProdutosFvListarVenda(): void
    {
        $orderId = (int) ($this->fvListarCopiarOrderId ?? 0);
        $this->fvListarCopiarConfirmOpen = false;
        $this->fvListarCopiarOrderId = null;

        if ($orderId <= 0) {
            return;
        }

        $this->executarCopiarProdutosFvListarVenda($orderId);
    }

    private function executarCopiarProdutosFvListarVenda(int $orderId): void
    {
        $order = $this->fvListarVendaAutorizada($orderId);

        if ($order === null) {
            return;
        }

        $linhas = $this->extrairProdutosQtdFvListarVenda($order);

        if ($linhas === []) {
            Notification::make()
                ->title('A venda selecionada não possui produtos para copiar.')
                ->warning()
                ->send();

            return;
        }

        $caixaId = $this->caixaId;
        $caixaLabel = $this->caixaLabel;
        $estoqueId = $this->estoqueId;
        $estoqueLabel = $this->estoqueLabel;
        $vendedorId = $this->vendedorId;
        $vendedorLabel = $this->vendedorLabel;
        $tabelaPrecoId = $this->tabelaPrecoId;
        $tabelaPrecoLabel = $this->tabelaPrecoLabel;

        // Nova venda: sem vínculo com o DAV de origem.
        $this->pedidoId = null;
        $this->fvVendaGeracao++;
        $this->resetarVenda();

        $this->caixaId = $caixaId;
        $this->caixaLabel = $caixaLabel;
        $this->estoqueId = $estoqueId;
        $this->estoqueLabel = $estoqueLabel;
        $this->vendedorId = $vendedorId;
        $this->vendedorLabel = $vendedorLabel;
        $this->tabelaPrecoId = $tabelaPrecoId;
        $this->tabelaPrecoLabel = $tabelaPrecoLabel;

        if (! $this->iniciarContextoVendaNova()) {
            return;
        }

        $productIds = array_values(array_unique(array_map(
            static fn (array $l): int => (int) $l['product_id'],
            $linhas,
        )));

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('ativo', true)
            ->get()
            ->keyBy('id');

        $novos = [];
        $ignorados = 0;
        $semEstoque = 0;
        $jaNoPedido = [];

        foreach ($linhas as $linha) {
            $productId = (int) $linha['product_id'];
            $qtd = (float) $linha['quantidade'];
            $product = $products->get($productId);

            if (! $product instanceof Product || $qtd <= 0.0001) {
                $ignorados++;

                continue;
            }

            if (EstoqueNegativoPolicy::ativo() && ! $product->is_servico) {
                $ja = (float) ($jaNoPedido[$productId] ?? 0);
                $disponivel = app(EstoqueReservaService::class)->disponivel($product, null, $this->estoqueId);

                if ($disponivel < ($qtd + $ja)) {
                    $semEstoque++;

                    continue;
                }
            }

            $preco = round($this->precoProdutoNaTabela($product, $qtd), 2);
            $jaNoPedido[$productId] = (float) ($jaNoPedido[$productId] ?? 0) + $qtd;

            $novos[] = [
                'key' => uniqid('i', true),
                'product_id' => $productId,
                'product_grade_id' => null,
                'codigo' => (string) ($product->codigo ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'quantidade' => $qtd,
                'preco_unitario' => $preco,
                'preco_manual' => false,
                'price_table_id' => ($product->usa_tab_preco ?? false) ? $this->tabelaPrecoId : null,
                'tabela_nivel' => ($product->usa_tab_preco ?? false)
                    ? $this->nivelTabelaPrecoSelecionada()
                    : null,
                'acrescimo' => 0.0,
                'desconto' => 0.0,
                'total' => round($qtd * $preco, 2),
                'foto' => $product->fotoUrl(),
            ];
        }

        if ($novos === []) {
            Notification::make()
                ->title('Nenhum produto pôde ser copiado.')
                ->body($semEstoque > 0
                    ? 'Estoque insuficiente para os itens da venda selecionada.'
                    : 'Produtos indisponíveis ou inativos.')
                ->warning()
                ->send();

            return;
        }

        // Mesma ordem visual da grade (mais recente no topo).
        $this->itens = array_values(array_reverse($novos));
        $this->itemSelecionado = 0;
        unset($this->totalBruto, $this->totalDescontosItens, $this->totalAcrescimosItens);

        $this->fecharListarVendas();
        $this->dispatch('fv-tela-venda-focus-barcode');

        $msg = count($novos).' produto(s) copiado(s) para a nova venda.';
        if ($ignorados > 0 || $semEstoque > 0) {
            $partes = [];
            if ($ignorados > 0) {
                $partes[] = $ignorados.' ignorado(s)';
            }
            if ($semEstoque > 0) {
                $partes[] = $semEstoque.' sem estoque';
            }
            $msg .= ' ('.implode(', ', $partes).').';
        }

        Notification::make()
            ->title('Produtos copiados.')
            ->body($msg.' Conclua normalmente com F3/F4.')
            ->success()
            ->send();
    }

    /**
     * @return list<array{product_id: int, quantidade: float}>
     */
    private function extrairProdutosQtdFvListarVenda(ForcaVendasOrder $order): array
    {
        $order->loadMissing('pedido.itens');
        $agrupado = [];

        $pedidoItens = $order->pedido?->itens;
        if ($pedidoItens !== null && $pedidoItens->isNotEmpty()) {
            foreach ($pedidoItens as $item) {
                $productId = (int) ($item->product_id ?? 0);
                $qtd = (float) ($item->quantidade ?? 0);
                if ($productId <= 0 || $qtd <= 0.0001) {
                    continue;
                }
                $agrupado[$productId] = ($agrupado[$productId] ?? 0.0) + $qtd;
            }
        }

        if ($agrupado === []) {
            $payload = is_array($order->payload) ? $order->payload : [];
            $payloadItens = is_array($payload['itens'] ?? null) ? $payload['itens'] : [];
            foreach ($payloadItens as $raw) {
                if (! is_array($raw)) {
                    continue;
                }
                $productId = (int) ($raw['product_id'] ?? 0);
                $qtd = (float) ($raw['quantidade'] ?? 0);
                if ($productId <= 0 || $qtd <= 0.0001) {
                    continue;
                }
                $agrupado[$productId] = ($agrupado[$productId] ?? 0.0) + $qtd;
            }
        }

        $linhas = [];
        foreach ($agrupado as $productId => $qtd) {
            $linhas[] = [
                'product_id' => (int) $productId,
                'quantidade' => round((float) $qtd, 3),
            ];
        }

        return $linhas;
    }

    private function carregarFvListarVendas(bool $reset): void
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        if ($empresaId <= 0) {
            $this->fvListarVendasRows = [];
            $this->fvListarVendasHasMore = false;

            return;
        }

        $page = max(1, $this->fvListarVendasPage);
        $limit = self::FV_LISTAR_VENDAS_PER_PAGE;
        $offset = ($page - 1) * $limit;

        $rows = $this->fvListarVendasQuery($empresaId)
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $this->fvListarVendasHasMore = $rows->count() > $limit;
        if ($this->fvListarVendasHasMore) {
            $rows = $rows->take($limit);
        }

        $mapped = $rows->map(function (ForcaVendasOrder $order): array {
            $momento = $order->client_created_at ?? $order->received_at;
            $local = $momento ? ErpTimezone::toLocal($momento) : null;
            $data = $local ? $local->format('d/m/Y') : '—';
            $hora = $local ? $local->format('H:i') : '—';
            $dav = trim((string) ($order->pedido?->numero ?? ''));
            if ($dav === '') {
                $dav = (string) ($order->id ?? '');
            }
            $cliente = trim((string) ($order->cliente?->nome_razao
                ?? (is_array($order->payload) ? ($order->payload['cliente_nome'] ?? '') : '')
                ?? ''));
            if ($cliente === '') {
                $cliente = '—';
            }

            [$situacaoKey, $situacao] = $this->fvListarVendaSituacaoLabel((string) $order->situacao);

            return [
                'id' => (int) $order->id,
                'dav' => $dav,
                'data' => $data,
                'hora' => $hora,
                'cliente' => mb_strtoupper($cliente, 'UTF-8'),
                'total' => ErpMoney::formatBr((float) ($order->total ?? 0), 2),
                'situacao' => $situacao,
                'situacao_key' => $situacaoKey,
            ];
        })->values()->all();

        $this->fvListarVendasRows = $reset
            ? $mapped
            : array_values(array_merge($this->fvListarVendasRows, $mapped));
    }

    private function fvListarVendasQuery(int $empresaId): Builder
    {
        $this->normalizarFvListarVendasPeriodo();
        $inicio = Carbon::parse($this->fvListarVendasDataDe, ErpTimezone::DEFAULT)->startOfDay();
        $fim = Carbon::parse($this->fvListarVendasDataAte, ErpTimezone::DEFAULT)->endOfDay();
        $busca = trim($this->fvListarVendasBusca);

        $query = ForcaVendasOrder::query()
            ->with([
                'pedido:id,numero',
                'cliente:id,nome_razao',
            ])
            ->where('empresa_id', $empresaId)
            ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO)
            ->where(function (Builder $q): void {
                // Somente Tela de Venda web — nunca app FV / PDV / OS.
                $q->where('device_uuid', 'monitor-web')
                    ->orWhere('payload->origem', 'like', 'monitor_tela_venda%');
            })
            ->where(function (Builder $q) use ($inicio, $fim): void {
                $q->where(function (Builder $q2) use ($inicio, $fim): void {
                    $q2->where('client_created_at', '>=', $inicio)
                        ->where('client_created_at', '<=', $fim);
                })->orWhere(function (Builder $q2) use ($inicio, $fim): void {
                    $q2->whereNull('client_created_at')
                        ->where('received_at', '>=', $inicio)
                        ->where('received_at', '<=', $fim);
                });
            })
            ->orderByDesc('client_created_at')
            ->orderByDesc('id');

        if ($busca !== '') {
            $like = '%'.$busca.'%';
            $query->where(function (Builder $q) use ($busca, $like): void {
                $q->whereHas('pedido', function (Builder $pq) use ($busca, $like): void {
                    $pq->where('numero', 'like', $like);
                    if (ctype_digit($busca)) {
                        $pq->orWhere('numero', $busca);
                    }
                })
                    ->orWhereHas('cliente', function (Builder $cq) use ($like): void {
                        $cq->where('nome_razao', 'like', $like)
                            ->orWhere('nome_fantasia', 'like', $like)
                            ->orWhere('cpf_cnpj', 'like', $like);
                    })
                    ->orWhere('payload->cliente_nome', 'like', $like);

                if (ctype_digit($busca)) {
                    $q->orWhere('id', (int) $busca);
                }
            });
        }

        return $query;
    }

    private function fvListarVendaAutorizada(int $orderId): ?ForcaVendasOrder
    {
        if (! $this->fvListarVendasPodeAcessar()) {
            return null;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $order = ForcaVendasOrder::query()->find($orderId);

        if (! $order instanceof ForcaVendasOrder) {
            Notification::make()->title('Venda não encontrada.')->warning()->send();

            return null;
        }

        if ((int) $order->empresa_id !== $empresaId) {
            Notification::make()->title('Venda de outra empresa.')->warning()->send();

            return null;
        }

        if (! ForcaVendasMonitorResource::isTelaVenda($order)) {
            Notification::make()
                ->title('Somente vendas da Tela de Venda podem ser usadas aqui.')
                ->warning()
                ->send();

            return null;
        }

        try {
            app(ForcaVendasTelaVendaService::class)->assertOrderDaEmpresa($order, $empresaId);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->warning()->send();

            return null;
        }

        return $order;
    }

    private function fvListarVendasPodeAcessar(): bool
    {
        $user = Auth::user();

        if (! $user || ! ErpAccess::authorizeOrNotify($user, 'vendas.access')) {
            return false;
        }

        if ((int) (ErpContext::currentEmpresaId() ?? 0) <= 0) {
            Notification::make()->title('Nenhuma empresa selecionada.')->warning()->send();

            return false;
        }

        return true;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function fvListarVendaSituacaoLabel(string $situacao): array
    {
        return match ($situacao) {
            ForcaVendasOrder::SITUACAO_FATURADO => ['faturada', 'Faturada'],
            ForcaVendasOrder::SITUACAO_CANCELADO => ['cancelada', 'Cancelada'],
            default => ['pendente', 'Pendente'],
        };
    }
}
