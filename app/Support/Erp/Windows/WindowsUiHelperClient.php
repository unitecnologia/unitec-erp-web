<?php

namespace App\Support\Erp\Windows;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Cliente do Unitec ERP UI Helper (loopback :9331) — processo na sessão interativa
 * iniciado pelo Launcher (`Unitec ERP.exe --ui-helper`).
 *
 * Usado para FolderBrowserDialog quando o PHP roda sob UnitecErpServer (Session 0).
 */
final class WindowsUiHelperClient
{
    public const DEFAULT_BASE_URL = 'http://127.0.0.1:9331';

    public static function baseUrl(): string
    {
        return rtrim((string) config('unitec.ui_helper.base_url', self::DEFAULT_BASE_URL), '/');
    }

    public static function isOnline(int $timeoutMs = 1500): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        $url = self::baseUrl().'/api/status';
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => max(0.3, $timeoutMs / 1000),
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
     * Fecha seletor preso (diálogos órfãos / cancelamento).
     */
    public static function cancelPick(int $timeoutSeconds = 2): bool
    {
        $url = self::baseUrl().'/api/cancel-pick';
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => max(1, $timeoutSeconds),
                'ignore_errors' => true,
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => '{}',
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            return false;
        }

        $json = json_decode($raw, true);

        return is_array($json) && (($json['ok'] ?? false) === true);
    }

    /**
     * @return array{ok: bool, cancelled: bool, path: ?string, message: ?string}
     */
    public static function pickFolder(string $initialPath = '', string $description = 'Selecione a pasta', int $timeoutSeconds = 300): array
    {
        // Libera diálogo anterior antes de abrir outro.
        if (self::isOnline(800)) {
            self::cancelPick(2);
            usleep(200_000);
        }

        $attempt = self::pickFolderOnce($initialPath, $description, $timeoutSeconds);
        if (($attempt['message'] ?? null) !== null
            && str_contains((string) $attempt['message'], 'ainda está fechando')) {
            usleep(400_000);
            self::cancelPick(2);
            usleep(200_000);
            $attempt = self::pickFolderOnce($initialPath, $description, $timeoutSeconds);
        }

        return $attempt;
    }

    /**
     * @return array{ok: bool, cancelled: bool, path: ?string, message: ?string}
     */
    private static function pickFolderOnce(string $initialPath, string $description, int $timeoutSeconds): array
    {
        $url = self::baseUrl().'/api/pick-folder';
        $payload = json_encode([
            'initialPath' => $initialPath,
            'description' => $description,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return [
                'ok' => false,
                'cancelled' => false,
                'path' => null,
                'message' => 'Falha ao montar pedido do seletor de pasta.',
            ];
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => max(5, $timeoutSeconds),
                'ignore_errors' => true,
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            return [
                'ok' => false,
                'cancelled' => false,
                'path' => null,
                'message' => 'UI Helper offline ou sem resposta. Use Alt+Tab ou reinicie o Unitec ERP.',
            ];
        }

        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return [
                'ok' => false,
                'cancelled' => false,
                'path' => null,
                'message' => 'Resposta inválida do UI Helper.',
            ];
        }

        if (! empty($json['cancelled'])) {
            return [
                'ok' => false,
                'cancelled' => true,
                'path' => null,
                'message' => null,
            ];
        }

        $path = trim((string) ($json['path'] ?? ''));
        if (! empty($json['ok']) && $path !== '') {
            return [
                'ok' => true,
                'cancelled' => false,
                'path' => str_replace('/', '\\', $path),
                'message' => null,
            ];
        }

        return [
            'ok' => false,
            'cancelled' => false,
            'path' => null,
            'message' => trim((string) ($json['message'] ?? 'Seletor de pasta não concluiu.')),
        ];
    }

    /**
     * Sobe o helper só na sessão interativa (DEV / usuário). Nunca a partir da Session 0.
     *
     * @return array{ok: bool, online: bool, started: bool, message: string}
     */
    public static function ensureRunning(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [
                'ok' => false,
                'online' => false,
                'started' => false,
                'message' => 'UI Helper só no Windows.',
            ];
        }

        if (self::isOnline()) {
            return [
                'ok' => true,
                'online' => true,
                'started' => false,
                'message' => 'UI Helper já online.',
            ];
        }

        if (! self::isInteractiveSession()) {
            return [
                'ok' => false,
                'online' => false,
                'started' => false,
                'message' => 'Sem sessão interativa (serviço). Abra o Unitec ERP pelo atalho.',
            ];
        }

        $exe = self::resolveLauncherExe();
        if ($exe === null) {
            return [
                'ok' => false,
                'online' => false,
                'started' => false,
                'message' => 'Unitec ERP.exe não encontrado para iniciar o UI Helper.',
            ];
        }

        try {
            $process = new Process([
                $exe,
                '--ui-helper',
                '--app',
                base_path(),
            ]);
            $process->setWorkingDirectory(dirname($exe));
            $process->setTimeout(null);
            $process->start();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'online' => false,
                'started' => false,
                'message' => 'Falha ao iniciar UI Helper: '.$e->getMessage(),
            ];
        }

        for ($i = 0; $i < 25; $i++) {
            usleep(150_000);
            if (self::isOnline(800)) {
                return [
                    'ok' => true,
                    'online' => true,
                    'started' => true,
                    'message' => 'UI Helper iniciado.',
                ];
            }
        }

        return [
            'ok' => false,
            'online' => false,
            'started' => true,
            'message' => 'UI Helper iniciado, mas /api/status não respondeu a tempo.',
        ];
    }

    /**
     * SessionId 0 = serviço Windows (sem desktop). >0 = usuário interativo.
     * Usa o PID do PHP (FrankenPHP/artisan), não o do PowerShell filho.
     */
    public static function isInteractiveSession(): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $pid = (int) getmypid();
        if ($pid <= 0) {
            $cached = false;

            return false;
        }

        try {
            $process = new Process([
                'powershell.exe',
                '-NoProfile',
                '-NonInteractive',
                '-Command',
                '(Get-Process -Id '.$pid.' -ErrorAction Stop).SessionId',
            ]);
            $process->setTimeout(5);
            $process->run();
            if (! $process->isSuccessful()) {
                $cached = false;

                return false;
            }

            $sessionId = (int) trim($process->getOutput());
            $cached = $sessionId > 0;
        } catch (Throwable) {
            $cached = false;
        }

        return $cached;
    }

    public static function resolveLauncherExe(): ?string
    {
        $candidates = [
            base_path('bin'.DIRECTORY_SEPARATOR.'Unitec ERP.exe'),
            'C:\\UNITECNOLOGIA_WEB\\bin\\Unitec ERP.exe',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
