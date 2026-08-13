<?php

namespace App\Support\Erp\License;

/**
 * Identificadores locais exibidos na tela de licença (informativo).
 */
final class LicencaMachineInfo
{
    public static function computador(): string
    {
        $host = trim((string) gethostname());

        return $host !== '' ? $host : '—';
    }

    public static function macAddress(): string
    {
        static $cached = null;

        if (is_string($cached)) {
            return $cached;
        }

        $mac = self::detectMac();
        $cached = $mac !== '' ? $mac : '—';

        return $cached;
    }

    private static function detectMac(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $out = [];
            @exec('getmac /fo csv /nh 2>NUL', $out);

            foreach ($out as $line) {
                if (preg_match('/"([0-9A-Fa-f]{2}(?:-[0-9A-Fa-f]{2}){5})"/', $line, $m) === 1) {
                    return strtoupper($m[1]);
                }
            }
        }

        if (is_readable('/sys/class/net')) {
            foreach (scandir('/sys/class/net') ?: [] as $iface) {
                if ($iface === '.' || $iface === '..' || $iface === 'lo') {
                    continue;
                }

                $path = '/sys/class/net/'.$iface.'/address';
                if (! is_readable($path)) {
                    continue;
                }

                $raw = strtoupper(trim((string) @file_get_contents($path)));
                if (preg_match('/^[0-9A-F]{2}(?::[0-9A-F]{2}){5}$/', $raw) === 1
                    && $raw !== '00:00:00:00:00:00') {
                    return str_replace(':', '-', $raw);
                }
            }
        }

        return '';
    }
}
