<?php

namespace App\Support\Erp\Pdv;

use App\Support\Erp\ErpMoney;

final class PdvFinalizarPagamentosHelper
{
    public static function isFormaAPrazo(string $forma): bool
    {
        $forma = mb_strtoupper(trim($forma), 'UTF-8');

        if (str_contains($forma, 'CHEQUE') || str_contains($forma, 'BOLETO')) {
            return true;
        }

        return str_contains($forma, 'CREDIÁRIO') || str_contains($forma, 'CREDIARIO');
    }

    /**
     * Ao escolher crediário/cheque/boleto, zera as demais formas e concentra o valor na linha escolhida.
     *
     * @param  array<int, array{forma: string, atalho: string, valor: string}>  $pagamentos
     * @return array<int, array{forma: string, atalho: string, valor: string}>
     */
    public static function aplicarFormaPrazoExclusiva(array $pagamentos, int $index, float $totalVenda): array
    {
        if (! isset($pagamentos[$index])) {
            return $pagamentos;
        }

        $total = max(0, round($totalVenda, 2));
        $valorLinha = ErpMoney::parseBr($pagamentos[$index]['valor'] ?? '0');
        $valorFinal = $valorLinha > 0 ? min($valorLinha, $total) : $total;

        foreach ($pagamentos as $i => $pagamento) {
            $pagamentos[$i]['valor'] = $i === $index
                ? ErpMoney::formatBr($valorFinal)
                : '0,00';
        }

        return $pagamentos;
    }
}
