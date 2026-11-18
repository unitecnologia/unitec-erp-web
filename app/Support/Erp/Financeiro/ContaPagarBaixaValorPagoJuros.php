<?php

namespace App\Support\Erp\Financeiro;

/**
 * Inverso: só o excesso do valor pago sobre o valor a pagar (já com juros/multa padrão)
 * aumenta os juros. Pagamento menor não reduz juros (baixa parcial).
 */
final class ContaPagarBaixaValorPagoJuros
{
    /**
     * @return array{aplicar: bool, juros: float, valor_a_pagar: float}
     */
    public static function absorverExcesso(
        float $saldo,
        float $jurosAtual,
        float $multa,
        float $desconto,
        float $valorPago,
    ): array {
        $saldo = round($saldo, 2);
        $jurosAtual = round($jurosAtual, 2);
        $multa = round($multa, 2);
        $desconto = round($desconto, 2);
        $valorPago = round($valorPago, 2);
        $valorAPagar = round(max(0, $saldo + $jurosAtual + $multa - $desconto), 2);

        if ($valorPago <= $valorAPagar + 0.009) {
            return [
                'aplicar' => false,
                'juros' => $jurosAtual,
                'valor_a_pagar' => $valorAPagar,
            ];
        }

        $juros = round($valorPago - $saldo - $multa + $desconto, 2);
        if ($juros < 0) {
            $juros = 0.0;
        }

        return [
            'aplicar' => true,
            'juros' => $juros,
            'valor_a_pagar' => $valorPago,
        ];
    }
}
