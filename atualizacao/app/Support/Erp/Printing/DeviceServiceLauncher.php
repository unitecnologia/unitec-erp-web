<?php

namespace App\Support\Erp\Printing;

/**
 * Garante o Unitecnologia Device Service (bandeja / :9330) no PC local.
 * Só funciona quando o ERP e o caixa estão no mesmo Windows.
 */
final class DeviceServiceLauncher
{
    public static function isOnline(int $timeoutMs = 1500): bool
    {
        $base = rtrim((string) config('unitec.device_service.base_url', 'http://127.0.0.1:9330'), '/');
        $url = $base.'/api/status';

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => max(0.4, $timeoutMs / 1000),
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);

        if ($raw === false || $raw === '') {
            return false;
        }

        $json = json_decode($raw, true);

        if (! is_array($json)) {
            return true;
        }

        return ($json['online'] ?? true) !== false;
    }

    /**
     * @return array{ok: bool, online: bool, started: bool, message: string, exe: ?string}
     */
    public static function ensureRunning(): array
    {
        if (self::isOnline()) {
            return [
                'ok' => true,
                'online' => true,
                'started' => false,
                'message' => 'Device Service já online.',
                'exe' => self::resolveExe(),
            ];
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return [
                'ok' => false,
                'online' => false,
                'started' => false,
                'message' => 'Device Service só inicia automaticamente no Windows.',
                'exe' => null,
            ];
        }

        $exe = self::resolveExe();

        if ($exe === null) {
            return [
                'ok' => false,
                'online' => false,
                'started' => false,
                'message' => 'Unitec.DeviceService.exe não encontrado em services/unitec-device-service/dist.',
                'exe' => null,
            ];
        }

        $started = self::startExe($exe);

        if (! $started) {
            return [
                'ok' => false,
                'online' => false,
                'started' => false,
                'message' => 'Falha ao iniciar Unitec.DeviceService.exe.',
                'exe' => $exe,
            ];
        }

        usleep(800_000);

        $online = self::isOnline(2500);

        return [
            'ok' => $online,
            'online' => $online,
            'started' => true,
            'message' => $online
                ? 'Device Service iniciado.'
                : 'Device Service disparado; aguardando API :9330.',
            'exe' => $exe,
        ];
    }

    public static function resolveExe(): ?string
    {
        $candidates = [
            base_path('services/unitec-device-service/dist/Unitec.DeviceService.exe'),
            'C:\\UNITECNOLOGIA_WEB\\services\\unitec-device-service\\dist\\Unitec.DeviceService.exe',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function startExe(string $exe): bool
    {
        $cwd = dirname($exe);
        $exeEsc = str_replace('"', '""', $exe);
        $cwdEsc = str_replace('"', '""', $cwd);

        $cmd = 'cmd /C start "" /B /D "'.$cwdEsc.'" "'.$exeEsc.'"';

        try {
            if (function_exists('popen') && function_exists('pclose')) {
                $handle = @popen($cmd, 'r');
                if (is_resource($handle)) {
                    pclose($handle);

                    return true;
                }
            }
        } catch (\Throwable) {
            // fallback abaixo
        }

        try {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $pipes = [];
            $proc = @proc_open($cmd, $descriptors, $pipes, $cwd);

            if (is_resource($proc)) {
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($proc);

                return true;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
