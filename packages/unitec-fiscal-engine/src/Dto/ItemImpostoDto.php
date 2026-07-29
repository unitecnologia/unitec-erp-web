<?php

namespace Unitec\FiscalEngine\Dto;

final class ItemImpostoDto
{
    public function __construct(
        public readonly int $origem,
        public readonly string $csosn,
        public readonly float $vBc = 0.0,
        public readonly float $vIcms = 0.0,
        public readonly float $vPis = 0.0,
        public readonly float $vCofins = 0.0,
        public readonly float $vTotTrib = 0.0,
        public readonly ?string $cstIbsCbs = null,
        public readonly ?string $cClassTrib = null,
        public readonly float $vBcIbscbs = 0.0,
        public readonly float $pIbsUf = 0.0,
        public readonly float $vIbsUf = 0.0,
        public readonly float $pIbsMun = 0.0,
        public readonly float $vIbsMun = 0.0,
        public readonly float $pCbs = 0.0,
        public readonly float $vCbs = 0.0,
        public readonly float $pRedIbs = 0.0,
        public readonly float $pRedCbs = 0.0,
    ) {}

    public function hasIbscbs(): bool
    {
        $cst = trim((string) ($this->cstIbsCbs ?? ''));
        $cClass = trim((string) ($this->cClassTrib ?? ''));

        return $cst !== '' && $cClass !== '';
    }

    public function vIbs(): float
    {
        return round($this->vIbsUf + $this->vIbsMun, 2);
    }
}
