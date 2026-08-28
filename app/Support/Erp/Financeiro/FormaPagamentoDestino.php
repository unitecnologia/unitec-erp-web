<?php

namespace App\Support\Erp\Financeiro;

use App\Models\FormaPagamento;

/**
 * Destino financeiro único a partir de tipo_movimento da forma de pagamento.
 */
final class FormaPagamentoDestino
{
    public static function normalize(?string $movimento): string
    {
        $m = mb_strtolower(trim((string) $movimento), 'UTF-8');

        if ($m === 'ficha_cliente') {
            return 'credito_cliente';
        }

        return array_key_exists($m, FormaPagamento::tipoMovimentoLabels()) ? $m : 'nenhum';
    }

    /**
     * @param  array{tipo_movimento?: string|null}|FormaPagamento|string|null  $source
     */
    public static function from(array|FormaPagamento|string|null $source): string
    {
        if ($source instanceof FormaPagamento) {
            return self::normalize((string) ($source->tipo_movimento ?? ''));
        }

        if (is_array($source)) {
            return self::normalize((string) ($source['tipo_movimento'] ?? ''));
        }

        return self::normalize($source);
    }

    public static function geraContasReceber(array|FormaPagamento|string|null $source): bool
    {
        return self::from($source) === 'contas_receber';
    }

    public static function vaiParaCaixa(array|FormaPagamento|string|null $source): bool
    {
        return self::from($source) === 'caixa';
    }

    public static function vaiParaDeposito(array|FormaPagamento|string|null $source): bool
    {
        return self::from($source) === 'deposito';
    }

    /** Crédito cliente, troca ou nenhum — sem CR e sem caixa. */
    public static function semLancamento(array|FormaPagamento|string|null $source): bool
    {
        return in_array(self::from($source), ['credito_cliente', 'troca', 'nenhum'], true);
    }

    /** Entra no movimento de caixa/sessão PDV (só tipo caixa). */
    public static function lancaNoCaixaSessao(array|FormaPagamento|string|null $source): bool
    {
        return self::vaiParaCaixa($source);
    }
}
