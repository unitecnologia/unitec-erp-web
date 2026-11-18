<?php

namespace App\Support\ForcaVendas;

use App\Support\Erp\DescontoReaisItemCalculator;
use App\Support\Erp\EmpresaParametros;

/**
 * Consolidação de quantidade ao agrupar o mesmo produto na Tela de Venda.
 * Desconto/acréscimo da linha são totais; a reescala depende do modo
 * (por unidade × qtd vs total da linha fixo).
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
        ?string $modoReais = null,
        ?int $empresaId = null,
    ): array {
        return DescontoReaisItemCalculator::consolidarAgrupamento(
            $quantidadeAtual,
            $descontoLinhaAtual,
            $acrescimoLinhaAtual,
            $quantidadeAdicionar,
            $descontoAdicionar,
            $acrescimoAdicionar,
            $modoReais ?? EmpresaParametros::DESCONTO_REAIS_ITEM_UNITARIO,
            $empresaId,
        );
    }
}
