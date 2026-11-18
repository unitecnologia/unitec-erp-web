<?php

namespace App\Support\Erp\Printing;

/**
 * Garante o Unitecnologia Device Service Windows (:9330) no PC local.
 * Caminho oficial: serviço UnitecDeviceService. Não sobe o EXE como app.
 */
final class DeviceServiceLauncher
{
    public const WINDOWS_SERVICE_NAME = 'UnitecDeviceService';

    /**
     * Nome legado ainda registrado em máquinas de desenvolvimento (erro 2 se o EXE antigo sumiu).
     */
    private const LEGACY_WINDOWS_SERVICE_NAME = 'UnitecPdvDeviceService';

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
        $serviceExists = self::windowsServiceExists();

        if (! $serviceExists) {
            return [
                'ok' => false,
                'online' => false,
                'started' => false,
                'message' => 'Serviço Windows UnitecDeviceService não instalado. Execute scripts\\install-device-service-startup.ps1 como administrador.',
                'exe' => $exe,
            ];
        }

        $started = self::startWindowsService();

        if (! $started) {
            return [
                'ok' => false,
                'online' => false,
                'started' => false,
                'message' => 'Falha ao iniciar o serviço Windows UnitecDeviceService.',
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
                ? 'Serviço Device Service iniciado.'
                : 'Serviço Device Service acionado; aguardando API :9330.',
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

    /**
     * Para o serviço/processo para permitir trocar arquivos em services/.../dist.
     */
    public static function stopRunning(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $serviceName = self::installedWindowsServiceName();
        if ($serviceName !== null) {
            try {
                @exec('sc stop '.$serviceName.' 2>NUL');
            } catch (\Throwable) {
                // ignore
            }

            usleep(1_200_000);
        }

        if (self::isOnline(500)) {
            try {
                @exec('taskkill /F /IM Unitec.DeviceService.exe 2>NUL');
            } catch (\Throwable) {
                // ignore
            }
        }

        usleep(400_000);
    }

    /**
     * True se a pasta de origem do update traz um dist novo do Device Service.
     */
    public static function packageContainsDist(string $sourceRoot): bool
    {
        $exe = rtrim($sourceRoot, '\\/').DIRECTORY_SEPARATOR
            .'services'.DIRECTORY_SEPARATOR
            .'unitec-device-service'.DIRECTORY_SEPARATOR
            .'dist'.DIRECTORY_SEPARATOR
            .'Unitec.DeviceService.exe';

        return is_file($exe);
    }

    private static function windowsServiceExists(): bool
    {
        return self::installedWindowsServiceName() !== null;
    }

    private static function installedWindowsServiceName(): ?string
    {
        foreach ([self::WINDOWS_SERVICE_NAME, self::LEGACY_WINDOWS_SERVICE_NAME] as $name) {
            try {
                @exec('sc query '.$name.' 2>NUL', $output, $queryExit);
                if ($queryExit === 0) {
                    return $name;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private static function startWindowsService(): bool
    {
        try {
            $serviceName = self::installedWindowsServiceName();
            if ($serviceName === null) {
                return false;
            }

            @exec('sc start '.$serviceName.' 2>NUL', $startOutput, $startExit);

            // 1056 = já está em execução.
            return in_array($startExit, [0, 1056], true);
        } catch (\Throwable) {
            return false;
        }
    }
}
