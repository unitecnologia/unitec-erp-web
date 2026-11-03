<?php

namespace App\Support\Fiscal;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * CRT do emitente a partir de empresas.regime_tributario.
 * Presumido / Lucro Real / Normal → 3 (nunca cair em CRT 1 por slug alternativo).
 */
final class RegimeCrtMapper
{
    /**
     * Aliases aceitos → slug canônico armazenável.
     *
     * @var array<string, string>
     */
    private const NORMALIZACAO = [
        'simples' => 'simples',
        'excesso_sublimite' => 'excesso_sublimite',
        'excesso' => 'excesso_sublimite',
        'simples_excesso' => 'excesso_sublimite',
        'presumido' => 'presumido',
        'lucro_presumido' => 'presumido',
        'real' => 'real',
        'lucro_real' => 'real',
        'normal' => 'normal',
        'mei' => 'mei',
        'simei' => 'mei',
    ];

    public static function normalize(?string $regime): string
    {
        $key = strtolower(trim((string) $regime));

        if ($key === '') {
            return 'simples';
        }

        return self::NORMALIZACAO[$key] ?? $key;
    }

    /**
     * @param  bool  $strict  Na emissão: regime desconhecido lança exceção (não vira CRT 1).
     */
    public static function toCrt(?string $regime, bool $strict = true): int
    {
        $raw = trim((string) $regime);
        $key = strtolower($raw);

        if ($key === '') {
            return 1;
        }

        if (! array_key_exists($key, self::NORMALIZACAO)) {
            if ($strict) {
                throw new FiscalEngineException(
                    'Regime tributário "'.$raw.'" não reconhecido. '
                    .'Cadastre Simples, Presumido, Real, Normal ou MEI na empresa.',
                );
            }

            return 1;
        }

        return match (self::NORMALIZACAO[$key]) {
            'simples' => 1,
            'excesso_sublimite' => 2,
            'presumido', 'real', 'normal' => 3,
            'mei' => 4,
        };
    }

    /**
     * CRT 3 = Regime Normal (Presumido/Real/Normal).
     * CRT 2 (Simples com excesso de sublimite) NÃO entra aqui.
     */
    public static function isRegimeNormal(int $crt): bool
    {
        return $crt === 3;
    }

    /** ICMS com CSOSN (CRT 1 Simples e CRT 4 MEI). */
    public static function usaIcmsCsosn(int $crt): bool
    {
        return in_array($crt, [1, 4], true);
    }

    /**
     * PIS/COFINS no tratamento Simples (sem alíquotas de Lucro Presumido):
     * CRT 1, 2 (excesso sublimite) e 4.
     */
    public static function usaPisCofinsSimples(int $crt): bool
    {
        return in_array($crt, [1, 2, 4], true);
    }
}
