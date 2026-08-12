<?php

namespace Unitec\FiscalEngine\Dto;

final class ItemDto
{
    public function __construct(
        public readonly int $numero,
        public readonly string $codigo,
        public readonly string $descricao,
        public readonly string $ncm,
        public readonly string $cfop,
        public readonly string $unidade,
        public readonly float $quantidade,
        public readonly float $valorUnitario,
        public readonly float $valorTotal,
        public readonly ItemImpostoDto $imposto,
        public readonly float $desconto = 0.0,
        public readonly float $frete = 0.0,
        public readonly float $seguro = 0.0,
        public readonly float $acrescimo = 0.0,
        public readonly ?string $infoAdicionais = null,
    ) {}
}
