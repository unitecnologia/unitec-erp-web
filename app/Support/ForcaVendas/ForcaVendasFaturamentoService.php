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
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\Financeiro\ContaReceberBaixaService;
use App\Support\Erp\Pdv\PdvStockService;
use App\Support\Logistica\LogisticaVendaHookService;
use App\Support\VendasInternas\VendasInternasMonitorHookService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fatura um pedido vindo do app Força de Vendas:
 * gera a Venda de retaguarda, dá baixa no estoque e cria as contas a receber
 * (uma por parcela, conforme o prazo escolhido no app). Também faz o estorno.
 *
 * À vista dinheiro/PIX: só Livro Caixa (sem Contas a Receber).
 * A prazo / demais formas: Contas a Receber.
 * As contas a receber usam o documento "FV-{orderId}" (e "FV-{orderId}/{n}"
 * quando há mais de uma parcela), mesma convenção da tela Monitor de Vendas.
 *
 * Observação: a baixa de estoque NÃO bloqueia por saldo (permite negativo),
 * por decisão de negócio para o faturamento automático.
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
        $estoqueId = $vendedor?->estoque_id ? (int) $vendedor->estoque_id : null;

        // Cobrança Pix paga deste pedido (verdade do servidor, casada pelo uuid).
        $pixPago = PixCobranca::query()
            ->where('order_uuid', $order->uuid)
            ->where('origem', PixCobranca::ORIGEM_PEDIDO)
            ->where('status', PixCobranca::STATUS_PAGO)
            ->latest('id')
            ->first();

        $venda = Venda::query()->create([
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
            'plataforma' => Venda::PLATAFORMA_MOBILE,
        ]);

        if ($pixPago !== null) {
            $pixPago->forceFill(['venda_id' => $venda->id])->save();
        }

        $stock = new PdvStockService();
        $docSaida = $this->documentoBase($order);

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
     * Estorna um pedido faturado: devolve o estoque, remove lançamentos de caixa
     * do pedido, apaga as contas a receber e cancela a venda.
     *
     * @throws \RuntimeException quando não há venda.
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

        DB::transaction(function () use ($order, $venda): void {
            $venda->loadMissing('itens');
            $stock = new PdvStockService();
            $vendedor = $order->vendedor_id
                ? Vendedor::query()->find($order->vendedor_id)
                : null;
            $estoqueId = $vendedor?->estoque_id ? (int) $vendedor->estoque_id : null;

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

        // Tem caixa definido (vendedor) → esse caixa; senão → CAIXA GERAL
        $caixaContaId = $this->resolveCaixaContaId($order);

        // PIX pago no app: vai direto para o Livro Caixa (sem CR).
        if ($pixPago !== null) {
            $baixa->registrarEntradaCaixa(
                valor: $total,
                data: $hoje->toDateString(),
                documento: $base,
                historico: 'VENDA APP '.$numeroPedido.' (PIX)',
                caixaContaId: $caixaContaId,
            );

            return;
        }

        $dias = $this->parcelasDias($payload);
        $n = count($dias);
        $forma = $formaModel
            ? $baixa->mapFormaConta($formaModel)
            : $this->mapForma((string) ($payload['forma_pagamento'] ?? ''));
        $aVista = $n === 1 && (int) $dias[0] === 0;

        // À vista dinheiro/PIX: só caixa, sem Contas a Receber.
        if ($aVista && $this->vaiDiretoCaixa($formaModel, $forma)) {
            $baixa->registrarEntradaCaixa(
                valor: $total,
                data: $hoje->toDateString(),
                documento: $base,
                historico: 'VENDA APP '.$numeroPedido.' ('.$formaLabel.')',
                caixaContaId: $caixaContaId,
            );

            return;
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

    /**
     * Dinheiro/PIX à vista não passam por Contas a Receber — só Livro Caixa.
     */
    private function vaiDiretoCaixa(?FormaPagamento $forma, string $formaConta): bool
    {
        if ($forma !== null) {
            $tipo = mb_strtolower(trim((string) ($forma->tipo ?? '')), 'UTF-8');
            $movimento = mb_strtolower(trim((string) ($forma->tipo_movimento ?? '')), 'UTF-8');
            $descricao = mb_strtoupper(trim((string) ($forma->descricao ?? '')), 'UTF-8');

            if ($movimento === 'contas_receber') {
                return false;
            }

            if (in_array($tipo, ['dinheiro', 'pix'], true)) {
                return true;
            }

            if (str_contains($descricao, 'DINHEIRO') || str_contains($descricao, 'PIX')) {
                return true;
            }

            if ($movimento === 'caixa' && in_array($tipo, ['dinheiro', 'pix', ''], true)) {
                return true;
            }
        }

        return in_array($formaConta, ['dinheiro', ContaReceber::FORMA_PIX], true);
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
        $avulso = $this->diasDeString((string) ($payload['condicao_pagamento'] ?? ''));

        $dias = $avulso !== []
            ? $avulso
            : $this->diasDeString((string) ($payload['tabela_prazo_dias'] ?? ''));

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
}
