<?php

namespace App\Support\Erp\Financeiro;

use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;

/**
 * Ponto único para o parâmetro Financeiro → "Ignorar financeiro".
 *
 * Desmarcado (padrão): mantém retenção/bloqueio por débitos e pendências do cliente.
 * Marcado: permite faturar sem liberação financeira por inadimplência (não altera títulos).
 */
final class FaturamentoPendenciaFinanceiraGuard
{
    public static function aguardeFinanceiroAtivo(?int $empresaId = null): bool
    {
        return EmpresaParametros::aguardeFinanceiro($empresaId);
    }

    /**
     * Retém pedido em análise financeira (TV / sync app) por limite/débitos.
     */
    public static function deveReterPorPendenciaCliente(?int $empresaId = null): bool
    {
        return ! self::aguardeFinanceiroAtivo($empresaId);
    }

    /**
     * Pedido já em situação "financeiro" pode ser faturado sem liberação manual.
     */
    public static function podeFaturarSemLiberacaoFinanceira(?int $empresaId = null): bool
    {
        return self::aguardeFinanceiroAtivo($empresaId);
    }

    public static function empresaIdOuContexto(?int $empresaId = null): ?int
    {
        $id = (int) ($empresaId ?: ErpContext::currentEmpresaId());

        return $id > 0 ? $id : null;
    }
}
