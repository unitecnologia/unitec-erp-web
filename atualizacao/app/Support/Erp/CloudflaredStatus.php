<?php

namespace App\Support\Erp;

/**
 * Lê status do túnel Cloudflare gravado pelo UnitecErpServer.
 */
final class CloudflaredStatus
{
    /**
     * @return array{
     *     online: bool,
     *     checked_at: ?string,
     *     last_online_at: ?string,
     *     pid: ?int,
     *     message: string,
     *     source: ?string
     * }
     */
    public static function read(): array
    {
        foreach (self::candidatePaths() as $path) {
            if (! is_file($path)) {
                continue;
            }

            try {
                $raw = file_get_contents($path);
                if (is_string($raw) && str_starts_with($raw, "\xEF\xBB\xBF")) {
                    $raw = substr($raw, 3);
                }
                $data = is_string($raw) ? json_decode($raw, true) : null;

                if (! is_array($data)) {
                    continue;
                }

                return [
                    'online' => (bool) ($data['online'] ?? false),
                    'checked_at' => self::nullableString($data['checked_at'] ?? null),
                    'last_online_at' => self::nullableString($data['last_online_at'] ?? null),
                    'pid' => isset($data['pid']) ? (int) $data['pid'] : null,
                    'message' => trim((string) ($data['message'] ?? '')),
                    'source' => $path,
                ];
            } catch (\Throwable) {
                // tenta próximo caminho
            }
        }

        return [
            'online' => false,
            'checked_at' => null,
            'last_online_at' => null,
            'pid' => null,
            'message' => 'Status do túnel ainda não disponível (UnitecErpServer / cloudflared).',
            'source' => null,
        ];
    }

    /**
     * Texto curto para title-bar / suporte.
     *
     * @return array{online: bool, label: string, detail: string}
     */
    public static function forUi(): array
    {
        $status = self::read();
        $online = (bool) ($status['online'] ?? false);

        $fmt = static function (?string $iso): string {
            $iso = trim((string) $iso);
            if ($iso === '') {
                return '—';
            }

            try {
                return ErpTimezone::toLocal($iso)->format('d/m/Y H:i:s');
            } catch (\Throwable) {
                return $iso;
            }
        };

        if ($online) {
            return [
                'online' => true,
                'label' => 'Online',
                'detail' => 'Última verificação: '.$fmt($status['checked_at'] ?? null),
            ];
        }

        return [
            'online' => false,
            'label' => 'Offline',
            'detail' => 'Última conexão OK: '.$fmt($status['last_online_at'] ?? null),
        ];
    }

    /**
     * @return list<string>
     */
    public static function candidatePaths(): array
    {
        $paths = [
            'C:\\ProgramData\\Unitec\\cloudflared\\status.json',
        ];

        $appBase = base_path();
        $paths[] = $appBase.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'cloudflared-status.json';

        return $paths;
    }

    private static function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
