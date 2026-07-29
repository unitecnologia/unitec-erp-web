<?php

namespace App\Support\Erp\WhatsApp;

class WhatsAppMessageHelper
{
    public const SYSTEM_FOOTER = 'Documento gerado por Unitecnologia ERP';

    public static function stripSystemFooter(string $message): string
    {
        $message = rtrim($message);

        if ($message === '') {
            return '';
        }

        while (str_ends_with($message, self::SYSTEM_FOOTER)) {
            $message = rtrim(substr($message, 0, -strlen(self::SYSTEM_FOOTER)));
        }

        $suffix = "\n\n" . self::SYSTEM_FOOTER;

        while (str_ends_with($message, $suffix)) {
            $message = rtrim(substr($message, 0, -strlen($suffix)));
        }

        $lines = preg_split("/\r\n|\n|\r/", $message) ?: [];

        while ($lines !== []) {
            $lastLine = trim((string) end($lines));

            if ($lastLine === self::SYSTEM_FOOTER) {
                array_pop($lines);

                if ($lines !== [] && trim((string) end($lines)) === '') {
                    array_pop($lines);
                }

                continue;
            }

            break;
        }

        return rtrim(implode("\n", $lines));
    }

    public static function withSystemFooter(string $message): string
    {
        $body = self::stripSystemFooter($message);

        if ($body === '') {
            return self::SYSTEM_FOOTER;
        }

        return $body . "\n\n" . self::SYSTEM_FOOTER;
    }

    public static function maxUserMessageLength(): int
    {
        return 1000;
    }
}
