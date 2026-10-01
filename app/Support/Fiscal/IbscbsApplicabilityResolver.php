<?php

namespace App\Support\Fiscal;

use DateTimeInterface;

/**
 * Decide se o emitente deve informar IBS/CBS no XML (NT 2025.002-RTC / LC 214/2025).
 *
 * Regra temporal — não use “MEI nunca gera IBS/CBS”.
 * Em 2026, CRT 1/2/4 não estão obrigados; a partir de 01/01/2027 o bloqueio cessa
 * e valem as regras próprias do Simples/MEI vigentes.
 */
final class IbscbsApplicabilityResolver
{
    /** Início do período em que CRT 1/2/4 não emitem IBS/CBS. */
    public const TRANSICAO_2026_INICIO = '2026-01-01';

    /** Último dia do bloqueio transitório (inclusive). */
    public const TRANSICAO_2026_FIM = '2026-12-31';

    /**
     * @param  int  $crt  Código de Regime Tributário do emitente (1, 2, 3 ou 4)
     */
    public static function deveEmitir(int $crt, DateTimeInterface|string $dataEmissao): bool
    {
        if (! self::estaNoPeriodoTransicao2026($dataEmissao)) {
            return true;
        }

        // CRT 3 (regime normal): emite normalmente em 2026.
        // CRT 1/2/4 (Simples / excesso / MEI): não emitem IBS/CBS em 2026.
        return $crt === 3;
    }

    public static function estaNoPeriodoTransicao2026(DateTimeInterface|string $dataEmissao): bool
    {
        $ymd = self::toYmd($dataEmissao);

        return $ymd >= self::TRANSICAO_2026_INICIO && $ymd <= self::TRANSICAO_2026_FIM;
    }

    private static function toYmd(DateTimeInterface|string $dataEmissao): string
    {
        if ($dataEmissao instanceof DateTimeInterface) {
            return $dataEmissao->format('Y-m-d');
        }

        $raw = trim($dataEmissao);
        if ($raw === '') {
            return '0000-00-00';
        }

        try {
            return (new \DateTimeImmutable($raw))->format('Y-m-d');
        } catch (\Exception) {
            return '0000-00-00';
        }
    }
}
