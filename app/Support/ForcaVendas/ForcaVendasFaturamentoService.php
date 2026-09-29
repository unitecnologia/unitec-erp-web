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
use App\Models\PixCobranca;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\Financeiro\ContaReceberBaixaService;
use App\Support\Erp\Financeiro\ContaReceberJurosCarteira;
use App\Support\Erp\Financeiro\FormaPagamentoDestino;
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
 * Caixa: FV lança no Livro Caixa (`caixa_lancamentos`) na hora do faturamento.
 * PDV usa `pdv_caixa_movimentos` da sessão e consolida no Livro só no fechamento.
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
    public function faturar(ForcaVendasOrder $order, Pedido $pedido): array
    {
        if ($order->situacao === ForcaVendasOrder::SITUACAO_FINANCEIRO) {
            throw new \RuntimeException('Pedido aguarda liberação financeira antes de faturar.');
        }

        if ($order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO) {
            throw new \RuntimeException('Pedido cancelado não pode ser faturado.');
        }

        $pedido->loadMissing('itens');

        $dataVenda = ErpTimezone::toLocal($order->dataAberturaAt());

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

        $empresaId = $order->empresa_id
            ? (int) $order->empresa_id
            : ErpContext::currentEmpresaId();

        if ($empresaId && ! $order->empresa_id) {
            $order->forceFill(['empresa_id' => $empresaId])->save();
        }

        $estoqueId = $this->resolveEstoqueId($empresaId, $vendedor);
        $isTelaErp = $this->isTelaVendaErp($order);

        $venda = Venda::query()->create([
            'empresa_id' => $empresaId,
            'numero' => Venda::nextNumero(),
            'data' => $dataVenda->toDateString(),
            'hora' => $dataVenda->format('H:i:s'),
            'cliente_id' => $pedido->cliente_id,
            'vendedor_id' => $vendedor?->id,
            'vendedor_nome' => $vendedor?->nome,
            'total' => $pedido->total,
            'forma_pagamento' => $pixPago ? 'PIX' : ($order->payload['forma_pagamento'] ?? null),
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => $isTelaErp ? Venda::PLATAFORMA_ERP : Venda::PLATAFORMA_MOBILE,
        ]);

        if ($pixPago !== null) {
            $pixPago->forceFill(['venda_id' => $venda->id])->save();
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

            VendaItem::query()->create([
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
                );
            }
        }

        $contas = $this->gerarContasReceber($venda, $pedido, $order, $pixPago);

        (new EstoqueReservaService())->consumirPedido($order);

        $order->forceFill([
            'venda_id' => $venda->id,
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
            'faturado_at' => now(),
        ])->save();

        (new VendasInternasMonitorHookService())->onForcaVendasOrderFaturado($order);

        $origemExpedicao = (($order->payload['origem'] ?? '') === 'vendas_internas')
            ? Entrega::ORIGEM_VI
            : Entrega::ORIGEM_MONITOR;

        (new LogisticaVendaHookService())->onVendaFechada($venda, $origemExpedicao);

        return ['venda' => $venda, 'contas_receber' => $contas];
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
    public function estornar(ForcaVendasOrder $order, ?string $motivo = null): void
    {
        $venda = $order->venda_id ? Venda::query()->find($order->venda_id) : null;

        if ($venda === null) {
            throw new \RuntimeException('Pedido sem venda gerada para cancelar.');
        }

        if ($venda->status === Venda::STATUS_CANCELADO) {
            throw new \RuntimeException('Esta venda já está cancelada.');
        }

        $this->garantirSemBoletoEmitido($order);
        $this->garantirTitulosNaoRecebidos($order);
        $this->garantirEntregaNaoExpedida($venda);

        $motivoLogistica = trim((string) $motivo);
        if ($motivoLogistica === '') {
            $motivoLogistica = 'Cancelamento no Monitor de Vendas.';
        }

        DB::transaction(function () use ($order, $venda, $motivoLogistica): void {
            $stock = new PdvStockService();
            $vendedor = $order->vendedor_id
                ? Vendedor::query()->find($order->vendedor_id)
                : null;
            $empresaId = $order->empresa_id
                ? (int) $order->empresa_id
                : ($venda->empresa_id ? (int) $venda->empresa_id : ErpContext::currentEmpresaId());
            $estoqueId = $this->resolveEstoqueId($empresaId, $vendedor);

            $this->estornarEstoque($order, $venda, $stock, $estoqueId, $empresaId);

            $this->estornarLancamentosCaixaDoPedido($order, $empresaId);
            $this->contasDoPedido($order)->delete();

            $venda->update(['status' => Venda::STATUS_CANCELADO]);

            (new LogisticaVendaHookService())->onVendaCancelada($venda, $motivoLogistica);

            $order->forceFill([
                'situacao' => ForcaVendasOrder::SITUACAO_CANCELADO,
                'canceled_at' => now(),
            ])->save();
        });
    }

    /**
     * Bloqueia se houver boleto emitido (aberto com linha digitável) nos CR do pedido.
     *
     * @throws \RuntimeException
     */
    private function garantirSemBoletoEmitido(ForcaVendasOrder $order): void
    {
        if (! Schema::hasTable((new Boleto)->getTable())
            || ! Schema::hasTable((new ContaReceber)->getTable())) {
            return;
        }

        $contaIds = $this->contasDoPedido($order)->pluck('id')->all();

        if ($contaIds === []) {
            return;
        }

        $temBoleto = Boleto::query()
            ->whereIn('conta_receber_id', $contaIds)
            ->where('status', Boleto::STATUS_ABERTO)
            ->whereNotNull('linha_digitavel')
            ->where('linha_digitavel', '!=', '')
            ->exists();

        if ($temBoleto) {
            throw new \RuntimeException(
                'Não é possível cancelar: existe boleto emitido para este pedido. '
                .'Baixe/cancele o boleto antes de cancelar o pedido.'
            );
        }
    }

    /**
     * Bloqueia o cancelamento se houver título do pedido já recebido (baixado).
     *
     * @throws \RuntimeException
     */
    private function garantirTitulosNaoRecebidos(ForcaVendasOrder $order): void
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
    private function garantirEntregaNaoExpedida(Venda $venda): void
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

        $itens = $pedido && $pedido->itens->isNotEmpty()
            ? $pedido->itens
            : null;

        if ($itens !== null) {
            foreach ($itens as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::query()->find($item->product_id);

                if ($product) {
                    $stock->estornoItemVenda(
                        $product,
                        (float) $item->quantidade,
                        $item->product_grade_id ? (int) $item->product_grade_id : null,
                        null,
                        $estoqueId,
                        EstoqueMovimentacaoContext::make(
                            EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO,
                            empresaId: $empresaId,
                            origemTipo: 'venda',
                            origemId: (int) $venda->id,
                            origemNumero: $venda->numero !== null ? (string) $venda->numero : null,
                        ),
                    );
                }
            }

            return;
        }

        $venda->loadMissing('itens');

        foreach ($venda->itens as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::query()->find($item->product_id);

            if ($product) {
                $stock->estornoItemVenda(
                    $product,
                    (float) $item->quantidade,
                    null,
                    null,
                    $estoqueId,
                    EstoqueMovimentacaoContext::make(
                        EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO,
                        empresaId: $empresaId,
                        origemTipo: 'venda',
                        origemId: (int) $venda->id,
                        origemNumero: $venda->numero !== null ? (string) $venda->numero : null,
                    ),
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
     * - à vista dinheiro/PIX → só Livro Caixa (sem Contas a Receber)
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
        $numeroPedido = $pedido->numero ?? ('#'.$order->id);
        $base = $this->documentoBase($order);
        $formaLabel = mb_strtoupper(trim((string) (
            $formaModel?->descricao
            ?? $payload['forma_pagamento']
            ?? ($pixPago ? 'PIX' : 'DINHEIRO')
        )), 'UTF-8');
        $empresaId = $order->empresa_id ? (int) $order->empresa_id : ErpContext::currentEmpresaId();
        $prefixoHist = $this->isTelaVendaErp($order) ? 'VENDA ERP ' : 'VENDA APP ';

        // Caixa do vendedor (Permissões do usuário vinculado); nunca o operador do Monitor.
        $caixaContaId = $this->resolveCaixaContaId($order);

        // PIX pago no app: vai direto para o Livro Caixa (sem CR).
        if ($pixPago !== null) {
            $baixa->registrarEntradaCaixa(
                valor: $total,
                data: $hoje->toDateString(),
                documento: $base,
                historico: $prefixoHist.$numeroPedido.' (PIX)',
                caixaContaId: $caixaContaId,
                empresaId: $empresaId,
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

        // Caixa ou depósito: Livro Caixa (conta destino da forma, se houver).
        if ($formaModel !== null && (
            FormaPagamentoDestino::vaiParaCaixa($movimento)
            || FormaPagamentoDestino::vaiParaDeposito($movimento)
        )) {
            $baixa->registrarEntradaCaixa(
                valor: $total,
                data: $hoje->toDateString(),
                documento: $base,
                historico: $prefixoHist.$numeroPedido.' ('.$formaLabel.')',
                caixaContaId: $formaModel->conta_destino_id
                    ? (int) $formaModel->conta_destino_id
                    : $caixaContaId,
                empresaId: $empresaId,
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
                $baixa->registrarEntradaCaixa(
                    valor: $total,
                    data: $hoje->toDateString(),
                    documento: $base,
                    historico: $prefixoHist.$numeroPedido.' ('.$formaLabel.')',
                    caixaContaId: $caixaContaId,
                    empresaId: $empresaId,
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

            $criadas[] = ContaReceber::query()->create([
                'empresa_id' => $order->empresa_id
                    ? (int) $order->empresa_id
                    : ErpContext::currentEmpresaId(),
                'numero' => ContaReceber::nextNumero(),
                'emissao' => $hoje,
                'historico' => 'PEDIDO APP '.$numeroPedido
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
            ]);
        }

        return $criadas;
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
     * Conta do Livro Caixa (sem usar o operador do Monitor):
     * 1) caixa padrão do usuário ligado ao vendedor (caixa_conta_user)
     * 2) pivot empresa_vendedor (legado)
     * 3) payload.caixa_id / caixa_conta_id
     * 4) CAIXA GERAL
     *
     * Não altera formas_pagamento.conta_destino_id (aplicado depois em gerarContasReceber).
     */
    private function resolveCaixaContaId(ForcaVendasOrder $order): int
    {
        $empresaId = $order->empresa_id ? (int) $order->empresa_id : null;

        if ($order->vendedor_id) {
            $vendedor = Vendedor::query()
                ->with(['empresas', 'usuario'])
                ->find($order->vendedor_id);

            $caixaVendedor = $vendedor?->caixaContaDaEmpresa($empresaId);
            if ($caixaVendedor?->id) {
                return (int) $caixaVendedor->id;
            }
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        $caixaPayloadId = (int) ($payload['caixa_id'] ?? $payload['caixa_conta_id'] ?? 0);
        if ($caixaPayloadId > 0 && CaixaConta::query()->whereKey($caixaPayloadId)->exists()) {
            return $caixaPayloadId;
        }

        return (int) CaixaConta::ensureCaixaGeral()->id;
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

            $baixa->registrarSaidaCaixa(
                valor: $liquido,
                data: $hoje,
                documento: $documento,
                historico: $historico,
                caixaContaId: $ref->caixa_conta_id ? (int) $ref->caixa_conta_id : null,
                empresaId: $ref->empresa_id
                    ? (int) $ref->empresa_id
                    : $empresaId,
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
     * Prioridade:
     * 1) prazo já negociado no payload (canhoto / condicao_pagamento / tabela_prazo_dias)
     * 2) tabela fixa do cliente
     * 3) prazo financeiro da forma (max_parcelas + intervalo_parcelas) via Helper
     * 4) fallback legado [0] (à vista / mesmo dia)
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
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    private function diasNegociadosDoPayload(array $payload): array
    {
        // 1º Canhoto POS da Tela de Venda.
        $canhotoDias = $payload['cartao_canhoto']['dias'] ?? null;

        if (is_array($canhotoDias) && $canhotoDias !== []) {
            $dias = collect($canhotoDias)
                ->map(fn ($d): int => (int) $d)
                ->filter(fn (int $d): bool => $d >= 0)
                ->values()
                ->all();

            if ($dias !== []) {
                return $dias;
            }
        }

        $avulso = $this->diasDeString((string) ($payload['condicao_pagamento'] ?? ''));

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
