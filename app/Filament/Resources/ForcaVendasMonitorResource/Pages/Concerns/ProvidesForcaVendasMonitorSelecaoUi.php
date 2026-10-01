<?php

namespace App\Filament\Resources\ForcaVendasMonitorResource\Pages\Concerns;

use App\Models\ContaReceber;
use App\Models\ForcaVendasOrder;
use App\Models\Nfe;
use App\Models\PixCobranca;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfe\NfeVendaMercadoriaService;
use App\Support\ForcaVendas\ForcaVendasFaturamentoService;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;

/**
 * Leitura de seleção para o detalhe inferior e estado visual da barra.
 * Sem ações de faturamento / NF-e / NFC-e (essas ficam no List page).
 *
 * Requer no componente: $selecionados (array), $highlightedRecordId (?int).
 */
trait ProvidesForcaVendasMonitorSelecaoUi
{
    /** @var array<int, string>|null */
    private ?array $situacoesSelecaoBarraMemo = null;

    #[Computed]
    public function selecionado(): ?ForcaVendasOrder
    {
        if (! $this->highlightedRecordId) {
            return null;
        }

        return ForcaVendasOrder::query()
            ->with(['pedido.itens.product', 'pedido.itens.grade', 'cliente', 'user', 'vendedor', 'venda'])
            ->find($this->highlightedRecordId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function itensSelecionado(): array
    {
        $order = $this->selecionado;
        $pedido = $order?->pedido;

        if (! $pedido) {
            return [];
        }

        $vendedor = $order?->vendedor?->nome ?? $order?->user?->name ?? '—';
        $payloadItens = is_array($order?->payload['itens'] ?? null) ? $order->payload['itens'] : [];

        $payloadFila = [];
        foreach ($payloadItens as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $chave = $this->chaveItemPayloadPedido($raw);
            $payloadFila[$chave][] = $raw;
        }

        return $pedido->itens
            ->sortBy(fn ($item) => (int) ($item->item ?? 0))
            ->values()
            ->map(function ($item) use ($vendedor, &$payloadFila): array {
                $chave = $this->chaveItemPayloadPedido([
                    'product_id' => $item->product_id,
                    'product_grade_id' => $item->product_grade_id,
                ]);
                $payloadItem = [];
                if (! empty($payloadFila[$chave])) {
                    $payloadItem = array_shift($payloadFila[$chave]) ?? [];
                }

                $qtd = (float) $item->quantidade;
                $acr = (float) ($payloadItem['acrescimo'] ?? 0);
                $precoPedido = (float) $item->preco_unitario;
                $precoPayload = isset($payloadItem['preco_unitario'])
                    ? (float) $payloadItem['preco_unitario']
                    : null;
                $desc = (float) $item->desconto;

                if ($acr > 0.0001 && $qtd > 0) {
                    if ($precoPayload !== null && abs($precoPayload - $precoPedido) > 0.005) {
                        $preco = $precoPayload;
                    } else {
                        $preco = round($precoPedido - ($acr / $qtd), 2);
                    }
                } else {
                    $preco = $precoPedido;
                }

                return [
                    'product_id' => (int) ($item->product_id ?? 0),
                    'codigo' => $item->product?->codigo ?? '',
                    'codigo_barras' => $item->product?->codigo_barras ?? '',
                    'descricao' => $item->descricao
                        ?: ($item->product?->descricao ?? 'Item'),
                    'quantidade' => $qtd,
                    'preco_unitario' => $preco,
                    'desconto' => $desc,
                    'acrescimo' => $acr,
                    'total' => round(($qtd * $preco) + $acr - $desc, 2),
                    'vendedor' => $vendedor,
                ];
            })
            ->all();
    }

    #[Computed]
    public function exibirCustoProdutoGrade(): bool
    {
        return (bool) (ErpContext::currentEmpresa()?->param_monitor_vendas_exibir_custo_produto ?? false);
    }

    /**
     * Custos unitários em lote para a grade de itens (só com parâmetro ligado).
     *
     * @return array<int, float>
     */
    #[Computed]
    public function custosUnitariosGrade(): array
    {
        if (! $this->exibirCustoProdutoGrade) {
            return [];
        }

        $itens = $this->itensSelecionado;
        if ($itens === []) {
            return [];
        }

        $ids = array_map(
            static fn (array $i): int => (int) ($i['product_id'] ?? 0),
            $itens,
        );

        return ForcaVendasMargemVendaCalculator::custosUnitariosPorProduto(
            $ids,
            ErpContext::currentEmpresaId(),
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function chaveItemPayloadPedido(array $item): string
    {
        $productId = (int) ($item['product_id'] ?? 0);
        $gradeId = filled($item['product_grade_id'] ?? null)
            ? (int) $item['product_grade_id']
            : 0;

        return $productId.':'.$gradeId;
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
                ->orWhere('documento', 'like', $documento.'/%'))
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

        return $this->pagamentosPrevistos($order);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function pagamentosPrevistos(ForcaVendasOrder $order): array
    {
        $total = round((float) ($order->pedido?->total ?? $order->total), 2);

        if ($total <= 0) {
            return [];
        }

        try {
            $dias = $this->diasParcelas($order);
        } catch (\RuntimeException $e) {
            return [];
        }

        if ($dias === []) {
            return [];
        }
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
            ->with(['pedido', 'venda.cliente', 'venda.itens'])
            ->get();
    }

    /**
     * @return array{enabled: bool, label: string, title: string, mode: string, venda_ids: list<int>}
     */
    #[Computed]
    public function nfeEmitirEstado(): array
    {
        $disabled = [
            'enabled' => false,
            'label' => 'Emitir NF-e',
            'title' => 'Selecione pedido(s) faturado(s) com cliente apto à NF-e.',
            'mode' => 'disabled',
            'venda_ids' => [],
        ];

        if (! ErpAccess::currentCan('nfe.access') || ! ErpAccess::currentCan('nfe.emit')) {
            $disabled['title'] = 'Sem permissão para emitir NF-e.';

            return $disabled;
        }

        $orders = $this->pedidosSelecionados();

        if ($orders->isEmpty() && $this->highlightedRecordId) {
            $orders = ForcaVendasOrder::query()
                ->whereKey((int) $this->highlightedRecordId)
                ->with(['venda.cliente', 'venda.itens', 'pedido'])
                ->get();
        }

        if ($orders->isEmpty()) {
            return $disabled;
        }

        $serv = app(NfeVendaMercadoriaService::class);
        $vendaIds = [];
        $motivos = [];

        foreach ($orders as $order) {
            if ($order->situacao !== ForcaVendasOrder::SITUACAO_FATURADO || ! $order->venda_id) {
                $motivos[] = 'DAV '.($order->pedido?->numero ?? $order->id).': não faturado.';

                continue;
            }

            $venda = $order->venda;

            if (! $venda) {
                $motivos[] = 'DAV '.($order->pedido?->numero ?? $order->id).': venda não encontrada.';

                continue;
            }

            $motivo = $serv->motivoInaptoParaNfe($venda);

            if ($motivo !== null) {
                $motivos[] = 'DAV '.($order->pedido?->numero ?? $order->id).': '.$motivo;

                continue;
            }

            $vendaIds[] = (int) $venda->id;
        }

        if ($motivos !== [] || $vendaIds === []) {
            return [
                'enabled' => false,
                'label' => count($orders) > 1 ? 'Emitir NF-e Lote' : 'Emitir NF-e',
                'title' => $motivos[0] ?? $disabled['title'],
                'mode' => 'disabled',
                'venda_ids' => [],
            ];
        }

        $lote = count($vendaIds) > 1;

        return [
            'enabled' => true,
            'label' => $lote ? 'Emitir NF-e Lote' : 'Emitir NF-e',
            'title' => $lote
                ? 'Transmitir '.count($vendaIds).' NF-e automaticamente (permanece no Monitor).'
                : 'Abrir emissão de NF-e da venda selecionada.',
            'mode' => $lote ? 'lote' : 'single',
            'venda_ids' => $vendaIds,
        ];
    }

    /**
     * Fatia 4B1: reabrir NF-e aberta existente (não cria nova).
     *
     * @return array{enabled: bool, label: string, title: string, nfe_id: int|null}
     */
    #[Computed]
    public function nfeAbrirEstado(): array
    {
        $disabled = [
            'enabled' => false,
            'label' => 'Abrir NF-e',
            'title' => 'Selecione um pedido com NF-e aberta para reabrir.',
            'nfe_id' => null,
        ];

        if (! ErpAccess::currentCan('nfe.access')) {
            $disabled['title'] = 'Sem permissão para acessar NF-e.';

            return $disabled;
        }

        $orders = $this->pedidosSelecionados();

        if ($orders->isEmpty() && $this->highlightedRecordId) {
            $orders = ForcaVendasOrder::query()
                ->whereKey((int) $this->highlightedRecordId)
                ->with(['venda'])
                ->get();
        }

        if ($orders->count() !== 1) {
            if ($orders->count() > 1) {
                $disabled['title'] = 'Selecione apenas um pedido para abrir a NF-e.';
            }

            return $disabled;
        }

        $order = $orders->first();

        if ($order?->situacao === ForcaVendasOrder::SITUACAO_CANCELADO) {
            $disabled['title'] = 'Pedido cancelado.';

            return $disabled;
        }

        $vendaId = (int) ($order?->venda_id ?? 0);

        if ($vendaId <= 0) {
            return $disabled;
        }

        $nfe = Nfe::query()
            ->where('venda_id', $vendaId)
            ->where('status', Nfe::STATUS_ABERTA)
            ->orderByDesc('id')
            ->first(['id', 'numero', 'empresa_id']);

        if (! $nfe) {
            return $disabled;
        }

        $num = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;

        return [
            'enabled' => true,
            'label' => 'Abrir NF-e',
            'title' => 'Reabrir NF-e '.$num.' (aberta) para revisar ou retransmitir.',
            'nfe_id' => (int) $nfe->id,
        ];
    }

    /**
     * @return array{enabled: bool, title: string}
     */
    #[Computed]
    public function faturarEstado(): array
    {
        $disabled = [
            'enabled' => false,
            'title' => 'Selecione pedido(s) pendente(s) com forma de pagamento.',
        ];

        if (property_exists($this, 'faturarProgressOpen') && $this->faturarProgressOpen) {
            return [
                'enabled' => false,
                'title' => 'Faturamento em andamento.',
            ];
        }

        $orders = $this->pedidosSelecionados();

        if ($orders->isEmpty()) {
            return $disabled;
        }

        $faturaveis = [];
        $semForma = [];

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

            $faturaveis[] = $order;

            if (! $this->pedidoTemFormaPagamento($order)) {
                $semForma[] = $order;
            }
        }

        if ($faturaveis === []) {
            return $disabled;
        }

        if ($semForma !== []) {
            $primeiro = $semForma[0];

            return [
                'enabled' => false,
                'title' => 'DAV '.($primeiro->pedido?->numero ?? $primeiro->id).': sem forma de pagamento.',
            ];
        }

        $n = count($faturaveis);

        return [
            'enabled' => true,
            'title' => $n > 1
                ? 'Faturar '.$n.' pedidos selecionados.'
                : 'Faturar pedido selecionado.',
        ];
    }

    /**
     * @return array{enabled: bool, title: string}
     */
    #[Computed]
    public function reabrirEstado(): array
    {
        if (property_exists($this, 'reabrirProgressOpen') && $this->reabrirProgressOpen) {
            return [
                'enabled' => false,
                'title' => 'Reabertura em andamento.',
            ];
        }

        $nMarcados = count($this->selecionados);

        if ($nMarcados > 1) {
            return [
                'enabled' => false,
                'title' => 'Reabrir apenas um pedido por vez. Desmarque a seleção em lote.',
            ];
        }

        if ($nMarcados === 0 && ! $this->highlightedRecordId) {
            return [
                'enabled' => false,
                'title' => 'Selecione um pedido para reabrir.',
            ];
        }

        if ($this->selecaoSomentePendentes()) {
            return [
                'enabled' => false,
                'title' => 'Pedido pendente não pode ser reaberto.',
            ];
        }

        return [
            'enabled' => true,
            'title' => 'Reabrir o pedido selecionado (volta para Pendente).',
        ];
    }

    /**
     * @return array{enabled: bool, title: string}
     */
    #[Computed]
    public function telaVendaEstado(): array
    {
        if (property_exists($this, 'clonarProgressOpen') && $this->clonarProgressOpen) {
            return [
                'enabled' => false,
                'title' => 'Clonagem em andamento.',
            ];
        }

        if (count($this->selecionados) > 1) {
            return [
                'enabled' => false,
                'title' => 'Abra apenas um pedido por vez na Tela de Venda. Desmarque a seleção em lote.',
            ];
        }

        if (count($this->selecionados) === 1 || $this->highlightedRecordId) {
            $situacao = array_values($this->situacoesDaSelecao())[0] ?? null;

            if ($situacao === ForcaVendasOrder::SITUACAO_FATURADO) {
                return [
                    'enabled' => false,
                    'title' => 'Pedido faturado não abre na Tela de Venda.',
                ];
            }

            return [
                'enabled' => true,
                'title' => 'Abrir o pedido selecionado na Tela de Venda.',
            ];
        }

        return [
            'enabled' => true,
            'title' => 'Abrir Tela de Venda em branco (nova venda).',
        ];
    }

    /**
     * @return array{enabled: bool, title: string}
     */
    #[Computed]
    public function enviarEstado(): array
    {
        $nMarcados = count($this->selecionados);

        if ($nMarcados > 1) {
            return [
                'enabled' => false,
                'title' => 'Envie apenas um pedido por vez. Desmarque a seleção em lote.',
            ];
        }

        if ($nMarcados === 0 && ! $this->highlightedRecordId) {
            return [
                'enabled' => false,
                'title' => 'Selecione um pedido na lista para enviar.',
            ];
        }

        if ($this->selecaoSomenteCancelados()) {
            return [
                'enabled' => false,
                'title' => 'Pedido cancelado não pode ser enviado.',
            ];
        }

        if ($this->selecaoSomentePendentes()) {
            return [
                'enabled' => false,
                'title' => 'Pedido pendente não pode ser enviado.',
            ];
        }

        return [
            'enabled' => true,
            'title' => 'Enviar DAV e documentos por e-mail ou WhatsApp.',
        ];
    }

    /**
     * @return array{enabled: bool, title: string}
     */
    #[Computed]
    public function imprimirEstado(): array
    {
        if ($this->situacoesDaSelecao() === []) {
            return [
                'enabled' => false,
                'title' => 'Selecione um pedido para imprimir.',
            ];
        }

        if ($this->selecaoSomentePendentes()) {
            $n = count($this->situacoesDaSelecao());

            return [
                'enabled' => false,
                'title' => $n > 1
                    ? 'Pedidos pendentes não podem ser impressos.'
                    : 'Pedido pendente não pode ser impresso.',
            ];
        }

        if ($this->selecaoSomenteCancelados()) {
            $n = count($this->situacoesDaSelecao());

            return [
                'enabled' => false,
                'title' => $n > 1
                    ? 'Pedidos cancelados não podem ser impressos.'
                    : 'Pedido cancelado não pode ser impresso.',
            ];
        }

        return [
            'enabled' => true,
            'title' => 'Imprimir DAV dos pedidos marcados',
        ];
    }

    /**
     * @return array{enabled: bool, title: string}
     */
    #[Computed]
    public function cancelarEstado(): array
    {
        if (property_exists($this, 'cancelarProgressOpen') && $this->cancelarProgressOpen) {
            return [
                'enabled' => false,
                'title' => 'Cancelamento em andamento.',
            ];
        }

        if ($this->situacoesDaSelecao() === []) {
            return [
                'enabled' => false,
                'title' => 'Selecione um pedido para cancelar.',
            ];
        }

        if ($this->selecaoSomenteCancelados()) {
            $n = count($this->situacoesDaSelecao());

            return [
                'enabled' => false,
                'title' => $n > 1
                    ? 'Os pedidos selecionados já estão cancelados.'
                    : 'O pedido selecionado já está cancelado.',
            ];
        }

        return [
            'enabled' => true,
            'title' => 'Cancelar pedidos selecionados',
        ];
    }

    /**
     * Situação dos pedidos da seleção (flags; se nenhuma, o destaque).
     *
     * @return array<int, string>
     */
    protected function situacoesDaSelecao(): array
    {
        if ($this->situacoesSelecaoBarraMemo !== null) {
            return $this->situacoesSelecaoBarraMemo;
        }

        $ids = collect($this->selecionados)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty() && $this->highlightedRecordId) {
            $ids = collect([(int) $this->highlightedRecordId]);
        }

        if ($ids->isEmpty()) {
            return $this->situacoesSelecaoBarraMemo = [];
        }

        return $this->situacoesSelecaoBarraMemo = ForcaVendasOrder::query()
            ->whereIn('id', $ids->all())
            ->pluck('situacao', 'id')
            ->all();
    }

    protected function esquecerSituacoesSelecaoBarra(): void
    {
        $this->situacoesSelecaoBarraMemo = null;
    }

    protected function selecaoSomentePendentes(): bool
    {
        $situacoes = $this->situacoesDaSelecao();

        if ($situacoes === []) {
            return false;
        }

        foreach ($situacoes as $situacao) {
            if ($situacao !== ForcaVendasOrder::SITUACAO_PENDENTE) {
                return false;
            }
        }

        return true;
    }

    protected function selecaoSomenteCancelados(): bool
    {
        $situacoes = $this->situacoesDaSelecao();

        if ($situacoes === []) {
            return false;
        }

        foreach ($situacoes as $situacao) {
            if ($situacao !== ForcaVendasOrder::SITUACAO_CANCELADO) {
                return false;
            }
        }

        return true;
    }

    protected function pedidoTemFormaPagamento(ForcaVendasOrder $order): bool
    {
        $payload = is_array($order->payload) ? $order->payload : [];

        if ((int) ($payload['forma_pagamento_id'] ?? 0) > 0) {
            return true;
        }

        if (trim((string) ($payload['forma_pagamento'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($order->pedido?->forma_pagamento ?? '')) !== '') {
            return true;
        }

        $uuid = trim((string) ($order->uuid ?? ''));

        if ($uuid === '') {
            return false;
        }

        return PixCobranca::query()
            ->where('order_uuid', $uuid)
            ->where('origem', PixCobranca::ORIGEM_PEDIDO)
            ->where('status', PixCobranca::STATUS_PAGO)
            ->exists();
    }

    /**
     * @return array<int, int>
     */
    protected function diasParcelas(ForcaVendasOrder $order): array
    {
        $clienteId = (int) ($order->cliente_id ?? $order->pedido?->cliente_id ?? 0);

        return app(ForcaVendasFaturamentoService::class)
            ->resolverParcelasDiasDoPedido($order, $clienteId > 0 ? $clienteId : null);
    }

    protected function formaContaReceber(ForcaVendasOrder $order): string
    {
        $forma = mb_strtolower((string) ($order->payload['forma_pagamento'] ?? ''), 'UTF-8');

        return match (true) {
            str_contains($forma, 'boleto') => ContaReceber::FORMA_BOLETO,
            str_contains($forma, 'cheque') => ContaReceber::FORMA_CHEQUE,
            str_contains($forma, 'cart') || str_contains($forma, 'pos') || str_contains($forma, 'tef') => ContaReceber::FORMA_CARTAO,
            default => ContaReceber::FORMA_CARTEIRA,
        };
    }

    protected function documentoReceber(ForcaVendasOrder $order): string
    {
        return 'FV-'.$order->id;
    }
}
