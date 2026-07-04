<?php

namespace App\Support\ForcaVendas;

use App\Models\ContaReceber;
use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use App\Models\PixCobranca;
use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Vendedor;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\Pdv\PdvStockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Fatura um pedido vindo do app Força de Vendas:
 * gera a Venda de retaguarda, dá baixa no estoque e cria as contas a receber
 * (uma por parcela, conforme o prazo escolhido no app). Também faz o estorno.
 *
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

        return $venda;
    }

    /**
     * Estorna um pedido faturado: devolve o estoque, apaga as contas a receber
     * (se nenhuma foi recebida) e cancela a venda.
     *
     * @throws \RuntimeException quando não há venda ou já existe título recebido.
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

        $temRecebido = $this->contasDoPedido($order)
            ->where('valor_recebido', '>', 0)
            ->exists();

        if ($temRecebido) {
            throw new \RuntimeException('Não é possível estornar: há títulos a receber já baixados para esta venda.');
        }

        DB::transaction(function () use ($order, $venda): void {
            $venda->loadMissing('itens');
            $stock = new PdvStockService();

            foreach ($venda->itens as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::query()->find($item->product_id);

                if ($product) {
                    $stock->estornoItemVenda($product, (float) $item->quantidade);
                }
            }

            $this->contasDoPedido($order)->delete();

            $venda->update(['status' => Venda::STATUS_CANCELADO]);

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
        });
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
     * Gera um título a receber por parcela do pedido.
     */
    private function gerarContasReceber(
        Venda $venda,
        Orcamento $orcamento,
        ForcaVendasOrder $order,
        ?PixCobranca $pixPago = null,
    ): void {
        $clienteId = (int) $orcamento->cliente_id;
        $total = round((float) $orcamento->total, 2);

        if ($clienteId <= 0 || $total <= 0) {
            return;
        }

        // Pix pago à vista: gera um único título já baixado (saldo zero).
        if ($pixPago !== null) {
            $hoje = ErpTimezone::toLocal()->startOfDay();
            $numeroPedido = $orcamento->numero ?? ('#' . $order->id);

            ContaReceber::query()->create([
                'numero' => ContaReceber::nextNumero(),
                'emissao' => $hoje,
                'historico' => 'PEDIDO APP ' . $numeroPedido . ' (PIX)',
                'documento' => $this->documentoBase($order),
                'cliente_id' => $clienteId,
                'vencimento' => $hoje,
                'valor' => $total,
                'valor_recebido' => $total,
                'recebido_em' => $pixPago->pago_em ?? now(),
                'forma' => ContaReceber::FORMA_PIX,
            ]);

            return;
        }

        $payload = $order->payload ?? [];
        $dias = $this->parcelasDias($payload);
        $n = count($dias);
        $base = $this->documentoBase($order);
        $forma = $this->mapForma((string) ($payload['forma_pagamento'] ?? ''));
        $numeroPedido = $orcamento->numero ?? ('#' . $order->id);
        $hoje = ErpTimezone::toLocal()->startOfDay();

        // Rateio em centavos: parcelas iniciais usam o piso, a última recebe o resto.
        $parcelaBase = floor($total / $n * 100) / 100;

        foreach (array_values($dias) as $i => $dia) {
            $valor = $i === $n - 1
                ? round($total - $parcelaBase * ($n - 1), 2)
                : $parcelaBase;

            ContaReceber::query()->create([
                'numero' => ContaReceber::nextNumero(),
                'emissao' => $hoje,
                'historico' => 'PEDIDO APP ' . $numeroPedido
                    . ($n > 1 ? ' (' . ($i + 1) . '/' . $n . ')' : ''),
                'documento' => $n > 1 ? $base . '/' . ($i + 1) : $base,
                'cliente_id' => $clienteId,
                'vencimento' => $hoje->copy()->addDays(max(0, $dia)),
                'valor' => $valor,
                'forma' => $forma,
            ]);
        }
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
            str_contains($f, 'cart') => ContaReceber::FORMA_CARTAO,
            default => ContaReceber::FORMA_CARTEIRA,
        };
    }
}
