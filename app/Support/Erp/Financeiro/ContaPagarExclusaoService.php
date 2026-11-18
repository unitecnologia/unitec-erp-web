<?php

namespace App\Support\Erp\Financeiro;

use App\Models\ContaPagar;
use App\Support\Erp\ErpContext;

/**
 * Regras de exclusão de Contas a Pagar (cadastro manual e vínculos).
 * Não altera baixas/compras: só informa o bloqueio.
 */
final class ContaPagarExclusaoService
{
    public function podeExcluir(ContaPagar $conta): bool
    {
        return $this->motivoBloqueio($conta) === null;
    }

    public function motivoBloqueio(ContaPagar $conta): ?string
    {
        $empresaAtual = ErpContext::currentEmpresaId();
        $empresaConta = $conta->empresa_id !== null ? (int) $conta->empresa_id : null;

        if ($empresaAtual !== null && $empresaConta !== null && $empresaConta !== $empresaAtual) {
            return 'Conta pertence a outra empresa. Troque a empresa logada para excluir.';
        }

        if ((float) $conta->valor_pago > 0) {
            return 'Conta já possui baixa. Estorne a parcela paga antes de excluir.';
        }

        if ($conta->relationLoaded('pagamentos')) {
            if ($conta->pagamentos->isNotEmpty()) {
                return 'Conta possui histórico de pagamentos. Estorne antes de excluir.';
            }
        } elseif ($conta->pagamentos()->exists()) {
            return 'Conta possui histórico de pagamentos. Estorne antes de excluir.';
        }

        $compraId = (int) ($conta->compra_id ?? 0);

        if ($compraId > 0) {
            return 'Conta vinculada à compra #'.$compraId.'. Exclua ou reabra pelo módulo de Compras.';
        }

        return null;
    }
}
