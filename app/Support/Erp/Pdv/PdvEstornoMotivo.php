<?php

namespace App\Support\Erp\Pdv;

final class PdvEstornoMotivo
{
    /** Exigência SEFAZ para xJust no cancelamento de NFC-e. */
    public const MIN_LENGTH = 15;

    public const MAX_LENGTH = 255;

    public const MOTIVO_AUTOMATICO = 'Venda cancelada por desistência do cliente antes da entrega da mercadoria';

    public static function normalize(string $motivo): string
    {
        return trim(preg_replace('/\s+/', ' ', $motivo) ?? '');
    }

    public static function validate(string $motivo): ?string
    {
        $normalized = self::normalize($motivo);
        $length = mb_strlen($normalized, 'UTF-8');

        if ($length < self::MIN_LENGTH) {
            return 'Motivo do estorno deve ter no mínimo ' . self::MIN_LENGTH . ' caracteres.';
        }

        if ($length > self::MAX_LENGTH) {
            return 'Motivo do estorno deve ter no máximo ' . self::MAX_LENGTH . ' caracteres.';
        }

        return null;
    }
}
