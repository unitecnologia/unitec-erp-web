<?php

namespace App\Support\Erp\Orcamento;

/**
 * Numeração visual da coluna # (somente produtos).
 * Lista na ordem da grade: mais recente no topo; o 1 fica no produto mais antigo.
 */
final class OrcamentoItemDisplayNumbers
{
    /**
     * @param  array<int, bool>  $isSeparadorByIndex
     * @return array<int, int|null>
     */
    public static function fromFlags(array $isSeparadorByIndex): array
    {
        $count = count($isSeparadorByIndex);
        $nums = array_fill(0, $count, null);
        $n = 0;

        for ($i = $count - 1; $i >= 0; $i--) {
            if (! empty($isSeparadorByIndex[$i])) {
                continue;
            }

            $n++;
            $nums[$i] = $n;
        }

        return $nums;
    }

    /**
     * @param  array<int, array<string, mixed>>  $itens
     * @return array<int, int|null>
     */
    public static function fromRows(array $itens): array
    {
        $flags = [];

        foreach ($itens as $index => $row) {
            $flags[$index] = (bool) ($row['is_separador'] ?? false);
        }

        return self::fromFlags($flags);
    }
}
