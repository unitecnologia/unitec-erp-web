<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaLancamento;
use App\Models\ContaPagar;
use App\Models\ContaPagarPagamento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Estorno de parcela/baixa de Contas a Pagar + contra-lançamento no Livro Caixa.
 */
final class ContaPagarEstornoService
{
    /**
     * @return array{ok: bool, valor: float}
     */
    public function estornarPagamento(int $pagamentoId): array
    {
        return DB::transaction(function () use ($pagamentoId): array {
            /** @var ContaPagarPagamento|null $pagamento */
            $pagamento = ContaPagarPagamento::query()
                ->with('contaPagar')
                ->whereKey($pagamentoId)
                ->lockForUpdate()
                ->first();

            if (! $pagamento) {
                throw new InvalidArgumentException('Parcela não encontrada.');
            }

            $conta = $pagamento->contaPagar;

            if (! $conta) {
                throw new InvalidArgumentException('Título da parcela não encontrado.');
            }

            /** @var ContaPagar $conta */
            $conta = ContaPagar::query()
                ->whereKey($conta->id)
                ->lockForUpdate()
                ->firstOrFail();

            $valorPago = round((float) $pagamento->valor_pago, 2);
            $juros = round((float) $pagamento->juros, 2);
            $desconto = round((float) $pagamento->desconto, 2);

            if ($valorPago <= 0) {
                throw new InvalidArgumentException('Parcela sem valor pago para estornar.');
            }

            $conta->valor_pago = round(max(0, (float) $conta->valor_pago - $valorPago), 2);
            $conta->juros = round(max(0, (float) $conta->juros - $juros), 2);
            $conta->desconto = round(max(0, (float) $conta->desconto - $desconto), 2);

            // Recalcula saldo no saving(); se ainda houver saldo, limpa pago_em.
            $conta->save();

            $conta->refresh();
            if ((float) $conta->saldo > 0) {
                $conta->pago_em = null;
                $conta->save();
            }

            $this->lancarEntradaEstornoCaixa(
                valor: $valorPago,
                data: optional($pagamento->data)?->toDateString() ?? now()->toDateString(),
                documento: (string) ($conta->documento ?: $conta->numero ?: ('CP-'.$conta->id)),
                historico: 'Estorno pagamento conta a pagar #'.($conta->numero ?: $conta->id),
                caixaContaId: $pagamento->caixa_conta_id ? (int) $pagamento->caixa_conta_id : null,
                planoContaId: $pagamento->plano_conta_id ? (int) $pagamento->plano_conta_id : null,
            );

            $pagamento->delete();

            return [
                'ok' => true,
                'valor' => $valorPago,
            ];
        });
    }

    private function lancarEntradaEstornoCaixa(
        float $valor,
        string $data,
        string $documento,
        string $historico,
        ?int $caixaContaId,
        ?int $planoContaId,
    ): void {
        if (! Schema::hasTable((new CaixaLancamento)->getTable()) || $valor <= 0) {
            return;
        }

        $planoNome = null;
        if ($planoContaId) {
            $planoNome = \App\Models\PlanoConta::query()->whereKey($planoContaId)->value('descricao');
        }

        CaixaLancamento::query()->create([
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => $data,
            'documento' => mb_substr($documento, 0, 40),
            'historico' => mb_substr($historico, 0, 180),
            'plano_contas' => $planoNome ? mb_substr(mb_strtoupper((string) $planoNome, 'UTF-8'), 0, 80) : null,
            'plano_conta_id' => $planoContaId,
            'caixa_conta_id' => $caixaContaId,
            'entrada' => $valor,
            'saida' => 0,
        ]);
    }
}
