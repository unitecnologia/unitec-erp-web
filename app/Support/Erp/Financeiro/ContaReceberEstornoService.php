<?php

namespace App\Support\Erp\Financeiro;

use App\Models\ContaReceber;
use App\Models\ContaReceberPagamento;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Estorno de uma baixa de Contas a Receber + saída no Livro Caixa.
 */
final class ContaReceberEstornoService
{
    /**
     * @return array{ok: bool, valor: float}
     */
    public function estornarPagamento(int $pagamentoId): array
    {
        return DB::transaction(function () use ($pagamentoId): array {
            /** @var ContaReceberPagamento|null $pagamento */
            $pagamento = ContaReceberPagamento::query()
                ->whereKey($pagamentoId)
                ->lockForUpdate()
                ->first();

            if (! $pagamento) {
                throw new InvalidArgumentException('Baixa não encontrada.');
            }

            /** @var ContaReceber|null $conta */
            $conta = ContaReceber::query()
                ->whereKey((int) $pagamento->conta_receber_id)
                ->lockForUpdate()
                ->first();

            if (! $conta) {
                throw new InvalidArgumentException('Título da baixa não encontrado.');
            }

            $valorRecebido = round((float) $pagamento->valor_recebido, 2);
            $juros = round((float) $pagamento->juros, 2);
            $desconto = round((float) $pagamento->desconto, 2);
            $multa = round((float) $pagamento->multa, 2);

            if ($valorRecebido <= 0) {
                throw new InvalidArgumentException('Baixa sem valor recebido para estornar.');
            }

            $conta->valor_recebido = round(max(0, (float) $conta->valor_recebido - $valorRecebido), 2);
            $conta->juros = round(max(0, (float) $conta->juros - $juros), 2);
            $conta->multa = round(max(0, (float) $conta->multa - $multa), 2);
            $conta->desconto = round(max(0, (float) $conta->desconto - $desconto), 2);
            $conta->save();

            $conta->refresh();
            if ((float) $conta->saldo > 0) {
                $conta->recebido_em = null;
                $conta->save();
            }

            app(ContaReceberBaixaService::class)->registrarSaidaCaixa(
                valor: $valorRecebido,
                data: optional($pagamento->data)?->toDateString() ?? now()->toDateString(),
                documento: (string) ($conta->documento ?: $conta->numero ?: ('CR-'.$conta->id)),
                historico: 'Estorno recebimento conta a receber #'.($conta->numero ?: $conta->id),
                caixaContaId: $pagamento->caixa_conta_id ? (int) $pagamento->caixa_conta_id : null,
                empresaId: $conta->empresa_id ? (int) $conta->empresa_id : null,
            );

            $pagamento->delete();

            return [
                'ok' => true,
                'valor' => $valorRecebido,
            ];
        });
    }
}
