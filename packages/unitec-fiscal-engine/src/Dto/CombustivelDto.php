<?php

namespace Unitec\FiscalEngine\Dto;

/**
 * Grupo prod/comb (LA) — combustíveis líquidos e GLP.
 * Obrigatórios no XSD quando o grupo é informado: cProdANP, descANP, UFCons.
 */
final class CombustivelDto
{
    public function __construct(
        public readonly string $cProdANP,
        public readonly string $descANP,
        public readonly string $ufCons,
        public readonly ?float $pGLP = null,
        public readonly ?float $pGNn = null,
        public readonly ?float $pGNi = null,
    ) {}
}
