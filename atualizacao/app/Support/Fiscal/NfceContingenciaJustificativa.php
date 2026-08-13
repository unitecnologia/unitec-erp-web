<?php

namespace App\Support\Fiscal;

final class NfceContingenciaJustificativa
{
    public const PADRAO = 'SEFAZ indisponivel para autorizacao da NFC-e em tempo real';

    public static function normalize(?string $motivo): string
    {
        $motivo = trim(preg_replace('/\s+/', ' ', (string) $motivo) ?? '');

        if (mb_strlen($motivo, 'UTF-8') >= 15) {
            return mb_substr($motivo, 0, 255, 'UTF-8');
        }

        return self::PADRAO;
    }
}
