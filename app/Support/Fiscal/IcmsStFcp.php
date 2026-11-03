<?php

namespace App\Support\Fiscal;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Classificação e validação de ICMS-ST / FCP para NF-e 55.
 * Não inventa MVA/alíquotas; CEST não implica ST.
 * DIFAL/ICMSUFDest fica fora deste módulo.
 */
final class IcmsStFcp
{
    public const MODO_NENHUM = 'nenhum';

    /** ST cobrada nesta operação (entra em vBCST/vST / vFCPST). */
    public const MODO_COBRADA = 'cobrada';

    /** ST retida anteriormente (informativo; NÃO entra em vST). */
    public const MODO_RETIDA = 'retida';

    /** @var list<string> */
    private const CST_COBRADA = ['10', '30', '70'];

    /** @var list<string> */
    private const CSOSN_COBRADA = ['201', '202', '203'];

    public static function normalizarCst(?string $raw): string
    {
        $digits = preg_replace('/\D/', '', (string) $raw) ?? '';

        return $digits === '' ? '' : str_pad(substr($digits, 0, 2), 2, '0', STR_PAD_LEFT);
    }

    public static function normalizarCsosn(?string $raw): string
    {
        $digits = preg_replace('/\D/', '', (string) $raw) ?? '';

        return $digits === '' ? '' : str_pad(substr($digits, 0, 3), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  bool  $temValoresStCobrada  true se o item já traz BC/valor de ST da operação
     */
    public static function modo(
        int $crt,
        ?string $cstIcms,
        ?string $csosn,
        bool $temValoresStCobrada = false,
    ): string {
        $cst = self::normalizarCst($cstIcms);
        $csosnN = self::normalizarCsosn($csosn);

        if (in_array($crt, [1, 4], true)) {
            if ($csosnN === '500') {
                return self::MODO_RETIDA;
            }
            if (in_array($csosnN, self::CSOSN_COBRADA, true)) {
                return self::MODO_COBRADA;
            }

            return self::MODO_NENHUM;
        }

        // CRT 2 e 3: CST.
        if ($cst === '60') {
            return self::MODO_RETIDA;
        }
        if (in_array($cst, self::CST_COBRADA, true)) {
            return self::MODO_COBRADA;
        }
        // CST 90: ST só quando houver valores de ST cobrada informados.
        if ($cst === '90' && $temValoresStCobrada) {
            return self::MODO_COBRADA;
        }

        return self::MODO_NENHUM;
    }

    public static function exigeStCobradaCompleta(string $modo, ?string $cstIcms, ?string $csosn): bool
    {
        if ($modo !== self::MODO_COBRADA) {
            return false;
        }

        $cst = self::normalizarCst($cstIcms);
        $csosnN = self::normalizarCsosn($csosn);

        // 10/30/70 e CSOSN 201–203 exigem ST completa. 90 só se modo cobrada (já com valores).
        return in_array($cst, self::CST_COBRADA, true)
            || in_array($csosnN, self::CSOSN_COBRADA, true);
    }

    /** UI: campos de ST cobrada (base/alíq/valor ST, MVA, FCP-ST). */
    public static function uiStCobrada(int $crt, ?string $cstIcms, ?string $csosn): bool
    {
        $modo = self::modo($crt, $cstIcms, $csosn, temValoresStCobrada: true);

        return $modo === self::MODO_COBRADA
            || in_array(self::normalizarCst($cstIcms), [...self::CST_COBRADA, '90'], true)
            || in_array(self::normalizarCsosn($csosn), self::CSOSN_COBRADA, true);
    }

    /** UI: campos de ST retida (vBCSTRet / vICMSSTRet / FCP-ST ret). */
    public static function uiStRetida(int $crt, ?string $cstIcms, ?string $csosn): bool
    {
        return self::modo($crt, $cstIcms, $csosn) === self::MODO_RETIDA
            || self::normalizarCst($cstIcms) === '60'
            || self::normalizarCsosn($csosn) === '500';
    }

    /** UI: FCP próprio (grupos com ICMS próprio). */
    public static function uiFcpProprio(int $crt, ?string $cstIcms, ?string $csosn): bool
    {
        if (in_array($crt, [1, 4], true)) {
            $csosnN = self::normalizarCsosn($csosn);

            return in_array($csosnN, ['201', '202', '203', '900'], true);
        }

        $cst = self::normalizarCst($cstIcms);

        return in_array($cst, ['00', '10', '20', '70', '90'], true);
    }

    /**
     * Valida antes da emissão. Não altera dados.
     *
     * @param  array{
     *   v_bc_st?: float, p_icms_st?: float, v_icms_st?: float,
     *   v_bc_st_ret?: float, v_icms_st_ret?: float,
     *   v_fcp?: float, p_fcp?: float, v_bc_fcp?: float,
     *   v_fcp_st?: float, p_fcp_st?: float, v_bc_fcp_st?: float,
     *   v_fcp_st_ret?: float, p_fcp_st_ret?: float, v_bc_fcp_st_ret?: float
     * }  $valores
     */
    public static function assertParaEmissao(
        string $modo,
        ?string $cstIcms,
        ?string $csosn,
        array $valores,
        string $prefixo = '',
    ): void {
        $vBcSt = (float) ($valores['v_bc_st'] ?? 0);
        $pIcmsSt = (float) ($valores['p_icms_st'] ?? 0);
        $vIcmsSt = (float) ($valores['v_icms_st'] ?? 0);
        $vBcStRet = (float) ($valores['v_bc_st_ret'] ?? 0);
        $vIcmsStRet = (float) ($valores['v_icms_st_ret'] ?? 0);

        if (self::exigeStCobradaCompleta($modo, $cstIcms, $csosn)) {
            if ($vBcSt <= 0 || $pIcmsSt <= 0 || $vIcmsSt <= 0) {
                throw new FiscalEngineException(
                    $prefixo.'CST/CSOSN exige ICMS-ST na operação, mas faltam base, alíquota ou valor de ST. '
                    .'Informe base_icms_st, aliq_icms_st e valor_icms_st (não inventamos MVA/alíquota; CEST não gera ST).',
                );
            }
        }

        if ($modo === self::MODO_COBRADA && ($vBcSt > 0 || $vIcmsSt > 0 || $pIcmsSt > 0)) {
            if ($vBcSt <= 0 || $pIcmsSt <= 0 || $vIcmsSt <= 0) {
                throw new FiscalEngineException(
                    $prefixo.'ICMS-ST parcialmente informado. Preencha base, alíquota e valor de ST da operação.',
                );
            }
            $esperado = round($vBcSt * $pIcmsSt / 100, 2);
            if (abs($esperado - round($vIcmsSt, 2)) > 0.05) {
                throw new FiscalEngineException(
                    $prefixo.'ICMS-ST inconsistente: vBCST×pICMSST ≈ '.$esperado
                    .' mas vICMSST='.round($vIcmsSt, 2).'.',
                );
            }
        }

        if ($modo === self::MODO_RETIDA) {
            if ($vBcStRet <= 0 && $vIcmsStRet <= 0) {
                throw new FiscalEngineException(
                    $prefixo.'CST 60 / CSOSN 500 exige ST retida anteriormente (vBCSTRet / vICMSSTRet). '
                    .'Valores apenas informativos — não entram em vST da nota.',
                );
            }
        }

        // FCP próprio: se há valor, exige alíquota (ou BC+alíquota).
        $vFcp = (float) ($valores['v_fcp'] ?? 0);
        $pFcp = (float) ($valores['p_fcp'] ?? 0);
        if ($vFcp > 0 && $pFcp <= 0) {
            throw new FiscalEngineException(
                $prefixo.'FCP próprio (vFCP) informado sem alíquota (pFCP). Cadastre % FCP no item/produto.',
            );
        }

        $vFcpSt = (float) ($valores['v_fcp_st'] ?? 0);
        $pFcpSt = (float) ($valores['p_fcp_st'] ?? 0);
        if ($vFcpSt > 0 && ($pFcpSt <= 0 || $modo !== self::MODO_COBRADA)) {
            throw new FiscalEngineException(
                $prefixo.'FCP-ST exige ST cobrada na operação e alíquota pFCPST.',
            );
        }

        $vFcpStRet = (float) ($valores['v_fcp_st_ret'] ?? 0);
        if ($vFcpStRet > 0 && $modo !== self::MODO_RETIDA) {
            throw new FiscalEngineException(
                $prefixo.'FCP-ST retido só se aplica com ST anteriormente retida (CST 60 / CSOSN 500).',
            );
        }

        // ST cobrada não pode coexistir com retida no mesmo item.
        if ($vIcmsSt > 0 && $vIcmsStRet > 0) {
            throw new FiscalEngineException(
                $prefixo.'Item não pode ter ST cobrada e ST retida ao mesmo tempo. Separe a natureza fiscal.',
            );
        }
    }
}
