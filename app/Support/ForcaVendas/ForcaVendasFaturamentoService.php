<?php

namespace App\Support\ForcaVendas;

use App\Models\Boleto;
use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\ContaReceber;
use App\Models\Entrega;
use App\Models\EstoqueMovimentacao;
use App\Models\ForcaVendasOrder;
use App\Models\FormaPagamento;
use App\Models\Pedido;
use App\Models\Person;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Models\PixCobranca;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Vendedor;
use App\Support\Erp\ConsumidorFinalGuard;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\Financeiro\ComissaoPeriodoService;
use App\Support\Erp\Financeiro\ContaReceberBaixaService;
use App\Support\Erp\Financeiro\ContaReceberJurosCarteira;
use App\Support\Erp\Financeiro\FormaPagamentoDestino;
use App\Support\Erp\Financeiro\VendaCaixaDestinoResolver;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use App\Support\Erp\Pdv\PdvStockService;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Support\Logistica\LogisticaVendaHookService;
use App\Support\VendasInternas\VendasInternasMonitorHookService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fatura um pedido vindo do app Força de Vendas:
 * gera a Venda de retaguarda, dá baixa no estoque e aplica o financeiro
 * conforme `tipo_movimento` da forma de pagamento (caixa, contas a receber,
 * depósito, crédito cliente, troca, nenhum). Também faz o estorno.
 *
 * As contas a receber usam o documento "FV-{orderId}" (e "FV-{orderId}/{n}"
 * quando há mais de uma parcela), mesma convenção da tela Monitor de Vendas.
 *
 * Caixa (Tela/FV/Monitor): destino via VendaCaixaDestinoResolver
 * (forma → seleção autorizada → Permissões → CAIXA GERAL).
 * PDV autorizado com sessão aberta → só `pdv_caixa_movimentos` (Livro no fechamento).
 * Demais destinos → só `caixa_lancamentos`. Formas que não movimentam caixa: sem lançamento.
 *
 * Observação: com "Bloquear Estoque Negativo" ativo, a baixa respeita o saldo
 * (ProductEstoqueSaldoService / PdvStockService). Com o parâmetro desligado,
 * o faturamento pode deixar estoque negativo.
 */
class ForcaVendasFaturamentoService
{
    /**
     * Cria a Venda + baixa de estoque + contas a receber a partir do pedido
     * já importado. Deve rodar dentro de uma transação (a do push já garante isso).
     *
     * @return array{venda: Venda, contas_receber: list<ContaReceber>}
     */
    public function faturar(ForcaVendasOrder $order, Pedido $pedido, ?callable $aoAvancar = null): array
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn (): array => $this->faturar($order, $pedido, $aoAvancar));
        }

        $avancar = static function (string $etapa) use ($aoAvancar): void {
            if ($aoAvancar !== null) {
                $aoAvancar($etapa);
            }
        };

        $avancar('Validando pedido');

        $order = ForcaVendasOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

        if (! $order instanceof ForcaVendasOrder) {
            throw new \RuntimeException('Pedido não encontrado.');
        }

        if ($order->situacao === ForcaVendasOrder::SITUACAO_FATURADO) {
            throw new \RuntimeException('Pedido já foi faturado.');
        }

        if ($order->situacao === ForcaVendasOrder::SITUACAO_FINANCEIRO) {
            throw new \RuntimeException('Pedido aguarda liberação financeira antes de faturar.');
        }

        if ($order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO) {
            throw new \RuntimeException('Pedido cancelado não pode ser faturado.');
        }

        if (! in_array((string) $order->situacao, [
            ForcaVendasOrder::SITUACAO_PENDENTE,
            ForcaVendasOrder::SITUACAO_CONFIRMADO,
        ], true)) {
            throw new \RuntimeException('Pedido não pode ser faturado.');
        }

        $vendaReaberta = $this->resolverVendaReabertaParaRefaturar($order);

        $pedido = $order->pedido;

        if (! $pedido instanceof Pedido) {
            throw new \RuntimeException('Pedido sem DAV para faturar.');
        }

        $pedido->loadMissing('itens');

        $abertura = ErpTimezone::toLocal($order->dataAberturaAt());
        $fechamento = ErpTimezone::toLocal(now());

        $vendedor = $order->vendedor_id
            ? Vendedor::query()->find($order->vendedor_id)
            : null;

        // Cobrança Pix paga deste pedido (verdade do servidor, casada pelo uuid).
        $pixPago = PixCobranca::query()
            ->where('order_uuid', $order->uuid)
            ->where('origem', PixCobranca::ORIGEM_PEDIDO)
            ->where('status', PixCobranca::STATUS_PAGO)
            ->latest('id')
            ->first();

        if ($pixPago === null && ! $this->pedidoTemFormaPagamento($order, $pedido)) {
            throw new \RuntimeException('Pedido sem forma de pagamento.');
        }

        if ($pixPago === null) {
            $payloadGuard = is_array($order->payload) ? $order->payload : [];
            ConsumidorFinalGuard::assertPagamentoPermitido(
                Person::query()->find((int) $pedido->cliente_id),
                $this->resolveFormaPagamento($payloadGuard),
            );
        }

        $empresaId = $order->empresa_id
            ? (int) $order->empresa_id
            : ErpContext::currentEmpresaId();

        if ($empresaId && ! $order->empresa_id) {
            $order->forceFill(['empresa_id' => $empresaId])->save();
        }

        $estoqueId = $this->resolveEstoqueId($empresaId, $vendedor);
        $isTelaErp = $this->isTelaVendaErp($order);
        $formaPagamento = $pixPago ? 'PIX' : ($order->payload['forma_pagamento'] ?? null);

        if ($vendaReaberta instanceof Venda) {
            return $this->refaturarVendaExistente(
                $order,
                $pedido,
                $vendaReaberta,
                $pixPago,
                $vendedor,
                $empresaId,
                $estoqueId,
                $abertura,
                $fechamento,
                $formaPagamento,
                $isTelaErp,
                $avancar,
            );
        }

        $avancar('Gerando venda');

        $venda = Venda::query()->create([
            'empresa_id' => $empresaId,
            'numero' => Venda::nextNumero(),
            'data' => $abertura->toDateString(),
            'hora' => $fechamento->format('H:i:s'),
            'hora_abertura' => $order->dataAberturaAt() !== null
                ? $abertura->format('H:i:s')
                : null,
            'cliente_id' => $pedido->cliente_id,
            'vendedor_id' => $vendedor?->id,
            'vendedor_nome' => $vendedor?->nome,
            'total' => $pedido->total,
            'forma_pagamento' => $formaPagamento,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => $isTelaErp ? Venda::PLATAFORMA_ERP : Venda::PLATAFORMA_MOBILE,
        ]);

        return $this->aplicarEfeitosFaturamento(
            $order,
            $pedido,
            $venda,
            $pixPago,
            $empresaId,
            $estoqueId,
            $avancar,
        );
    }

    /**
     * Venda reaberta (status aberto) do mesmo order — refaturar sem novo número.
     */
    private function resolverVendaReabertaParaRefaturar(ForcaVendasOrder $order): ?Venda
    {
        if (! $order->venda_id) {
            return null;
        }

        /** @var Venda|null $venda */
        $venda = Venda::query()->whereKey((int) $order->venda_id)->lockForUpdate()->first();

        if (! $venda instanceof Venda) {
            return null;
        }

        if ($venda->status === Venda::STATUS_FECHADO) {
            throw new \RuntimeException('Pedido já foi faturado.');
        }

        if ($venda->status === Venda::STATUS_ABERTO) {
            return $venda;
        }

        // Cancelada definitiva: próximo faturar cria nova venda.
        return null;
    }

    /**
     * @param  callable(string): void  $avancar
     * @return array{venda: Venda, contas_receber: list<ContaReceber>}
     */
    private function refaturarVendaExistente(
        ForcaVendasOrder $order,
        Pedido $pedido,
        Venda $venda,
        ?PixCobranca $pixPago,
        ?Vendedor $vendedor,
        ?int $empresaId,
        ?int $estoqueId,
        \Carbon\CarbonInterface $abertura,
        \Carbon\CarbonInterface $fechamento,
        mixed $formaPagamento,
        bool $isTelaErp,
        callable $avancar,
    ): array {
        $avancar('Atualizando venda');

        // Não reutiliza número de documento fiscal — trava permanece no Monitor.
        // Mantém id/numero; atualiza cabeçalho comercial.
        $venda->forceFill([
            'empresa_id' => $empresaId,
            'data' => $abertura->toDateString(),
            'hora' => $fechamento->format('H:i:s'),
            'hora_abertura' => $order->dataAberturaAt() !== null
                ? $abertura->format('H:i:s')
                : $venda->hora_abertura,
            'cliente_id' => $pedido->cliente_id,
            'vendedor_id' => $vendedor?->id,
            'vendedor_nome' => $vendedor?->nome,
            'total' => $pedido->total,
            'forma_pagamento' => $formaPagamento,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => $isTelaErp ? Venda::PLATAFORMA_ERP : Venda::PLATAFORMA_MOBILE,
        ])->save();

        return $this->aplicarEfeitosFaturamento(
            $order,
            $pedido,
            $venda->fresh() ?? $venda,
            $pixPago,
            $empresaId,
            $estoqueId,
            $avancar,
            sincronizarItens: true,
        );
    }

    /**
     * Baixa estoque, financeiro, reserva, vínculos e logística após create/update da venda.
     *
     * @param  callable(string): void  $avancar
     * @return array{venda: Venda, contas_receber: list<ContaReceber>}
     */
    private function aplicarEfeitosFaturamento(
        ForcaVendasOrder $order,
        Pedido $pedido,
        Venda $venda,
        ?PixCobranca $pixPago,
        ?int $empresaId,
        ?int $estoqueId,
        callable $avancar,
        bool $sincronizarItens = false,
    ): array {
        if ($pixPago !== null) {
            $pixPago->forceFill(['venda_id' => $venda->id])->save();
        }

        $avancar('Movimentando estoque');

        if ($sincronizarItens) {
            VendaItem::query()->where('venda_id', (int) $venda->id)->delete();
        }

        $stock = new PdvStockService();
        $docSaida = $this->documentoBase($order);
        $empresa = $empresaId
            ? \App\Models\Empresa::query()->find($empresaId)
            : null;

        foreach ($pedido->itens as $item) {
            if (! $item->product_id) {
                continue;
            }

            $vendaItem = VendaItem::query()->create([
                'venda_id' => $venda->id,
                'product_id' => $item->product_id,
                'quantidade' => $item->quantidade,
                'valor_item' => $item->preco_unitario,
                'total' => $item->total,
            ]);

            $product = Product::query()->find($item->product_id);

            if ($product) {
                $stock->baixaItemVenda(
                    $product,
                    (float) $item->quantidade,
                    $item->product_grade_id,
                    null,
                    $docSaida,
                    $estoqueId,
                    $empresa,
                    EstoqueMovimentacaoContext::make(
                        EstoqueMovimentacao::TIPO_VENDA,
                        empresaId: $empresa?->id !== null ? (int) $empresa->id : null,
                        origemTipo: 'venda',
                        origemId: (int) $venda->id,
                        origemNumero: $venda->numero !== null ? (string) $venda->numero : null,
                    ),
                    'venda_item',
                    (int) $vendaItem->id,
                );
            }
        }

        $avancar('Gerando financeiro');

        $contas = $this->gerarContasReceber($venda, $pedido, $order, $pixPago);

        $avancar('Finalizando pedido');

        (new EstoqueReservaService())->consumirPedido($order);

        $order->forceFill([
            'venda_id' => $venda->id,
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
            'faturado_at' => now(),
            'canceled_at' => null,
        ])->save();

        if ($pedido->status !== Pedido::STATUS_FECHADO) {
            $pedido->update(['status' => Pedido::STATUS_FECHADO]);
        }

        (new VendasInternasMonitorHookService())->onForcaVendasOrderFaturado($order);

        $origemExpedicao = (($order->payload['origem'] ?? '') === 'vendas_internas')
            ? Entrega::ORIGEM_VI
            : Entrega::ORIGEM_MONITOR;

        (new LogisticaVendaHookService())->onVendaFechada($venda->fresh() ?? $venda, $origemExpedicao);

        return ['venda' => $venda->fresh() ?? $venda, 'contas_receber' => $contas];
    }

    /**
     * Conclui pedido Força já pago no PDV offline: amarra a venda espelhada,
     * marca faturado no Monitor e consome reserva — sem baixar estoque/financeiro
     * de novo (já feitos no retorno do caixa).
     */
    public function concluirViaPdv(ForcaVendasOrder $order, Venda $venda): void
    {
        if ($order->situacao === ForcaVendasOrder::SITUACAO_FATURADO && (int) $order->venda_id === (int) $venda->id) {
            return;
        }

        if ($order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO) {
            throw new \RuntimeException('Pedido cancelado não pode ser concluído pelo PDV.');
        }

        if ($order->situacao === ForcaVendasOrder::SITUACAO_FINANCEIRO) {
            throw new \RuntimeException('Pedido aguarda liberação financeira antes de faturar.');
        }

        if ($order->venda_id && (int) $order->venda_id !== (int) $venda->id) {
            throw new \RuntimeException('Pedido já vinculado a outra venda.');
        }

        (new EstoqueReservaService())->consumirPedido($order);

        $order->forceFill([
            'venda_id' => $venda->id,
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
            'faturado_at' => now(),
        ])->save();

        $pedido = $order->pedido;
        if ($pedido !== null && $pedido->status === Pedido::STATUS_ABERTO) {
            $pedido->forceFill(['status' => Pedido::STATUS_IMPORTADO])->save();
        }

        (new VendasInternasMonitorHookService())->onForcaVendasOrderFaturado($order);
    }

    /**
     * Cancela um pedido faturado: devolve o estoque, gera contra-lançamento no
     * Livro Caixa, apaga contas a receber em aberto e cancela a venda/pedido.
     *
     * Bloqueia quando há boleto emitido, título já recebido ou entrega expedida.
     *
     * @throws \RuntimeException
     */
    public function estornar(
        ForcaVendasOrder $order,
        ?string $motivo = null,
        ?callable $aoAvancar = null,
        bool $reabrir = false,
    ): void
    {
        $avancar = function (string $nome) use ($aoAvancar): void {
            if ($aoAvancar !== null) {
                $aoAvancar($nome);
            }
        };

        if (! $order->venda_id) {
            throw new \RuntimeException('Pedido sem venda gerada para cancelar.');
        }

        $motivoLogistica = trim((string) $motivo);
        if ($motivoLogistica === '') {
            $motivoLogistica = $reabrir
                ? 'Reabertura do pedido no Monitor de Vendas.'
                : 'Cancelamento no Monitor de Vendas.';
        }

        DB::transaction(function () use ($order, $motivoLogistica, $avancar, $reabrir): void {
            /** @var ForcaVendasOrder|null $order */
            $order = ForcaVendasOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();
            $venda = $order?->venda_id
                ? Venda::query()->whereKey((int) $order->venda_id)->lockForUpdate()->first()
                : null;

            if (! $order instanceof ForcaVendasOrder || ! $venda instanceof Venda) {
                throw new \RuntimeException('Pedido sem venda gerada para cancelar.');
            }

            if ($venda->status === Venda::STATUS_CANCELADO
                || (! $reabrir && $order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO)) {
                throw new \RuntimeException('Pedido já está cancelado.');
            }

            $this->garantirEntregaNaoExpedida($venda);

            if ($reabrir) {
                if ($venda->status !== Venda::STATUS_FECHADO) {
                    throw new \RuntimeException('Somente venda fechada pode ser reaberta.');
                }

                $this->reabrirVendaEstornada($order, $venda, $motivoLogistica, $avancar);

                return;
            }

            // Cancelamento definitivo após reabertura: efeitos já foram estornados.
            if ($venda->status === Venda::STATUS_ABERTO) {
                $this->cancelarVendaReaberta($order, $venda, $motivoLogistica, $avancar);

                return;
            }

            $avancar('Verificando financeiro');
            $this->garantirTitulosNaoRecebidos($order);

            $avancar('Verificando boleto bancário');
            $this->garantirSemBoletoEmitido($order);

            $stock = new PdvStockService();
            $vendedor = $order->vendedor_id
                ? Vendedor::query()->find($order->vendedor_id)
                : null;
            $empresaId = $order->empresa_id
                ? (int) $order->empresa_id
                : ($venda->empresa_id ? (int) $venda->empresa_id : ErpContext::currentEmpresaId());
            $estoqueId = $this->resolveEstoqueId($empresaId, $vendedor);

            $avancar('Devolvendo estoque');
            $this->estornarEstoque($order, $venda, $stock, $estoqueId, $empresaId);

            $avancar('Estornando financeiro');
            $this->estornarCaixaDoPedido($order, $empresaId);
            $this->contasDoPedido($order)
                ->where(function (Builder $query): void {
                    $query->whereNull('valor_recebido')->orWhere('valor_recebido', '<=', 0);
                })
                ->delete();

            $avancar('Cancelando venda');
            $venda->update(['status' => Venda::STATUS_CANCELADO]);

            (new LogisticaVendaHookService())->onVendaCancelada($venda, $motivoLogistica);

            $avancar('Finalizando pedido');
            $order->forceFill([
                'situacao' => ForcaVendasOrder::SITUACAO_CANCELADO,
                'canceled_at' => now(),
            ])->save();

            $order->load('pedido');

            if ($order->pedido && $order->pedido->status !== Pedido::STATUS_CANCELADO) {
                $order->pedido->update(['status' => Pedido::STATUS_CANCELADO]);
            }

            (new VendasInternasMonitorHookService())->onForcaVendasOrderCancelado($order);
        });
    }

    /**
     * Estorna faturamento e mantém a mesma venda (id/numero) em status aberto,
     * distinta do cancelamento definitivo.
     *
     * @param  callable(string): void  $avancar
     */
    private function reabrirVendaEstornada(
        ForcaVendasOrder $order,
        Venda $venda,
        string $motivoLogistica,
        callable $avancar,
    ): void {
        $this->garantirEntregaNaoExpedida($venda);

        $avancar('Estornando financeiro');
        $this->garantirTitulosNaoRecebidos($order);
        $this->garantirSemBoletoEmitido($order);

        try {
            (new ComissaoPeriodoService())->liberarVendaEstornada((int) $venda->id);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        $this->contasDoPedido($order)
            ->where(function (Builder $query): void {
                $query->whereNull('valor_recebido')->orWhere('valor_recebido', '<=', 0);
            })
            ->delete();

        $stock = new PdvStockService();
        $vendedor = $order->vendedor_id
            ? Vendedor::query()->find($order->vendedor_id)
            : null;
        $empresaId = $order->empresa_id
            ? (int) $order->empresa_id
            : ($venda->empresa_id ? (int) $venda->empresa_id : ErpContext::currentEmpresaId());
        $estoqueId = $this->resolveEstoqueId($empresaId, $vendedor);

        $avancar('Estornando caixa');
        $this->estornarCaixaDoPedido($order, $empresaId);

        $avancar('Devolvendo estoque');
        $this->estornarEstoque($order, $venda, $stock, $estoqueId, $empresaId);

        $avancar('Recriando reserva');
        $order->loadMissing('pedido.itens.product', 'user');

        if ($order->tipo === ForcaVendasOrder::TIPO_PEDIDO && $order->pedido) {
            if (! $order->user) {
                throw new \RuntimeException('Não foi possível recriar a reserva: pedido sem usuário.');
            }

            (new EstoqueReservaService())->reservarPedido($order, $order->pedido, $order->user);
        }

        $avancar('Reabrindo pedido');
        // Mantém id/numero; distingue de cancelamento definitivo.
        $venda->update(['status' => Venda::STATUS_ABERTO]);
        (new LogisticaVendaHookService())->onVendaCancelada($venda, $motivoLogistica);

        $order->forceFill([
            'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
            'venda_id' => $venda->id,
            'confirmed_at' => null,
            'faturado_at' => null,
            'canceled_at' => null,
        ])->save();

        $order->load('pedido');

        if ($order->pedido && $order->pedido->status !== Pedido::STATUS_ABERTO) {
            $order->pedido->update(['status' => Pedido::STATUS_ABERTO]);
        }

        (new VendasInternasMonitorHookService())->onForcaVendasOrderReaberto($order);
    }

    /**
     * Cancelamento definitivo de venda já reaberta (efeitos já estornados).
     *
     * @param  callable(string): void  $avancar
     */
    private function cancelarVendaReaberta(
        ForcaVendasOrder $order,
        Venda $venda,
        string $motivoLogistica,
        callable $avancar,
    ): void {
        $avancar('Liberando reserva');
        (new EstoqueReservaService())->liberarPedido($order);

        $avancar('Cancelando venda');
        $venda->update(['status' => Venda::STATUS_CANCELADO]);
        (new LogisticaVendaHookService())->onVendaCancelada($venda, $motivoLogistica);

        $avancar('Finalizando pedido');
        $order->forceFill([
            'situacao' => ForcaVendasOrder::SITUACAO_CANCELADO,
            'canceled_at' => now(),
        ])->save();

        $order->load('pedido');

        if ($order->pedido && $order->pedido->status !== Pedido::STATUS_CANCELADO) {
            $order->pedido->update(['status' => Pedido::STATUS_CANCELADO]);
        }

        (new VendasInternasMonitorHookService())->onForcaVendasOrderCancelado($order);
    }

    /**
     * Boletos abertos com identificação bancária ligados às contas do pedido.
     *
     * @return \Illuminate\Support\Collection<int, Boleto>
     */
    public function boletosBancariosAtivos(ForcaVendasOrder $order): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable((new Boleto)->getTable())
            || ! Schema::hasTable((new ContaReceber)->getTable())) {
            return collect();
        }

        $contaIds = $this->contasDoPedido($order)->pluck('id')->all();

        if ($contaIds === []) {
            return collect();
        }

        return Boleto::query()
            ->whereIn('conta_receber_id', $contaIds)
            ->where('status', Boleto::STATUS_ABERTO)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $linha): void {
                    $linha->whereNotNull('linha_digitavel')->where('linha_digitavel', '!=', '');
                })->orWhere(function (Builder $nosso): void {
                    $nosso->whereNotNull('nosso_numero')
                        ->where('nosso_numero', '!=', '')
                        ->where('nosso_numero', '!=', '0');
                })->orWhere(function (Builder $externo): void {
                    $externo->whereNotNull('id_externo')
                        ->where('id_externo', '!=', '')
                        ->where('id_externo', '!=', '0');
                });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Bloqueia se ainda houver boleto bancário ativo (status aberto com identificação).
     *
     * @throws \RuntimeException
     */
    private function garantirSemBoletoEmitido(ForcaVendasOrder $order): void
    {
        if ($this->boletosBancariosAtivos($order)->isEmpty()) {
            return;
        }

        throw new \RuntimeException(
            'Não é possível cancelar: existe boleto emitido para este pedido. '
            .'Baixe/cancele o boleto antes de cancelar o pedido.'
        );
    }

    /**
     * Bloqueia o cancelamento se houver título do pedido já recebido (baixado).
     *
     * @throws \RuntimeException
     */
    public function garantirTitulosNaoRecebidos(ForcaVendasOrder $order): void
    {
        if (! Schema::hasTable((new ContaReceber)->getTable())) {
            return;
        }

        $temRecebido = $this->contasDoPedido($order)
            ->where('valor_recebido', '>', 0)
            ->exists();

        if ($temRecebido) {
            throw new \RuntimeException(
                'Não é possível cancelar: existe título deste pedido já recebido (baixado). '
                .'Estorne o recebimento no Contas a Receber antes de cancelar o pedido.'
            );
        }
    }

    /**
     * Bloqueia se a entrega da venda já foi expedida (bipada).
     *
     * @throws \RuntimeException
     */
    public function garantirEntregaNaoExpedida(Venda $venda): void
    {
        if (! Schema::hasTable((new Entrega)->getTable())) {
            return;
        }

        $expedida = Entrega::query()
            ->where('venda_id', (int) $venda->id)
            ->where('status', Entrega::STATUS_EXPEDIDO)
            ->exists();

        if ($expedida) {
            throw new \RuntimeException(
                'Não é possível cancelar: a entrega deste pedido já foi expedida. '
                .'Trate a expedição antes de cancelar o pedido.'
            );
        }
    }

    /**
     * Devolve o estoque usando a mesma grade da baixa (lida do pedido de
     * origem, que é a fonte da baixa em `faturar`). Sem pedido disponível,
     * cai para os itens da venda (sem grade).
     */
    private function estornarEstoque(
        ForcaVendasOrder $order,
        Venda $venda,
        PdvStockService $stock,
        ?int $estoqueId,
        ?int $empresaId = null,
    ): void {
        $pedido = $order->pedido;
        $pedido?->loadMissing('itens');
        $empresaId = $empresaId && $empresaId > 0
            ? $empresaId
            : ErpContext::currentEmpresaId();

        $venda->loadMissing('itens');

        foreach ($venda->itens as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::query()->find($item->product_id);

            if ($product) {
                $gradeId = null;
                if ($pedido) {
                    $pedidoItem = $pedido->itens->first(
                        fn ($pi): bool => (int) ($pi->product_id ?? 0) === (int) $item->product_id
                    );
                    $gradeId = $pedidoItem?->product_grade_id ? (int) $pedidoItem->product_grade_id : null;
                }

                $stock->estornoItemVenda(
                    $product,
                    (float) $item->quantidade,
                    $gradeId,
                    null,
                    $estoqueId,
                    EstoqueMovimentacaoContext::make(
                        EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO,
                        empresaId: $empresaId,
                        origemTipo: 'venda',
                        origemId: (int) $venda->id,
                        origemNumero: $venda->numero !== null ? (string) $venda->numero : null,
                    ),
                    'venda_item',
                    (int) $item->id,
                );
            }
        }
    }

    /**
     * Cancela pedido ainda pendente (sem venda) e libera as reservas de estoque.
     */
    public function cancelarPendente(ForcaVendasOrder $order): void
    {
        if ($order->situacao === ForcaVendasOrder::SITUACAO_FATURADO || $order->venda_id) {
            throw new \RuntimeException('Pedido faturado deve ser estornado, não cancelado.');
        }

        if ($order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO) {
            throw new \RuntimeException('Pedido já está cancelado.');
        }

        DB::transaction(function () use ($order): void {
            (new EstoqueReservaService())->liberarPedido($order);

            if ($order->pedido && $order->pedido->status !== Pedido::STATUS_CANCELADO) {
                $order->pedido->update(['status' => Pedido::STATUS_CANCELADO]);
            }

            $order->forceFill([
                'situacao' => ForcaVendasOrder::SITUACAO_CANCELADO,
                'canceled_at' => now(),
            ])->save();

            (new VendasInternasMonitorHookService())->onForcaVendasOrderCancelado($order);
        });
    }

    /**
     * Libera pedido com restrição financeira → volta para pendente (pronto para faturar).
     */
    public function liberarFinanceiro(ForcaVendasOrder $order, ?User $user = null): void
    {
        if ($order->situacao !== ForcaVendasOrder::SITUACAO_FINANCEIRO) {
            throw new \RuntimeException('Pedido não está aguardando liberação financeira.');
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        $payload['financeiro_liberado'] = true;
        $payload['financeiro_liberado_at'] = now()->toIso8601String();
        if ($user !== null) {
            $payload['financeiro_liberado_por'] = $user->id;
            $payload['financeiro_liberado_por_nome'] = $user->name;
        }

        $order->forceFill([
            'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
            'payload' => $payload,
        ])->save();
    }

    /**
     * Documento base das contas a receber do pedido (sem sufixo de parcela).
     */
    private function documentoBase(ForcaVendasOrder $order): string
    {
        return 'FV-' . $order->id;
    }

    /**
     * Query de todas as contas a receber do pedido (parcela única ou múltiplas).
     */
    private function contasDoPedido(ForcaVendasOrder $order): Builder
    {
        $base = $this->documentoBase($order);

        return ContaReceber::query()->where(fn (Builder $q) => $q
            ->where('documento', $base)
            ->orWhere('documento', 'like', $base . '/%'));
    }

    /**
     * Financeiro do faturamento:
     * - à vista dinheiro/PIX/caixa → sessão PDV ou Livro Caixa (sem Contas a Receber)
     * - depósito → Livro Caixa
     * - demais / a prazo → Contas a Receber
     *
     * @return list<ContaReceber>
     */
    private function gerarContasReceber(
        Venda $venda,
        Pedido $pedido,
        ForcaVendasOrder $order,
        ?PixCobranca $pixPago = null,
    ): array {
        $total = round((float) $pedido->total, 2);

        if ($total <= 0) {
            return [];
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        // Uma resolução por pedido (não por parcela): evita N+1 no carnê.
        $formaModel = $this->resolveFormaPagamento($payload);
        $clienteId = (int) $pedido->cliente_id;
        $diasTabelaCliente = $this->diasTabelaPrazoCliente($clienteId);
        $baixa = app(ContaReceberBaixaService::class);
        $hoje = ErpTimezone::toLocal()->startOfDay();
        $base = $this->documentoBase($order);
        $formaLabel = mb_strtoupper(trim((string) (
            $formaModel?->descricao
            ?? $payload['forma_pagamento']
            ?? ($pixPago ? 'PIX' : 'DINHEIRO')
        )), 'UTF-8');
        $empresaId = $order->empresa_id ? (int) $order->empresa_id : ErpContext::currentEmpresaId();
        $prefixoHist = $this->isTelaVendaErp($order) ? 'VENDA ERP ' : 'VENDA APP ';
        $planoVenda = EmpresaParametros::planoVendaLancamento($empresaId);
        $historicoCaixa = $prefixoHist.'FV ('.$formaLabel.') ['.$base.']';

        // PIX pago no app: caixa sem CR.
        if ($pixPago !== null) {
            $this->lancarEntradaCaixaVenda(
                order: $order,
                formaModel: $formaModel,
                total: $total,
                data: $hoje->toDateString(),
                documento: $base,
                historico: $prefixoHist.'FV (PIX) ['.$base.']',
                formaLabel: 'PIX',
                empresaId: $empresaId,
                planoVenda: $planoVenda,
                permitirSessao: true,
            );

            return [];
        }

        $dias = $this->resolverParcelasDias($payload, $formaModel, $diasTabelaCliente);
        $n = count($dias);
        $forma = $formaModel
            ? $baixa->mapFormaConta($formaModel)
            : $this->mapForma((string) ($payload['forma_pagamento'] ?? ''));

        $movimento = FormaPagamentoDestino::from($formaModel);

        // Crédito cliente / troca / nenhum: sem lançamento financeiro.
        if ($formaModel !== null && FormaPagamentoDestino::semLancamento($movimento)) {
            return [];
        }

        // Caixa: sessão PDV (se aplicável) ou Livro. Depósito: sempre Livro.
        if ($formaModel !== null && (
            FormaPagamentoDestino::vaiParaCaixa($movimento)
            || FormaPagamentoDestino::vaiParaDeposito($movimento)
        )) {
            $this->lancarEntradaCaixaVenda(
                order: $order,
                formaModel: $formaModel,
                total: $total,
                data: $hoje->toDateString(),
                documento: $base,
                historico: $historicoCaixa,
                formaLabel: $formaLabel,
                empresaId: $empresaId,
                planoVenda: $planoVenda,
                permitirSessao: FormaPagamentoDestino::vaiParaCaixa($movimento),
            );

            return [];
        }

        // Contas a receber pelo cadastro.
        if ($formaModel !== null && ! FormaPagamentoDestino::geraContasReceber($movimento)) {
            return [];
        }

        // Legado sem forma cadastrada: à vista dinheiro/PIX → caixa.
        if ($formaModel === null) {
            $aVista = $n === 1 && (int) $dias[0] === 0;
            if ($aVista && in_array($forma, ['dinheiro', ContaReceber::FORMA_PIX], true)) {
                $this->lancarEntradaCaixaVenda(
                    order: $order,
                    formaModel: null,
                    total: $total,
                    data: $hoje->toDateString(),
                    documento: $base,
                    historico: $historicoCaixa,
                    formaLabel: $formaLabel !== '' ? $formaLabel : 'DINHEIRO',
                    empresaId: $empresaId,
                    planoVenda: $planoVenda,
                    permitirSessao: true,
                );

                return [];
            }
        }

        if ($clienteId <= 0) {
            return [];
        }

        $parcelaBase = floor($total / $n * 100) / 100;
        $criadas = [];

        foreach (array_values($dias) as $i => $dia) {
            $valor = $i === $n - 1
                ? round($total - $parcelaBase * ($n - 1), 2)
                : $parcelaBase;

            $documento = $n > 1 ? $base.'/'.($i + 1) : $base;

            $payload = [
                'empresa_id' => $order->empresa_id
                    ? (int) $order->empresa_id
                    : ErpContext::currentEmpresaId(),
                'numero' => ContaReceber::nextNumero(),
                'emissao' => $hoje,
                'historico' => 'PEDIDO APP FV'
                    .($n > 1 ? ' ('.($i + 1).'/'.$n.')' : ''),
                'documento' => $documento,
                'cliente_id' => $clienteId,
                'vencimento' => $hoje->copy()->addDays(max(0, $dia)),
                'valor' => $valor,
                'forma' => $forma,
                ...ContaReceberJurosCarteira::atributosParaCreate(
                    $forma,
                    $order->empresa_id ? (int) $order->empresa_id : null
                ),
            ];

            if (! empty($planoVenda['id'])) {
                $payload['plano_conta_id'] = (int) $planoVenda['id'];
            }

            $criadas[] = ContaReceber::query()->create($payload);
        }

        return $criadas;
    }

    /**
     * @param  array{id: int|null, nome: string|null}|null  $planoVenda
     */
    private function lancarEntradaCaixaVenda(
        ForcaVendasOrder $order,
        ?FormaPagamento $formaModel,
        float $total,
        string $data,
        string $documento,
        string $historico,
        string $formaLabel,
        ?int $empresaId,
        ?array $planoVenda,
        bool $permitirSessao,
    ): void {
        $total = round($total, 2);
        if ($total <= 0) {
            return;
        }

        // Idempotência: já lançado (Livro ou sessão) com saldo líquido.
        if ($this->saldoLiquidoLivroDocumento($documento) > 0.005
            || $this->saldoLiquidoSessaoDocumento($documento) > 0.005) {
            return;
        }

        $destino = $this->resolverDestinoCaixaVenda($order, $formaModel);
        $viaSessao = $permitirSessao && ($destino['sessao_id'] ?? null);

        if ($viaSessao) {
            $this->registrarMovimentoSessaoVenda(
                sessaoId: (int) $destino['sessao_id'],
                valor: $total,
                historico: $historico,
                formaLabel: $formaLabel,
                estorno: false,
            );

            return;
        }

        app(ContaReceberBaixaService::class)->registrarEntradaCaixa(
            valor: $total,
            data: $data,
            documento: $documento,
            historico: $historico,
            caixaContaId: (int) $destino['caixa_conta_id'],
            empresaId: $empresaId,
            planoContaId: $planoVenda['id'] ?? null,
            planoNome: $planoVenda['nome'] ?? null,
        );
    }

    /**
     * Forma no payload do pedido ou no DAV (texto/id).
     */
    private function pedidoTemFormaPagamento(ForcaVendasOrder $order, Pedido $pedido): bool
    {
        $payload = is_array($order->payload) ? $order->payload : [];

        if ((int) ($payload['forma_pagamento_id'] ?? 0) > 0) {
            return true;
        }

        if (trim((string) ($payload['forma_pagamento'] ?? '')) !== '') {
            return true;
        }

        return trim((string) ($pedido->forma_pagamento ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveFormaPagamento(array $payload): ?FormaPagamento
    {
        $id = (int) ($payload['forma_pagamento_id'] ?? 0);
        if ($id > 0) {
            $forma = FormaPagamento::query()->find($id);
            if ($forma) {
                return $forma;
            }
        }

        $descricao = trim((string) ($payload['forma_pagamento'] ?? ''));
        if ($descricao === '') {
            return null;
        }

        return FormaPagamento::query()
            ->whereRaw('UPPER(descricao) = ?', [mb_strtoupper($descricao, 'UTF-8')])
            ->orderByDesc('ativo')
            ->first();
    }

    /**
     * Destino do caixa da venda (nunca usa o operador do Monitor).
     *
     * @return array{caixa_conta_id: int, sessao_id: int|null}
     */
    private function resolverDestinoCaixaVenda(ForcaVendasOrder $order, ?FormaPagamento $formaModel): array
    {
        $empresaId = $order->empresa_id ? (int) $order->empresa_id : ErpContext::currentEmpresaId();
        $payload = is_array($order->payload) ? $order->payload : [];
        $selecionadaId = (int) ($payload['caixa_id'] ?? $payload['caixa_conta_id'] ?? 0) ?: null;

        $usuario = null;
        if ($order->vendedor_id) {
            $vendedor = Vendedor::query()->with('usuario')->find($order->vendedor_id);
            $usuario = $vendedor?->usuario;
        }
        if (! $usuario && $order->user_id) {
            $usuario = User::query()->find((int) $order->user_id);
        }

        return app(VendaCaixaDestinoResolver::class)->resolver(
            $formaModel,
            $selecionadaId,
            $usuario instanceof User ? $usuario : null,
            $empresaId,
        );
    }

    /**
     * Estorna caixa na origem: sessão PDV e/ou Livro Caixa.
     * Sessão já fechada: contra-lançamento também no CAIXA GERAL (fechamento agregou lá).
     */
    private function estornarCaixaDoPedido(ForcaVendasOrder $order, ?int $empresaId = null): void
    {
        $empresaId = $empresaId && $empresaId > 0
            ? $empresaId
            : ErpContext::currentEmpresaId();
        $base = $this->documentoBase($order);

        $this->estornarMovimentosSessaoDoPedido($order, $base, $empresaId);
        $this->estornarLancamentosCaixaDoPedido($order, $empresaId);
    }

    private function estornarMovimentosSessaoDoPedido(
        ForcaVendasOrder $order,
        string $base,
        ?int $empresaId,
    ): void {
        if (! Schema::hasTable((new PdvCaixaMovimento)->getTable())) {
            return;
        }

        $movimentos = PdvCaixaMovimento::query()
            ->where('historico', 'like', '%['.$base.']%')
            ->orderBy('id')
            ->get();

        if ($movimentos->isEmpty()) {
            return;
        }

        $grupos = $movimentos->groupBy(
            fn (PdvCaixaMovimento $m): string => ((int) $m->pdv_caixa_sessao_id).'|'.mb_strtoupper(trim((string) ($m->forma_pagamento ?? 'DINHEIRO')), 'UTF-8')
        );

        $hoje = ErpTimezone::toLocal()->toDateString();
        $baixa = app(ContaReceberBaixaService::class);
        $geralId = (int) CaixaConta::ensureCaixaGeral()->id;
        $planoVenda = EmpresaParametros::planoVendaLancamento($empresaId);

        foreach ($grupos as $grupo) {
            $liquido = round((float) $grupo->sum('entrada') - (float) $grupo->sum('saida'), 2);
            if ($liquido <= 0.005) {
                continue;
            }

            /** @var PdvCaixaMovimento $ref */
            $ref = $grupo->first();
            $sessaoId = (int) $ref->pdv_caixa_sessao_id;
            $forma = mb_strtoupper(trim((string) ($ref->forma_pagamento ?? 'DINHEIRO')), 'UTF-8');
            if ($forma === '') {
                $forma = 'DINHEIRO';
            }

            $historicoOrig = trim((string) ($ref->historico ?? ''));
            $historico = $historicoOrig !== ''
                ? 'ESTORNO '.$historicoOrig
                : 'ESTORNO ['.$base.']';

            $this->registrarMovimentoSessaoVenda(
                sessaoId: $sessaoId,
                valor: $liquido,
                historico: $historico,
                formaLabel: $forma,
                estorno: true,
            );

            $sessao = PdvCaixaSessao::query()->find($sessaoId);
            // Fechamento já consolidou no Geral (PDV-CX-*): compensa com documento FV.
            // Sem PDV-CX, o dinheiro nunca entrou no Livro — só o estorno na sessão basta.
            if ($sessao && ! $sessao->isAberto() && $this->sessaoJaConsolidadaNoLivro($sessaoId)) {
                // Já há lançamento FV neste documento (entrada antiga ou compensação).
                if (abs($this->saldoLiquidoLivroDocumento($base)) > 0.005) {
                    continue;
                }

                $baixa->registrarSaidaCaixa(
                    valor: $liquido,
                    data: $hoje,
                    documento: $base,
                    historico: $historico,
                    caixaContaId: $geralId,
                    empresaId: $empresaId,
                    planoContaId: $planoVenda['id'] ?? null,
                    planoNome: $planoVenda['nome'] ?? null,
                );
            }
        }
    }

    private function sessaoJaConsolidadaNoLivro(int $sessaoId): bool
    {
        if ($sessaoId <= 0 || ! Schema::hasTable((new CaixaLancamento)->getTable())) {
            return false;
        }

        $base = 'PDV-CX-'.$sessaoId;

        return CaixaLancamento::query()
            ->where(function ($query) use ($base): void {
                $query->where('documento', $base)
                    ->orWhere('documento', 'like', $base.'-%');
            })
            ->exists();
    }

    private function registrarMovimentoSessaoVenda(
        int $sessaoId,
        float $valor,
        string $historico,
        string $formaLabel,
        bool $estorno,
    ): void {
        if ($sessaoId <= 0 || $valor <= 0) {
            return;
        }

        if (! Schema::hasTable((new PdvCaixaMovimento)->getTable())) {
            return;
        }

        $forma = mb_strtoupper(trim($formaLabel), 'UTF-8');
        if ($forma === '') {
            $forma = 'DINHEIRO';
        }

        PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessaoId,
            'tipo' => $estorno ? 'estorno' : 'venda',
            'historico' => $historico,
            'forma_pagamento' => $forma,
            'entrada' => $estorno ? 0 : $valor,
            'saida' => $estorno ? $valor : 0,
            'pdv_venda_id' => null,
        ]);
    }

    private function saldoLiquidoLivroDocumento(string $documento): float
    {
        if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
            return 0.0;
        }

        $rows = CaixaLancamento::query()
            ->where(function ($query) use ($documento): void {
                $query->where('documento', $documento)
                    ->orWhere('documento', 'like', $documento.'/%');
            })
            ->get(['entrada', 'saida']);

        return round((float) $rows->sum('entrada') - (float) $rows->sum('saida'), 2);
    }

    private function saldoLiquidoSessaoDocumento(string $documento): float
    {
        if (! Schema::hasTable((new PdvCaixaMovimento)->getTable())) {
            return 0.0;
        }

        $rows = PdvCaixaMovimento::query()
            ->where('historico', 'like', '%['.$documento.']%')
            ->get(['entrada', 'saida']);

        return round((float) $rows->sum('entrada') - (float) $rows->sum('saida'), 2);
    }

    /**
     * Gera contra-lançamento (saída) pelo saldo líquido das entradas do pedido
     * no Livro Caixa. Não apaga o lançamento original.
     */
    private function estornarLancamentosCaixaDoPedido(ForcaVendasOrder $order, ?int $empresaId = null): void
    {
        if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
            return;
        }

        $base = $this->documentoBase($order);

        $lancamentos = CaixaLancamento::query()
            ->where(function ($query) use ($base): void {
                $query->where('documento', $base)
                    ->orWhere('documento', 'like', $base.'/%');
            })
            ->orderBy('id')
            ->get();

        if ($lancamentos->isEmpty()) {
            return;
        }

        $baixa = app(ContaReceberBaixaService::class);
        $hoje = ErpTimezone::toLocal()->toDateString();
        $empresaId = $empresaId && $empresaId > 0
            ? $empresaId
            : ErpContext::currentEmpresaId();

        $grupos = $lancamentos->groupBy(
            fn (CaixaLancamento $l): string => ((int) ($l->caixa_conta_id ?? 0)).'|'.(string) ($l->documento ?? $base)
        );

        foreach ($grupos as $grupo) {
            $liquido = round((float) $grupo->sum('entrada') - (float) $grupo->sum('saida'), 2);
            if ($liquido <= 0) {
                continue;
            }

            /** @var CaixaLancamento $ref */
            $ref = $grupo->first(fn (CaixaLancamento $l): bool => (float) $l->entrada > 0) ?? $grupo->first();
            $documento = (string) ($ref->documento ?: $base);
            $historicoOrig = trim((string) ($ref->historico ?? ''));
            $historico = $historicoOrig !== ''
                ? 'ESTORNO '.$historicoOrig
                : 'ESTORNO '.$base;

            $planoContaId = $ref->plano_conta_id ? (int) $ref->plano_conta_id : null;
            $planoNome = filled($ref->plano_contas) ? (string) $ref->plano_contas : null;

            if (! $planoContaId) {
                $planoVenda = EmpresaParametros::planoVendaLancamento(
                    $ref->empresa_id ? (int) $ref->empresa_id : $empresaId
                );
                $planoContaId = $planoVenda['id'] ?? null;
                $planoNome = $planoVenda['nome'] ?? null;
            }

            $baixa->registrarSaidaCaixa(
                valor: $liquido,
                data: $hoje,
                documento: $documento,
                historico: $historico,
                caixaContaId: $ref->caixa_conta_id ? (int) $ref->caixa_conta_id : null,
                empresaId: $ref->empresa_id
                    ? (int) $ref->empresa_id
                    : $empresaId,
                planoContaId: $planoContaId,
                planoNome: $planoNome,
            );
        }
    }

    /**
     * Resolve dias de carnê a partir do pedido FV (prévia Monitor / faturamento).
     * Uma consulta de forma + uma de tabela do cliente por pedido.
     *
     * @return list<int>
     */
    public function resolverParcelasDiasDoPedido(ForcaVendasOrder $order, ?int $clienteId = null): array
    {
        $payload = is_array($order->payload) ? $order->payload : [];
        $forma = $this->resolveFormaPagamento($payload);
        $pessoaId = $clienteId !== null
            ? $clienteId
            : (int) ($order->cliente_id ?? 0);

        return $this->resolverParcelasDias(
            $payload,
            $forma,
            $this->diasTabelaPrazoCliente($pessoaId),
        );
    }

    /**
     * Dias de vencimento do carnê no faturamento Monitor/app.
     *
     * Com modo_prazo explícito:
     * 1) canhoto do cartão (cartao_canhoto.dias), se houver
     * 2) tabela fixa do cliente
     * 3) financeiro → só max_parcelas + intervalo_parcelas
     * 4) tabela → Prazo Avulso (condicao_pagamento), depois tabela_prazo_dias
     *    somente se existir na mesma forma; senão erro
     *
     * Sem modo_prazo (legado): payload negociado, depois tabela do cliente,
     * depois prazo financeiro da heurística, depois [0].
     *
     * @param  array<string, mixed>  $payload
     * @param  list<int>|null  $diasTabelaCliente
     * @return list<int>
     */
    public function resolverParcelasDias(
        array $payload,
        ?FormaPagamento $forma = null,
        ?array $diasTabelaCliente = null,
    ): array {
        if ($this->formaEhAVistaSemPrazo($forma)) {
            return [0];
        }

        $modo = mb_strtolower(trim((string) ($forma?->modo_prazo ?? '')), 'UTF-8');

        if ($modo === FormaPagamento::MODO_PRAZO_FINANCEIRO || $modo === FormaPagamento::MODO_PRAZO_TABELA) {
            $canhoto = $this->diasCanhotoDoPayload($payload);

            if ($canhoto !== []) {
                return $canhoto;
            }

            $doCliente = $this->diasLista($diasTabelaCliente);

            if ($doCliente !== []) {
                return $doCliente;
            }

            if ($modo === FormaPagamento::MODO_PRAZO_FINANCEIRO) {
                $resolved = PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade(
                    null,
                    (int) ($forma?->max_parcelas ?? 0),
                    (int) ($forma?->intervalo_parcelas ?? 0),
                    FormaPagamento::MODO_PRAZO_FINANCEIRO,
                );

                return ($resolved !== null && $resolved !== []) ? $resolved : [0];
            }

            // App/Tela: Prazo Avulso tem prioridade sobre tabela_prazo_dias.
            $avulso = $this->diasPrazoAvulsoDoPayload($payload);

            if ($avulso !== []) {
                return $avulso;
            }

            $recebido = $this->diasTabelaPrazoDoPayload($payload);

            if ($recebido !== [] && $this->tabelaPrazoConfereComForma($forma, $recebido)) {
                return $recebido;
            }

            throw new \RuntimeException('Não existe prazo válido para a forma selecionada.');
        }

        $negociado = $this->diasNegociadosDoPayload($payload);

        if ($negociado !== []) {
            return $negociado;
        }

        $resolved = PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade(
            $diasTabelaCliente,
            (int) ($forma?->max_parcelas ?? 0),
            (int) ($forma?->intervalo_parcelas ?? 0),
            $forma?->modo_prazo,
        );

        return ($resolved !== null && $resolved !== []) ? $resolved : [0];
    }

    /**
     * Dinheiro, PIX e Troca são à vista. O modo de prazo gravado na forma não se aplica.
     */
    private function formaEhAVistaSemPrazo(?FormaPagamento $forma): bool
    {
        $tipo = mb_strtolower(trim((string) ($forma?->tipo ?? '')), 'UTF-8');

        return in_array($tipo, ['dinheiro', 'pix', 'troca'], true);
    }

    /**
     * Canhoto da POS (parcelas do cartão). Não é prazo de boleto.
     *
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    private function diasCanhotoDoPayload(array $payload): array
    {
        $canhotoDias = $payload['cartao_canhoto']['dias'] ?? null;

        if (! is_array($canhotoDias) || $canhotoDias === []) {
            return [];
        }

        return $this->diasLista($canhotoDias);
    }

    /**
     * @param  list<int|string>|null  $dias
     * @return list<int>
     */
    private function diasLista(?array $dias): array
    {
        return collect($dias ?? [])
            ->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    private function diasTabelaPrazoDoPayload(array $payload): array
    {
        $prazoRaw = $payload['tabela_prazo_dias'] ?? '';

        if (is_array($prazoRaw)) {
            return $this->diasLista($prazoRaw);
        }

        return $this->diasDeString((string) $prazoRaw);
    }

    /**
     * Prazo Avulso negociado no app/Tela (`condicao_pagamento`), ex.: "15" ou "30,60,90".
     *
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    private function diasPrazoAvulsoDoPayload(array $payload): array
    {
        return $this->diasDeString((string) ($payload['condicao_pagamento'] ?? ''));
    }

    /**
     * A lista inteira precisa ser igual a uma tabela da mesma forma.
     *
     * @param  list<int>  $dias
     */
    private function tabelaPrazoConfereComForma(?FormaPagamento $forma, array $dias): bool
    {
        if ($forma === null || $dias === []) {
            return false;
        }

        $tabelas = $forma->relationLoaded('tabelasPrazo')
            ? $forma->tabelasPrazo
            : $forma->tabelasPrazo()->get(['dias']);

        foreach ($tabelas as $tabela) {
            $cadastrada = PdvFinalizarPagamentosHelper::diasDeString((string) $tabela->dias);

            if ($cadastrada === $dias) {
                return true;
            }
        }

        return false;
    }

    private function diasNegociadosDoPayload(array $payload): array
    {
        // 1º Canhoto POS. Não é prazo de boleto.
        $canhoto = $this->diasCanhotoDoPayload($payload);

        if ($canhoto !== []) {
            return $canhoto;
        }

        $avulso = $this->diasPrazoAvulsoDoPayload($payload);

        if ($avulso !== []) {
            return $avulso;
        }

        $prazoRaw = $payload['tabela_prazo_dias'] ?? '';

        if (is_array($prazoRaw)) {
            return collect($prazoRaw)
                ->map(fn ($d): int => (int) $d)
                ->filter(fn (int $d): bool => $d >= 0)
                ->values()
                ->all();
        }

        return $this->diasDeString((string) $prazoRaw);
    }

    /**
     * Uma query por pedido: Person + TabelaPrazo (eager).
     *
     * @return list<int>|null
     */
    private function diasTabelaPrazoCliente(int $clienteId): ?array
    {
        if ($clienteId <= 0) {
            return null;
        }

        $cliente = Person::query()
            ->with('tabelaPrazo:id,dias')
            ->find($clienteId, ['id', 'tabela_prazo_id']);

        if ($cliente === null || ! $cliente->tabela_prazo_id || $cliente->tabelaPrazo === null) {
            return null;
        }

        $dias = PdvFinalizarPagamentosHelper::diasDeString((string) $cliente->tabelaPrazo->dias);

        return $dias !== [] ? $dias : null;
    }

    /**
     * Converte "30,60,90" numa lista de dias [30, 60, 90], ignorando entradas
     * não numéricas.
     *
     * @return array<int, int>
     */
    private function diasDeString(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn ($d): string => trim((string) $d))
            ->filter(fn (string $d): bool => $d !== '' && is_numeric($d))
            ->map(fn (string $d): int => (int) $d)
            ->values()
            ->all();
    }

    /**
     * Mapeia a forma de pagamento do app para a forma da conta a receber.
     */
    private function mapForma(string $forma): string
    {
        $f = mb_strtolower(trim($forma), 'UTF-8');

        return match (true) {
            str_contains($f, 'boleto') => ContaReceber::FORMA_BOLETO,
            str_contains($f, 'cheque') => ContaReceber::FORMA_CHEQUE,
            str_contains($f, 'cart') || str_contains($f, 'pos') || str_contains($f, 'tef') => ContaReceber::FORMA_CARTAO,
            str_contains($f, 'pix') => ContaReceber::FORMA_PIX,
            str_contains($f, 'dinheiro') || str_contains($f, 'especie') || str_contains($f, 'espécie') => 'dinheiro',
            str_contains($f, 'deposit') => 'deposito',
            default => ContaReceber::FORMA_CARTEIRA,
        };
    }

    private function isTelaVendaErp(ForcaVendasOrder $order): bool
    {
        return (string) ($order->device_uuid ?? '') === 'monitor-web';
    }

    /**
     * Depósito da empresa da venda; fallback estoque do vendedor.
     */
    private function resolveEstoqueId(?int $empresaId, ?Vendedor $vendedor): ?int
    {
        $saldos = new ProductEstoqueSaldoService();

        if ($empresaId && $empresaId > 0) {
            $fromEmpresa = $saldos->estoqueIdParaEmpresa($empresaId);
            if ($fromEmpresa) {
                return $fromEmpresa;
            }
        }

        return $vendedor?->estoque_id ? (int) $vendedor->estoque_id : null;
    }
}
