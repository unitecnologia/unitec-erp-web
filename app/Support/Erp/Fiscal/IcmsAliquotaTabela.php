<?php

namespace App\Support\Erp\Fiscal;

/**
 * Matriz oficial de alíquotas ICMS (interna × interestadual) — padrão 2026.
 * Usada no seed do sistema e como referência para consultas.
 */
final class IcmsAliquotaTabela
{
    /**
     * Ordem das UFs conforme tabela oficial 2026.
     *
     * @return list<string>
     */
    public static function ufs(): array
    {
        return [
            'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
            'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RN', 'RS',
            'RJ', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
        ];
    }

    /**
     * Alíquotas internas (diagonal) por UF — tabela 2026.
     *
     * @return array<string, float>
     */
    public static function aliquotasInternas(): array
    {
        return [
            'ES' => 17.0,
            'MT' => 17.0,
            'MS' => 17.0,
            'RS' => 17.0,
            'SC' => 17.0,
            'AP' => 18.0,
            'MG' => 18.0,
            'SP' => 18.0,
            'AC' => 19.0,
            'GO' => 19.0,
            'PA' => 19.0,
            'PR' => 19.5,
            'RO' => 19.5,
            'AM' => 20.0,
            'CE' => 20.0,
            'DF' => 20.0,
            'PB' => 20.0,
            'RN' => 20.0,
            'RR' => 20.0,
            'SE' => 20.0,
            'TO' => 20.0,
            'BA' => 20.5,
            'PE' => 20.5,
            'AL' => 21.5,
            'RJ' => 22.0,
            'PI' => 22.5,
            'MA' => 23.0,
        ];
    }

    /**
     * Origens Sul/Sudeste (exceto ES): 7% para N/NE/CO+ES; 12% para demais S/SE.
     *
     * @return list<string>
     */
    public static function origensSetePorcento(): array
    {
        return ['MG', 'PR', 'RS', 'RJ', 'SC', 'SP'];
    }

    /**
     * Destinos que recebem 7% das origens Sul/Sudeste (exceto ES).
     *
     * @return list<string>
     */
    public static function destinosSetePorcento(): array
    {
        return [
            // Norte
            'AC', 'AP', 'AM', 'PA', 'RO', 'RR', 'TO',
            // Nordeste
            'AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE',
            // Centro-Oeste
            'DF', 'GO', 'MT', 'MS',
            // ES (exceção do Sudeste)
            'ES',
        ];
    }

    public static function aliquotaPadrao(string $ufOrigem, string $ufDestino): float
    {
        $origem = strtoupper($ufOrigem);
        $destino = strtoupper($ufDestino);

        if ($origem === $destino) {
            return self::aliquotasInternas()[$origem] ?? 18.0;
        }

        if (in_array($origem, self::origensSetePorcento(), true)
            && in_array($destino, self::destinosSetePorcento(), true)) {
            return 7.0;
        }

        return 12.0;
    }

    /**
     * Matriz completa 27×27 com valores padrão 2026.
     *
     * @return array<string, array<string, float>>
     */
    public static function matrizPadrao(): array
    {
        $matriz = [];

        foreach (self::ufs() as $origem) {
            foreach (self::ufs() as $destino) {
                $matriz[$origem][$destino] = self::aliquotaPadrao($origem, $destino);
            }
        }

        return $matriz;
    }
}
