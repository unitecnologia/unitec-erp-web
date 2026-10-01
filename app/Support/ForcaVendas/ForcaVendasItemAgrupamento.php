<?php

namespace App\Support\ForcaVendas;

/**
 * Consolidação de quantidade ao agrupar o mesmo produto na Tela de Venda.
 * Desconto/acréscimo da linha são totais; o ajuste unitário da linha existente
 * é reaplicado na nova quantidade.
 */
final class ForcaVendasItemAgrupamento
{
    /**
     * @return array{quantidade: float, desconto: float, acrescimo: float}
     */
    public static function consolidar(
        float $quantidadeAtual,
        float $descontoLinhaAtual,
        float $acrescimoLinhaAtual,
        float $quantidadeAdicionar,
        float $descontoAdicionar,
        float $acrescimoAdicionar,
    ): array {
        $novaQtd = round($quantidadeAtual + $quantidadeAdicionar, 3);

        if ($quantidadeAtual > 0 && ($descontoLinhaAtual > 0 || $acrescimoLinhaAtual > 0)) {
            return [
                'quantidade' => $novaQtd,
                'desconto' => round(($descontoLinhaAtual / $quantidadeAtual) * $novaQtd, 2),
                'acrescimo' => round(($acrescimoLinhaAtual / $quantidadeAtual) * $novaQtd, 2),
            ];
        }

        return [
            'quantidade' => $novaQtd,
            'desconto' => round($descontoLinhaAtual + $descontoAdicionar, 2),
            'acrescimo' => round($acrescimoLinhaAtual + $acrescimoAdicionar, 2),
        ];
    }
}
