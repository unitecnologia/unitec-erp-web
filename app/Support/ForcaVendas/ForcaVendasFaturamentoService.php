<?php

namespace App\Support\ForcaVendas;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\ContaReceber;
use App\Models\Entrega;
use App\Models\ForcaVendasOrder;
use App\Models\FormaPagamento;
use App\Models\Orcamento;
use App\Models\PixCobranca;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\Financeiro\ContaReceberBaixaService;
use App\Support\Erp\Financeiro\FormaPagamentoDestino;
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
     * Cria a Venda + baixa de estoque + contas a receber a partir do orçamento
     * já importado. Deve rodar dentro de uma transação (a do push já garante isso).
     */
    public function faturar(ForcaVendasOrder $order, Orcamento $orcamento): Venda
    {
        if ($order->situacao === ForcaVendasOrder::SITUACAO_FINANCEIRO) {
            throw new \RuntimeException('Pedido aguarda liberação financeira antes de faturar.');
        }

        if ($order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO) {
            throw new \RuntimeException('Pedido cancelado não pode ser faturado.');
        }

        $orcamento->loadMissing('itens');

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
            'cliente_id' => $orcamento->cliente_id,
            'vendedor_id' => $vendedor?->id,
            'vendedor_nome' => $vendedor?->nome,
            'total' => $orcamento->total,
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

        foreach ($orcamento->itens as $item) {
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
                );
            }
        }

        $this->gerarContasReceber($venda, $orcamento, $order, $pixPago);

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

        return $venda;
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

        $orcamento = $order->orcamento;
        if ($orcamento !== null && $orcamento->status === Orcamento::STATUS_ABERTO) {
            $orcamento->forceFill(['status' => Orcamento::STATUS_IMPORTADO])->save();
        }

        (new VendasInternasMonitorHookService())->onForcaVendasOrderFaturado($order);
    }

    /**
     * Estorna um pedido faturado: devolve o estoque (mesma grade da baixa),
     * remove lançamentos de caixa do pedido, apaga as contas a receber em aberto
     * e cancela a venda.
     *
     * Bloqueia o estorno quando alguma conta a receber do pedido já foi recebida
     * (valor_recebido > 0): apagar o título "sumiria" com dinheiro já baixado.
     * Nesse caso é preciso estornar o recebimento antes.
     *
     * @throws \RuntimeException quando não há venda ou há título já recebido.
     */
    public function estornar(ForcaVendasOrder $order): void
    {
        $venda = $order->venda_id ? Venda::query()->find($order->venda_id) : null;

        if ($venda === null) {
            throw new \RuntimeException('Pedido sem venda gerada para estornar.');
        }

        if ($venda->status === Venda::STATUS_CANCELADO) {
            throw new \RuntimeException('Esta venda já está cancelada.');
        }

        $this->garantirTitulosNaoRecebidos($order);

        DB::transaction(function () use ($order, $venda): void {
            $stock = new PdvStockService();
            $vendedor = $order->vendedor_id
                ? Vendedor::query()->find($order->vendedor_id)
                : null;
            $empresaId = $order->empresa_id
                ? (int) $order->empresa_id
                : ($venda->empresa_id ? (int) $venda->empresa_id : ErpContext::currentEmpresaId());
            $estoqueId = $this->resolveEstoqueId($empresaId, $vendedor);

            $this->estornarEstoque($order, $venda, $stock, $estoqueId);

            $this->removerLancamentosCaixaDoPedido($order);
            $this->contasDoPedido($order)->delete();

            $venda->update(['status' => Venda::STATUS_CANCELADO]);

            (new LogisticaVendaHookService())->onVendaCancelada($venda, 'Estorno no Monitor de Vendas.');

            $order->forceFill([
                'situacao' => ForcaVendasOrder::SITUACAO_CANCELADO,
                'canceled_at' => now(),
            ])->save();
        });
    }

    /**
     * Bloqueia o estorno se houver título do pedido já recebido (baixado).
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
                'Não é possível estornar: existe título deste pedido já recebido (baixado). '
                .'Estorne o recebimento no Contas a Receber antes de estornar o pedido.'
            );
        }
    }

    /**
     * Devolve o estoque usando a mesma grade da baixa (lida do orçamento de
     * origem, que é a fonte da baixa em `faturar`). Sem orçamento disponível,
     * cai para os itens da venda (sem grade).
     */
    private function estornarEstoque(
        ForcaVendasOrder $order,
        Venda $venda,
        PdvStockService $stock,
        ?int $estoqueId,
    ): void {
        $orcamento = $order->orcamento;
        $orcamento?->loadMissing('itens');

        $itens = $orcamento && $orcamento->itens->isNotEmpty()
            ? $orcamento->itens
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

            if ($order->orcamento && $order->orcamento->status !== Orcamento::STATUS_CANCELADO) {
                $order->orcamento->update(['status' => Orcamento::STATUS_CANCELADO]);
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
     */
    private function gerarContasReceber(
        Venda $venda,
        Orcamento $orcamento,
        ForcaVendasOrder $order,
        ?PixCobranca $pixPago = null,
    ): void {
        $total = round((float) $orcamento->total, 2);

        if ($total <= 0) {
            return;
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        $formaModel = $this->resolveFormaPagamento($payload);
        $baixa = app(ContaReceberBaixaService::class);
        $hoje = ErpTimezone::toLocal()->startOfDay();
        $numeroPedido = $orcamento->numero ?? ('#'.$order->id);
        $base = $this->documentoBase($order);
        $formaLabel = mb_strtoupper(trim((string) (
            $formaModel?->descricao
            ?? $payload['forma_pagamento']
            ?? ($pixPago ? 'PIX' : 'DINHEIRO')
        )), 'UTF-8');
        $empresaId = $order->empresa_id ? (int) $order->empresa_id : ErpContext::currentEmpresaId();
        $prefixoHist = $this->isTelaVendaErp($order) ? 'VENDA ERP ' : 'VENDA APP ';

        // Tem caixa definido (vendedor) → esse caixa; senão → CAIXA GERAL
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

            return;
        }

        $dias = $this->parcelasDias($payload);
        $n = count($dias);
        $forma = $formaModel
            ? $baixa->mapFormaConta($formaModel)
            : $this->mapForma((string) ($payload['forma_pagamento'] ?? ''));

        $movimento = FormaPagamentoDestino::from($formaModel);

        // Crédito cliente / troca / nenhum: sem lançamento financeiro.
        if ($formaModel !== null && FormaPagamentoDestino::semLancamento($movimento)) {
            return;
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

            return;
        }

        // Contas a receber pelo cadastro.
        if ($formaModel !== null && ! FormaPagamentoDestino::geraContasReceber($movimento)) {
            return;
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

                return;
            }
        }

        $clienteId = (int) $orcamento->cliente_id;
        if ($clienteId <= 0) {
            return;
        }

        $parcelaBase = floor($total / $n * 100) / 100;

        foreach (array_values($dias) as $i => $dia) {
            $valor = $i === $n - 1
                ? round($total - $parcelaBase * ($n - 1), 2)
                : $parcelaBase;

            $documento = $n > 1 ? $base.'/'.($i + 1) : $base;

            ContaReceber::query()->create([
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
            ]);
        }
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
     * Conta do Livro Caixa:
     * - tem caixa definido no vendedor/pedido → usa esse caixa
     * - não tem → CAIXA GERAL
     */
    private function resolveCaixaContaId(ForcaVendasOrder $order): int
    {
        $empresaId = $order->empresa_id ? (int) $order->empresa_id : null;

        if ($order->vendedor_id) {
            $vendedor = Vendedor::query()
                ->with('empresas')
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

    private function removerLancamentosCaixaDoPedido(ForcaVendasOrder $order): void
    {
        if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
            return;
        }

        $base = $this->documentoBase($order);

        CaixaLancamento::query()
            ->where(function ($query) use ($base): void {
                $query->where('documento', $base)
                    ->orWhere('documento', 'like', $base.'/%');
            })
            ->delete();
    }

    /**
     * Lê os dias de prazo do pedido (ex.: "30,60,90"). Sem prazo => à vista (hoje).
     *
     * O "Prazo Avulso" (condicao_pagamento), definido livremente pelo vendedor
     * no app, tem prioridade sobre a tabela de prazo da forma de pagamento.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, int>
     */
    private function parcelasDias(array $payload): array
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
            $dias = collect($prazoRaw)
                ->map(fn ($d): int => (int) $d)
                ->filter(fn (int $d): bool => $d >= 0)
                ->values()
                ->all();

            return $dias === [] ? [0] : $dias;
        }

        $dias = $this->diasDeString((string) $prazoRaw);

        return $dias === [] ? [0] : $dias;
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
