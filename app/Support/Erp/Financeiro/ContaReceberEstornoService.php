<?php

namespace App\Support\Erp\Financeiro;

use App\Models\ContaReceber;
use App\Models\ContaReceberPagamento;
use App\Models\PlanoConta;
use App\Support\Erp\Audit\ErpOperacaoLogService;
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

            $snapshotPagamento = [
                'pagamento_id' => (int) $pagamento->id,
                'data' => optional($pagamento->data)?->toDateString(),
                'valor_recebido' => $valorRecebido,
                'juros' => $juros,
                'multa' => $multa,
                'desconto' => $desconto,
                'forma_pagamento_id' => $pagamento->forma_pagamento_id ? (int) $pagamento->forma_pagamento_id : null,
                'caixa_conta_id' => $pagamento->caixa_conta_id ? (int) $pagamento->caixa_conta_id : null,
                'plano_conta_id' => $pagamento->plano_conta_id ? (int) $pagamento->plano_conta_id : null,
            ];

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

            $caixaContaId = $pagamento->caixa_conta_id ? (int) $pagamento->caixa_conta_id : null;

            // Só estorna Livro Caixa se a baixa original movimentou caixa.
            if ($caixaContaId) {
                $planoId = $pagamento->plano_conta_id ? (int) $pagamento->plano_conta_id : null;
                $planoNome = $planoId
                    ? PlanoConta::query()->whereKey($planoId)->value('descricao')
                    : null;

                app(ContaReceberBaixaService::class)->registrarSaidaCaixa(
                    valor: $valorRecebido,
                    data: optional($pagamento->data)?->toDateString() ?? now()->toDateString(),
                    documento: (string) ($conta->documento ?: $conta->numero ?: ('CR-'.$conta->id)),
                    historico: 'Estorno recebimento conta a receber #'.($conta->numero ?: $conta->id),
                    caixaContaId: $caixaContaId,
                    empresaId: $conta->empresa_id ? (int) $conta->empresa_id : null,
                    planoContaId: $planoNome !== null ? $planoId : null,
                    planoNome: $planoNome !== null ? mb_substr(mb_strtoupper((string) $planoNome, 'UTF-8'), 0, 120) : null,
                );
            }

            $pagamento->delete();

            app(ErpOperacaoLogService::class)->registrar(
                operacao: 'ESTORNAR_BAIXA_CONTAS_RECEBER',
                resumo: 'Estornada baixa de CR #'.($conta->numero ?: $conta->id).' (R$ '.number_format($valorRecebido, 2, ',', '.').').',
                origem: 'contas_receber',
                documentoTipo: 'conta_receber',
                documentoId: (int) $conta->id,
                documentoNumero: (string) ($conta->numero ?: $conta->id),
                detalhes: [
                    'conta' => [
                        'id' => (int) $conta->id,
                        'numero' => $conta->numero,
                        'documento' => $conta->documento,
                        'cliente_id' => $conta->cliente_id ? (int) $conta->cliente_id : null,
                        'valor' => (float) $conta->valor,
                    ],
                    'pagamento' => $snapshotPagamento,
                    'movimentou_caixa' => (bool) $caixaContaId,
                ],
                empresaId: $conta->empresa_id ? (int) $conta->empresa_id : null,
            );

            return [
                'ok' => true,
                'valor' => $valorRecebido,
            ];
        });
    }
}
