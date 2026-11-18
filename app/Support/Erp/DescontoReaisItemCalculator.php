<?php

namespace App\Support\Erp;

/**
 * Interpretação do desconto/acréscimo em R$ digitado no item,
 * conforme empresas.param_monitor_vendas_desconto_reais_item_modo.
 *
 * Persistência comercial nas telas de pedido/orçamento: sempre total da linha.
 * O parâmetro só define como converter o valor digitado (e reescalar na qtd).
 */
final class DescontoReaisItemCalculator
{
    /**
     * @return array{base: float, deltaUnit: float, ajusteLinha: float, novoPreco: float, total: float}
     */
    public static function calcular(
        float $base,
        float $quantidade,
        float $valorInformado,
        string $tipo,
        string $modoEntrada,
        ?string $modoReais = null,
        ?int $empresaId = null,
    ): array {
        $base = round(max(0, $base), 2);
        $qtd = max(0.0, $quantidade);
        $valor = max(0.0, round($valorInformado, 2));
        $ehAcrescimo = $tipo === 'acrescimo';
        $modoReais = EmpresaParametros::normalizarDescontoReaisItemModo(
            $modoReais ?? EmpresaParametros::descontoReaisItemModo($empresaId),
        );

        if ($modoEntrada === 'percentual') {
            $deltaUnit = round($base * ($valor / 100), 2);
            $ajusteLinha = round($deltaUnit * $qtd, 2);
        } elseif ($modoReais === EmpresaParametros::DESCONTO_REAIS_ITEM_LINHA) {
            $ajusteLinha = $valor;
            $deltaUnit = $qtd > 0 ? round($ajusteLinha / $qtd, 4) : $valor;
        } else {
            $deltaUnit = $valor;
            $ajusteLinha = round($deltaUnit * $qtd, 2);
        }

        $novoPreco = $ehAcrescimo
            ? round($base + $deltaUnit, 2)
            : round($base - $deltaUnit, 2);

        if ($novoPreco < 0) {
            $novoPreco = 0.0;
        }

        $bruto = round($qtd * $base, 2);
        $total = $ehAcrescimo
            ? round($bruto + $ajusteLinha, 2)
            : round(max(0, $bruto - $ajusteLinha), 2);

        return [
            'base' => $base,
            'deltaUnit' => abs($deltaUnit),
            'ajusteLinha' => abs($ajusteLinha),
            'novoPreco' => $novoPreco,
            'total' => $total,
        ];
    }

    /**
     * Reaplica o ajuste de linha quando a quantidade muda.
     * Por unidade: mantém o unitário e recalcula o total da linha.
     * Total da linha: mantém o valor absoluto informado.
     */
    public static function reescalarAjusteLinha(
        float $ajusteLinhaAtual,
        float $quantidadeAtual,
        float $quantidadeNova,
        ?string $modoReais = null,
        ?int $empresaId = null,
    ): float {
        $atual = max(0.0, round($ajusteLinhaAtual, 2));
        $qtdAtual = max(0.0, $quantidadeAtual);
        $qtdNova = max(0.0, $quantidadeNova);
        $modoReais = EmpresaParametros::normalizarDescontoReaisItemModo(
            $modoReais ?? EmpresaParametros::descontoReaisItemModo($empresaId),
        );

        if ($modoReais === EmpresaParametros::DESCONTO_REAIS_ITEM_LINHA || $qtdAtual <= 0) {
            return $atual;
        }

        return round(($atual / $qtdAtual) * $qtdNova, 2);
    }

    /**
     * Consolidação ao agrupar linhas iguais.
     *
     * @return array{quantidade: float, desconto: float, acrescimo: float}
     */
    public static function consolidarAgrupamento(
        float $quantidadeAtual,
        float $descontoLinhaAtual,
        float $acrescimoLinhaAtual,
        float $quantidadeAdicionar,
        float $descontoAdicionar,
        float $acrescimoAdicionar,
        ?string $modoReais = null,
        ?int $empresaId = null,
    ): array {
        $novaQtd = round($quantidadeAtual + $quantidadeAdicionar, 3);
        $modoReais = EmpresaParametros::normalizarDescontoReaisItemModo(
            $modoReais ?? EmpresaParametros::descontoReaisItemModo($empresaId),
        );

        if ($modoReais === EmpresaParametros::DESCONTO_REAIS_ITEM_LINHA) {
            // Total da linha: não reescala o desconto existente; soma apenas o que a nova entrada trouxer.
            return [
                'quantidade' => $novaQtd,
                'desconto' => round(max(0, $descontoLinhaAtual) + max(0, $descontoAdicionar), 2),
                'acrescimo' => round(max(0, $acrescimoLinhaAtual) + max(0, $acrescimoAdicionar), 2),
            ];
        }

        if ($quantidadeAtual > 0 && ($descontoLinhaAtual > 0 || $acrescimoLinhaAtual > 0)) {
            return [
                'quantidade' => $novaQtd,
                'desconto' => round(($descontoLinhaAtual / $quantidadeAtual) * $novaQtd, 2),
                'acrescimo' => round(($acrescimoLinhaAtual / $quantidadeAtual) * $novaQtd, 2),
            ];
        }

        return [
            'quantidade' => $novaQtd,
            'desconto' => round(max(0, $descontoLinhaAtual) + max(0, $descontoAdicionar), 2),
            'acrescimo' => round(max(0, $acrescimoLinhaAtual) + max(0, $acrescimoAdicionar), 2),
        ];
    }
}
