<?php

namespace App\Support\Fiscal;

/**
 * NT 2024.001 — regras de CSOSN/CFOP para emitente MEI (CRT=4).
 *
 * Rejeição 782: CSOSN inválido para MEI.
 * Rejeição 337: CFOP inválido para MEI (conforme combinação com CSOSN).
 */
final class MeiFiscalNormalizer
{
    public const CRT_MEI = 4;

    public const MODELO_NFE = 55;

    public const MODELO_NFCE = 65;

    /** @var list<string> */
    private const CSOSN_NFE = ['102', '300', '400', '900'];

    /** @var list<string> */
    private const CSOSN_NFCE = ['102', '300'];

    /** @var list<string> */
    private const CFOP_CSOSN_102 = ['5102', '6102'];

    /**
     * CFOPs aceitos com CSOSN 900 para MEI (NF-e) — NT 2024.001 N12a-90.
     *
     * @var list<string>
     */
    private const CFOP_CSOSN_900 = [
        '1202', '1904', '2202', '2904',
        '5202', '5904', '6202', '6904',
        '1501', '1503', '1504', '1505', '1506', '1553',
        '2501', '2503', '2504', '2505', '2506', '2553',
        '5501', '5502', '5504', '5505', '5551', '5933',
        '6501', '6502', '6504', '6505', '6551', '6933',
    ];

    public static function isMeiCrt(int $crt): bool
    {
        return $crt === self::CRT_MEI;
    }

    public static function isMeiRegime(?string $regime): bool
    {
        return in_array(strtolower(trim((string) $regime)), ['mei', 'simei'], true);
    }

    /**
     * Normaliza CSOSN + CFOP do item para MEI.
     *
     * @return array{csosn: string, cfop: string}
     */
    public static function normalizeItem(
        string $csosn,
        string $cfop,
        int $modelo = self::MODELO_NFE,
    ): array {
        $csosnNorm = self::padDigits($csosn, 3) ?: '102';
        $cfopNorm = self::padDigits($cfop, 4) ?: ($modelo === self::MODELO_NFCE ? '5102' : '5102');

        $allowedCsosn = $modelo === self::MODELO_NFCE
            ? self::CSOSN_NFCE
            : self::CSOSN_NFE;

        if (! in_array($csosnNorm, $allowedCsosn, true)) {
            // Devolução / remessa típica de CSOSN 900 (só na NF-e).
            if (
                $modelo === self::MODELO_NFE
                && in_array($cfopNorm, self::CFOP_CSOSN_900, true)
            ) {
                $csosnNorm = '900';
            } else {
                $csosnNorm = '102';
            }
        }

        if ($modelo === self::MODELO_NFCE) {
            // NFC-e MEI: somente CFOP 5102 (N12a-91).
            $cfopNorm = '5102';

            return ['csosn' => $csosnNorm, 'cfop' => $cfopNorm];
        }

        if ($csosnNorm === '102' && ! in_array($cfopNorm, self::CFOP_CSOSN_102, true)) {
            $cfopNorm = str_starts_with($cfopNorm, '6') ? '6102' : '5102';
        }

        if ($csosnNorm === '900' && ! in_array($cfopNorm, self::CFOP_CSOSN_900, true)) {
            // Venda comum com 900 inválido → volta para combinação de venda MEI.
            $csosnNorm = '102';
            $cfopNorm = str_starts_with($cfopNorm, '6') ? '6102' : '5102';
        }

        return ['csosn' => $csosnNorm, 'cfop' => $cfopNorm];
    }

    /**
     * Atalho quando o CRT já é conhecido.
     *
     * @return array{csosn: string, cfop: string}
     */
    public static function normalizeIfMei(
        int $crt,
        string $csosn,
        string $cfop,
        int $modelo = self::MODELO_NFE,
    ): array {
        if (! self::isMeiCrt($crt)) {
            return [
                'csosn' => self::padDigits($csosn, 3) ?: $csosn,
                'cfop' => self::padDigits($cfop, 4) ?: $cfop,
            ];
        }

        return self::normalizeItem($csosn, $cfop, $modelo);
    }

    private static function padDigits(string $value, int $len): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        return str_pad($digits, $len, '0', STR_PAD_LEFT);
    }
}
