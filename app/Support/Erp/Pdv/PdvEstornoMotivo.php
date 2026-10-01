<?php

namespace App\Support\Erp\Pdv;

final class PdvEstornoMotivo
{
    /** Exigência SEFAZ para xJust no cancelamento de NFC-e. */
    public const MIN_LENGTH = 15;

    /** Cancelamento no Monitor FV (sem SEFAZ neste passo). */
    public const MIN_LENGTH_MONITOR_FV = 5;

    public const MAX_LENGTH = 255;

    public const MOTIVO_AUTOMATICO = 'Venda cancelada por desistência do cliente antes da entrega da mercadoria';

    public static function normalize(string $motivo): string
    {
        return trim(preg_replace('/\s+/', ' ', $motivo) ?? '');
    }

    public static function validate(string $motivo, int $minLength = self::MIN_LENGTH): ?string
    {
        $normalized = self::normalize($motivo);
        $length = mb_strlen($normalized, 'UTF-8');
        $min = max(1, $minLength);

        if ($length < $min) {
            return 'Motivo do cancelamento deve ter no mínimo '.$min.' caracteres.';
        }

        if ($length > self::MAX_LENGTH) {
            return 'Motivo do cancelamento deve ter no máximo '.self::MAX_LENGTH.' caracteres.';
        }

        return null;
    }
}
