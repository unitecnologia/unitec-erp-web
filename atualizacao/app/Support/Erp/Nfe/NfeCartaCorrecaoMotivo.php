<?php

namespace App\Support\Erp\Nfe;

final class NfeCartaCorrecaoMotivo
{
    public const MIN_LENGTH = 15;

    public const MAX_LENGTH = 1000;

    public const MAX_SEQUENCIA = 20;

    public const TEXTO_PADRAO = 'Correcao de informacoes complementares da nota fiscal conforme solicitacao do emitente';

    public static function normalize(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }

    public static function validate(string $texto): ?string
    {
        $normalized = self::normalize($texto);
        $length = mb_strlen($normalized, 'UTF-8');

        if ($length < self::MIN_LENGTH) {
            return 'Texto da correção deve ter no mínimo ' . self::MIN_LENGTH . ' caracteres.';
        }

        if ($length > self::MAX_LENGTH) {
            return 'Texto da correção deve ter no máximo ' . self::MAX_LENGTH . ' caracteres.';
        }

        return null;
    }
}
