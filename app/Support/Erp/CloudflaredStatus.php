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
     *     source: ?string,
     *     config_present: bool
     * }
     */
    public static function read(): array
    {
        $configPresent = self::configExists();
        $restartPending = is_file(self::restartFlagPath());

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

                $online = (bool) ($data['online'] ?? false);

                return [
                    'online' => $online,
                    'checked_at' => self::nullableString($data['checked_at'] ?? null),
                    'last_online_at' => self::nullableString($data['last_online_at'] ?? null),
                    'pid' => isset($data['pid']) ? (int) $data['pid'] : null,
                    'message' => self::resolveMessage(
                        online: $online,
                        rawMessage: trim((string) ($data['message'] ?? '')),
                        configPresent: $configPresent,
                        restartPending: $restartPending,
                    ),
                    'source' => $path,
                    'config_present' => $configPresent,
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
            'message' => self::resolveMessage(
                online: false,
                rawMessage: '',
                configPresent: $configPresent,
                restartPending: $restartPending,
            ),
            'source' => null,
            'config_present' => $configPresent,
        ];
    }

    public static function programDataDir(): string
    {
        return rtrim((string) config('unitec.cloudflare.program_data_dir', 'C:\\ProgramData\\Unitec\\cloudflared'), '\\/');
    }

    public static function configPath(): string
    {
        return self::programDataDir().DIRECTORY_SEPARATOR.'config.yml';
    }

    public static function restartFlagPath(): string
    {
        return self::programDataDir().DIRECTORY_SEPARATOR.'restart.flag';
    }

    public static function configExists(): bool
    {
        return is_file(self::configPath());
    }

    /**
     * Copia cloudflared.exe embarcado (resources/cloudflared) para ProgramData.
     * Necessário no update: bin/tools não vêm no ZIP; o serviço C# antigo já lê ProgramData.
     */
    public static function ensureExeInProgramData(): bool
    {
        $dest = self::programDataDir().DIRECTORY_SEPARATOR.'cloudflared.exe';
        if (is_file($dest) && (int) @filesize($dest) > 1_000_000) {
            return true;
        }

        $sources = [
            base_path('resources'.DIRECTORY_SEPARATOR.'cloudflared'.DIRECTORY_SEPARATOR.'cloudflared.exe'),
            base_path('bin'.DIRECTORY_SEPARATOR.'cloudflared.exe'),
            base_path('tools'.DIRECTORY_SEPARATOR.'cloudflared'.DIRECTORY_SEPARATOR.'cloudflared.exe'),
        ];

        $src = null;
        foreach ($sources as $candidate) {
            if (is_file($candidate) && (int) @filesize($candidate) > 1_000_000) {
                $src = $candidate;
                break;
            }
        }

        if ($src === null) {
            return is_file($dest);
        }

        $dir = self::programDataDir();
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }

        if (! @copy($src, $dest)) {
            return false;
        }

        return is_file($dest) && (int) @filesize($dest) > 1_000_000;
    }

    /**
     * Pede ao UnitecErpServer para (re)iniciar o cloudflared no próximo Ensure.
     */
    public static function requestRestart(): void
    {
        self::ensureExeInProgramData();

        $dir = self::programDataDir();
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Não foi possível criar a pasta {$dir}.");
        }

        if (@file_put_contents(self::restartFlagPath(), date('c')) === false) {
            throw new \RuntimeException('Não foi possível sinalizar o UnitecErpServer (restart.flag).');
        }
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
            self::programDataDir().DIRECTORY_SEPARATOR.'status.json',
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

    private static function resolveMessage(
        bool $online,
        string $rawMessage,
        bool $configPresent,
        bool $restartPending,
    ): string {
        if ($online) {
            return $rawMessage;
        }

        if ($restartPending && ! self::statusIsNewerThanRestartFlag()) {
            return 'Aguardando o UnitecErpServer iniciar o túnel…';
        }

        if (! $configPresent) {
            return 'Túnel ainda não criado — clique em Ativar túnel';
        }

        $normalized = mb_strtolower($rawMessage);
        $keepSpecific = $normalized !== ''
            && ! str_contains($normalized, 'config ausente')
            && ! str_contains($normalized, 'nao esta em execucao')
            && ! str_contains($normalized, 'não está em execução')
            && ! str_contains($normalized, 'status do túnel ainda não disponível');

        if ($keepSpecific) {
            return $rawMessage;
        }

        return 'Config OK, mas cloudflared parado — reinicie o UnitecErpServer';
    }

    /**
     * Serviço novo apaga o flag no Ensure. Serviço antigo ignora o flag, mas reescreve status.json.
     */
    private static function statusIsNewerThanRestartFlag(): bool
    {
        $flagMtime = @filemtime(self::restartFlagPath());
        if (! is_int($flagMtime) || $flagMtime <= 0) {
            return false;
        }

        foreach (self::candidatePaths() as $path) {
            if (! is_file($path)) {
                continue;
            }

            $statusMtime = @filemtime($path);
            if (is_int($statusMtime) && $statusMtime >= $flagMtime) {
                return true;
            }
        }

        return false;
    }
}
