<?php

declare(strict_types=1);

namespace App\Support\Pix\Ailos;

use InvalidArgumentException;

/**
 * Normaliza o Copia e Cola Ailos (BR Code puro ou Base64) e valida CRC16/GUI Pix.
 */
final class AilosBrCode
{
    private const PAYLOAD_PREFIX = '000201';

    private const PIX_GUI = 'BR.GOV.BCB.PIX';

    public static function normalize(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Ailos não retornou o código Pix Copia e Cola.');
        }

        $raw = trim($value);
        if (self::isValid($raw)) {
            return $raw;
        }

        $decoded = self::decodeStrictBase64($raw);
        if ($decoded !== null && self::isValid($decoded)) {
            return $decoded;
        }

        throw new InvalidArgumentException('Ailos retornou um código Pix Copia e Cola inválido.');
    }

    private static function isValid(string $value): bool
    {
        if (! str_starts_with($value, self::PAYLOAD_PREFIX)) {
            return false;
        }

        if (stripos($value, self::PIX_GUI) === false) {
            return false;
        }

        if (! preg_match('/6304([0-9A-F]{4})$/i', $value, $match)) {
            return false;
        }

        $withoutCrc = substr($value, 0, -4);

        return strtoupper(self::crc16($withoutCrc)) === strtoupper($match[1]);
    }

    private static function decodeStrictBase64(string $value): ?string
    {
        $compact = preg_replace('/\s+/', '', $value) ?? '';
        if (
            strlen($compact) < 16
            || strlen($compact) % 4 === 1
            || ! preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $compact)
        ) {
            return null;
        }

        $decoded = base64_decode($compact, true);
        if ($decoded === false) {
            return null;
        }

        $encodedAgain = rtrim(base64_encode($decoded), '=');
        if ($encodedAgain !== rtrim($compact, '=')) {
            return null;
        }

        return trim($decoded);
    }

    private static function crc16(string $value): string
    {
        $crc = 0xFFFF;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $crc ^= ord($value[$index]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0
                    ? (($crc << 1) ^ 0x1021) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
