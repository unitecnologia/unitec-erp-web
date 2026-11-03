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
        /** NT 2023.004: 0=não deduz vNF, 1=deduz, null=não informado (não deduz). */
        public readonly ?int $indDeduzDeson = null,
        public readonly int $crt = 1,
        public readonly float $pRedBc = 0.0,
        public readonly ?string $modBc = null,
        // ICMS-ST cobrada na operação (entra em vBCST/vST).
        public readonly float $vBcSt = 0.0,
        public readonly float $pIcmsSt = 0.0,
        public readonly float $vIcmsSt = 0.0,
        public readonly float $pMvaSt = 0.0,
        public readonly float $pRedBcSt = 0.0,
        public readonly ?string $modBcSt = null,
        // FCP próprio (vFCP).
        public readonly float $vBcFcp = 0.0,
        public readonly float $pFcp = 0.0,
        public readonly float $vFcp = 0.0,
        // FCP-ST da operação (vFCPST).
        public readonly float $vBcFcpSt = 0.0,
        public readonly float $pFcpSt = 0.0,
        public readonly float $vFcpSt = 0.0,
        // ST retida anteriormente (informativo — NÃO entra em vST).
        public readonly float $vBcStRet = 0.0,
        public readonly float $pStRet = 0.0,
        public readonly float $vIcmsSubstituto = 0.0,
        public readonly float $vIcmsStRet = 0.0,
        // FCP-ST retido (vFCPSTRet).
        public readonly float $vBcFcpStRet = 0.0,
        public readonly float $pFcpStRet = 0.0,
        public readonly float $vFcpStRet = 0.0,
        // ICMSUFDest (DIFAL) — NA01; NÃO entra em vNF (W16-10).
        public readonly float $vBcUfDest = 0.0,
        public readonly float $vBcFcpUfDest = 0.0,
        public readonly float $pFcpUfDest = 0.0,
        public readonly float $pIcmsUfDest = 0.0,
        public readonly float $pIcmsInter = 0.0,
        public readonly float $pIcmsInterPart = 0.0,
        public readonly float $vFcpUfDest = 0.0,
        public readonly float $vIcmsUfDest = 0.0,
        public readonly float $vIcmsUfRemet = 0.0,
    ) {}

    public function hasStCobrada(): bool
    {
        return $this->vBcSt > 0 || $this->vIcmsSt > 0 || $this->pIcmsSt > 0;
    }

    public function hasStRetida(): bool
    {
        return $this->vBcStRet > 0 || $this->vIcmsStRet > 0;
    }

    public function hasFcpProprio(): bool
    {
        return $this->vFcp > 0 || ($this->pFcp > 0 && $this->vBcFcp > 0);
    }

    public function hasFcpSt(): bool
    {
        return $this->vFcpSt > 0 || ($this->pFcpSt > 0 && $this->vBcFcpSt > 0);
    }

    public function hasFcpStRet(): bool
    {
        return $this->vFcpStRet > 0 || ($this->pFcpStRet > 0 && $this->vBcFcpStRet > 0);
    }

    /** Grupo ICMSUFDest exigível / preenchido (interestadual CF não contribuinte). */
    public function hasIcmsUfDest(): bool
    {
        return $this->vBcUfDest > 0
            && $this->pIcmsUfDest > 0
            && $this->pIcmsInter > 0
            && $this->pIcmsInterPart > 0;
    }

    /**
     * ICMS no XML: CSOSN (ICMSSN*) só para CRT 1 e 4.
     * CRT 2 (excesso sublimite) e CRT 3 usam CST (ICMS00/20/…).
     */
    public function usesSimples(): bool
    {
        return in_array($this->crt, [1, 4], true);
    }

    /**
     * PIS/COFINS no estilo Simples (sem alíquotas de Lucro Presumido):
     * CRT 1, 2 e 4 — CST vem do produto/operação; não inventa 07.
     */
    public function usesPisCofinsSimples(): bool
    {
        return in_array($this->crt, [1, 2, 4], true);
    }

    public function hasIcmsDetalhe(): bool
    {
        return $this->vBc > 0 || $this->vIcms > 0 || $this->pIcms > 0;
    }

    public function hasIpi(): bool
    {
        return $this->vIpi > 0 || $this->pIpi > 0;
    }

    /**
     * NT 2023.004 v1.11 / W16-10 Exceção 4:
     * só abate vICMSDeson do vNF quando indDeduzDeson = 1.
     * Ausente ou 0 → informa vICMSDeson no ICMSTot sem reduzir vNF.
     * motDesICMS=7 (SUFRAMA): o builder/factory forçam indDeduzDeson=1.
     */
    public function deduzIcmsDesonDoTotal(): bool
    {
        return $this->vIcmsDeson > 0 && $this->indDeduzDeson === 1;
    }

    public function hasPisTributado(): bool
    {
        // CST 01/02 → sempre grupo PISAliq (inclui alíquota zero), nunca forçar PISNT 07.
        $explicit = $this->normalizeCst($this->cstPis ?? '');
        if (in_array($explicit, ['01', '02'], true)) {
            return true;
        }

        if ($this->vPis > 0) {
            return true;
        }

        return $this->pPis > 0 && $this->vBcPis > 0;
    }

    public function hasCofinsTributado(): bool
    {
        $explicit = $this->normalizeCst($this->cstCofins ?? '');
        if (in_array($explicit, ['01', '02'], true)) {
            return true;
        }

        if ($this->vCofins > 0) {
            return true;
        }

        return $this->pCofins > 0 && $this->vBcCofins > 0;
    }

    public function cstPisResolvido(): string
    {
        // Nunca inventa 01/07: só o CST explícito do item/produto.
        return $this->normalizeCst($this->cstPis ?? '');
    }

    public function cstCofinsResolvido(): string
    {
        return $this->normalizeCst($this->cstCofins ?? '');
    }

    public function csosnResolvido(): string
    {
        $csosn = str_pad(preg_replace('/\D/', '', $this->csosn) ?? '', 3, '0', STR_PAD_LEFT) ?: '102';

        // 500 = ST retida; 201–203 = ST cobrada — não forçar 900.
        if ($this->hasIcmsDetalhe() && in_array($csosn, ['102', '103', '300', '400'], true)) {
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
