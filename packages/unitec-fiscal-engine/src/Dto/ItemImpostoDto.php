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
        public readonly ?string $cstIcms = null,
        public readonly float $pIcms = 0.0,
        public readonly float $pPis = 0.0,
        public readonly float $vBcPis = 0.0,
        public readonly ?string $cstPis = null,
        public readonly float $pCofins = 0.0,
        public readonly float $vBcCofins = 0.0,
        public readonly ?string $cstCofins = null,
        public readonly float $vIpi = 0.0,
        public readonly float $pIpi = 0.0,
        public readonly float $vBcIpi = 0.0,
        public readonly ?string $cstIpi = null,
        public readonly float $vIcmsDeson = 0.0,
        public readonly ?string $motivoDesoneracao = null,
        public readonly int $crt = 1,
    ) {}

    public function usesSimples(): bool
    {
        return in_array($this->crt, [1, 4], true);
    }

    public function hasIcmsDetalhe(): bool
    {
        return $this->vBc > 0 || $this->vIcms > 0 || $this->pIcms > 0;
    }

    public function hasIpi(): bool
    {
        return $this->vIpi > 0 || $this->pIpi > 0;
    }

    public function hasPisTributado(): bool
    {
        if ($this->vPis > 0) {
            return true;
        }

        return $this->pPis > 0 && $this->vBcPis > 0;
    }

    public function hasCofinsTributado(): bool
    {
        if ($this->vCofins > 0) {
            return true;
        }

        return $this->pCofins > 0 && $this->vBcCofins > 0;
    }

    public function cstPisResolvido(): string
    {
        if (! $this->hasPisTributado()) {
            return $this->normalizeCst($this->cstPis ?? '07') ?: '07';
        }

        $cst = $this->normalizeCst($this->cstPis ?? '01') ?: '01';

        return in_array($cst, ['01', '02'], true) ? $cst : '01';
    }

    public function cstCofinsResolvido(): string
    {
        if (! $this->hasCofinsTributado()) {
            return $this->normalizeCst($this->cstCofins ?? '07') ?: '07';
        }

        $cst = $this->normalizeCst($this->cstCofins ?? '01') ?: '01';

        return in_array($cst, ['01', '02'], true) ? $cst : '01';
    }

    public function csosnResolvido(): string
    {
        $csosn = str_pad(preg_replace('/\D/', '', $this->csosn) ?? '', 3, '0', STR_PAD_LEFT) ?: '102';

        if ($this->hasIcmsDetalhe() && in_array($csosn, ['102', '103', '300', '400', '500'], true)) {
            return '900';
        }

        return $csosn;
    }

    public function cstIcmsResolvido(): string
    {
        $cst = $this->normalizeCst($this->cstIcms ?? '00');

        return $cst !== '' ? $cst : '00';
    }

    private function normalizeCst(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        return str_pad(substr($digits, 0, 2), 2, '0', STR_PAD_LEFT);
    }

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
