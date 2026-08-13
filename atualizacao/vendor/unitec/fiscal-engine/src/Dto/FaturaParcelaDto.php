<?php

namespace Unitec\FiscalEngine\Dto;

final class FaturaParcelaDto
{
    public function __construct(
        public readonly string $numero,
        public readonly \DateTimeInterface $vencimento,
        public readonly float $valor,
    ) {}
}
