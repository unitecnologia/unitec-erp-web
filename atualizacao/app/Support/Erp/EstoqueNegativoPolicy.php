<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use App\Models\Product;

/**
 * Política global do parâmetro "Bloquear Estoque Negativo".
 * A baixa física (ProductEstoqueSaldoService) também consulta esta policy.
 */
final class EstoqueNegativoPolicy
{
    public static function ativo(?Empresa $empresa = null): bool
    {
        $empresa ??= ErpContext::currentEmpresa();

        return (bool) ($empresa?->param_geral_bloquear_estoque_negativo ?? false);
    }

    /**
     * Garante que a saída não deixará o saldo negativo (quando o parâmetro estiver ON).
     *
     * @throws \RuntimeException
     */
    public static function garantirSaidaPermitida(
        Product $product,
        float $quantidade,
        ?int $estoqueId = null,
        ?Empresa $empresa = null,
    ): void {
        if ($quantidade <= 0 || $product->is_servico) {
            return;
        }

        if (! self::ativo($empresa)) {
            return;
        }

        $saldo = (new ProductEstoqueSaldoService())->fisico((int) $product->id, $estoqueId);

        if ($saldo < $quantidade) {
            $desc = trim((string) ($product->descricao ?? $product->codigo ?? 'produto'));

            throw new \RuntimeException(
                'Estoque insuficiente para '.$desc
                .' (saldo: '.number_format($saldo, 3, ',', '.')
                .', saída: '.number_format($quantidade, 3, ',', '.').').'
                .' Bloqueio de estoque negativo está ativo.'
            );
        }
    }
}
