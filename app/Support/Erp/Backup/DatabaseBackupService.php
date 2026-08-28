<?php

namespace App\Support\Erp\Backup;

use App\Support\Erp\ErpSystemConfig;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class DatabaseBackupService
{
    public const RETENTION_DAYS = 14;

    public const FILE_PREFIX = 'unitec_erp_';

    public const PRE_UPDATE_PREFIX = 'unitec_erp_preupdate_';

    /**
     * Backup forçado antes de atualizar o sistema (ignora intervalo / flag automática).
     *
     * @return array{ok: bool, path?: string, env_path?: string, size?: int, message: string, files_removed?: int}
     */
    public function runPreUpdate(?int $empresaId = null): array
    {
        return $this->run($empresaId, scheduled: false, preUpdate: true);
    }

    /**
     * @return array{ok: bool, path?: string, size?: int, message: string, files_removed?: int}
     */
    public function run(?int $empresaId = null, bool $scheduled = false, bool $preUpdate = false): array
    {
        $empresa = ErpSystemConfig::empresa($empresaId);

        if ($scheduled && ! ErpSystemConfig::backupEnabled($empresaId)) {
            return [
                'ok' => true,
                'message' => 'Backup automático desabilitado.',
            ];
        }

        if ($scheduled && ! $this->intervalElapsed($empresaId)) {
            return [
                'ok' => true,
                'message' => 'Ainda dentro do intervalo configurado; backup não necessário.',
            ];
        }

        $destination = $this->resolveDestination($empresaId);

        try {
            File::ensureDirectoryExists($destination);
        } catch (Throwable $e) {
            $this->markStatus($empresaId, 'failed', $e->getMessage());

            return [
                'ok' => false,
                'message' => 'Não foi possível criar a pasta de backup: '.$destination,
            ];
        }

        $mysqldump = $this->resolveMysqldumpPath();
        $connection = config('database.connections.'.config('database.default'), []);
        $database = (string) ($connection['database'] ?? '');
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        if ($database === '' || $username === '') {
            $this->markStatus($empresaId, 'failed', 'Conexão MySQL incompleta.');

            return [
                'ok' => false,
                'message' => 'Configuração do banco incompleta (.env).',
            ];
        }

        $stamp = ErpTimezone::toLocal()->format('Y-m-d_H-i-s');
        $prefix = $preUpdate ? self::PRE_UPDATE_PREFIX : self::FILE_PREFIX;
        $filename = $prefix.$stamp.'.sql';
        $targetPath = rtrim($destination, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;

        $this->markStatus($empresaId, 'running');

        try {
            $dumpResult = $this->executeMysqldump(
                mysqldump: $mysqldump,
                targetPath: $targetPath,
                database: $database,
                host: $host,
                port: $port,
                username: $username,
                password: $password,
            );

            if (! ($dumpResult['ok'] ?? false)) {
                @unlink($targetPath);
                $message = (string) ($dumpResult['message'] ?? 'mysqldump falhou sem mensagem.');
                $this->markStatus($empresaId, 'failed', mb_substr($message, 0, 240));

                return [
                    'ok' => false,
                    'message' => 'Falha ao gerar backup: '.$message,
                ];
            }

            $envPath = $this->copyEnvBackup($destination, $stamp, $prefix);

            if ($preUpdate) {
                if (! is_file(base_path('.env'))) {
                    @unlink($targetPath);
                    $this->markStatus($empresaId, 'failed', '.env ausente');

                    return [
                        'ok' => false,
                        'message' => 'Backup pré-update abortado: arquivo .env não encontrado.',
                    ];
                }

                if ($envPath === null) {
                    @unlink($targetPath);
                    $this->markStatus($empresaId, 'failed', 'Falha ao copiar .env');

                    return [
                        'ok' => false,
                        'message' => 'Backup pré-update abortado: não foi possível copiar o .env.',
                    ];
                }
            }

            $removed = $this->purgeOldBackups($destination);
            $size = (int) filesize($targetPath);
            $this->markStatus($empresaId, 'ok', 'Backup concluído - '.$this->formatBytes($size));

            $label = $preUpdate ? 'Backup pré-update' : 'Backup';
            $message = $label.' gerado: '.$filename.' ('.$this->formatBytes($size).').';
            if ($envPath !== null) {
                $message .= ' Inclui cópia do .env.';
            } else {
                $message .= ' Aviso: .env não encontrado para copiar.';
            }

            return [
                'ok' => true,
                'path' => $targetPath,
                'env_path' => $envPath,
                'size' => $size,
                'files_removed' => $removed,
                'message' => $message,
            ];
        } catch (Throwable $e) {
            report($e);
            @unlink($targetPath);
            $this->markStatus($empresaId, 'failed', mb_substr($e->getMessage(), 0, 240));

            return [
                'ok' => false,
                'message' => 'Erro ao gerar backup: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Copia o .env da instalação para a pasta de backup (mesmo carimbo do dump).
     */
    protected function copyEnvBackup(string $destination, string $stamp, string $prefix = self::FILE_PREFIX): ?string
    {
        $source = base_path('.env');

        if (! is_file($source)) {
            return null;
        }

        $filename = $prefix.$stamp.'.env';
        $targetPath = rtrim($destination, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;

        try {
            File::copy($source, $targetPath);

            return is_file($targetPath) ? $targetPath : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Executa mysqldump com TCP explícito, PATH/DLL corretos e retry em erro de socket (Windows 10106).
     *
     * @return array{ok: bool, message: string}
     */
    protected function executeMysqldump(
        string $mysqldump,
        string $targetPath,
        string $database,
        string $host,
        string $port,
        string $username,
        string $password,
    ): array {
        $attempts = $this->connectionAttempts($host, $port);
        $lastError = '';

        foreach ($attempts as $index => $attempt) {
            if ($index > 0) {
                usleep(350_000);
            }

            @unlink($targetPath);

            $defaultsFile = null;

            try {
                $defaultsFile = $this->writeDefaultsFile(
                    $attempt['host'],
                    $attempt['port'],
                    $username,
                    $password,
                );

                $command = [
                    $mysqldump,
                    '--defaults-extra-file='.$defaultsFile,
                    '--protocol='.$attempt['protocol'],
                    '--single-transaction',
                    '--routines',
                    '--triggers',
                    '--events',
                    '--hex-blob',
                    '--default-character-set=utf8mb4',
                    '--result-file='.$targetPath,
                    $database,
                ];

                $process = new Process($command);
                $process->setTimeout(600);
                $process->setWorkingDirectory(dirname($mysqldump));
                $process->setEnv($this->processEnvironment(dirname($mysqldump)));
                $process->run();

                if ($process->isSuccessful() && is_file($targetPath) && filesize($targetPath) >= 32) {
                    return ['ok' => true, 'message' => 'ok'];
                }

                @unlink($targetPath);
                $lastError = trim($process->getErrorOutput().' '.$process->getOutput());
                $lastError = $lastError !== '' ? $lastError : 'mysqldump falhou sem mensagem.';

                if (! $this->isRetryableSocketError($lastError)) {
                    break;
                }
            } finally {
                if ($defaultsFile && is_file($defaultsFile)) {
                    @unlink($defaultsFile);
                }
            }
        }

        return ['ok' => false, 'message' => $lastError];
    }

    /**
     * @return list<array{host: string, port: string, protocol: string}>
     */
    protected function connectionAttempts(string $host, string $port): array
    {
        $normalized = mb_strtolower(trim($host));
        $tcpHost = in_array($normalized, ['localhost', '::1', ''], true) ? '127.0.0.1' : $host;

        // Duas tentativas TCP: cobre falha intermitente de Winsock (error 10106) no Windows.
        return [
            ['host' => $tcpHost, 'port' => $port, 'protocol' => 'TCP'],
            ['host' => $tcpHost, 'port' => $port, 'protocol' => 'TCP'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function processEnvironment(string $binDir): array
    {
        $path = $binDir.PATH_SEPARATOR.(string) getenv('PATH');

        return [
            'PATH' => $path,
            'SystemRoot' => (string) (getenv('SystemRoot') ?: 'C:\\Windows'),
            'WINDIR' => (string) (getenv('WINDIR') ?: 'C:\\Windows'),
            'TEMP' => (string) (getenv('TEMP') ?: sys_get_temp_dir()),
            'TMP' => (string) (getenv('TMP') ?: sys_get_temp_dir()),
        ];
    }

    protected function isRetryableSocketError(string $error): bool
    {
        $haystack = mb_strtolower($error);

        return str_contains($haystack, '2004')
            || str_contains($haystack, '10106')
            || str_contains($haystack, "can't create tcp/ip socket")
            || str_contains($haystack, 'can\'t create tcp/ip socket')
            || str_contains($haystack, 'lost connection')
            || str_contains($haystack, 'can\'t connect');
    }

    public function resolveDestination(?int $empresaId = null): string
    {
        $configured = ErpSystemConfig::backupDestinationPath($empresaId);

        if ($configured !== '') {
            return $this->normalizePath($configured);
        }

        $default = storage_path('app/backups');
        File::ensureDirectoryExists($default);

        return $default;
    }

    public function resolveMysqldumpPath(): string
    {
        $configured = trim((string) env('MYSQLDUMP_PATH', ''));

        if ($configured !== '' && is_file($configured)) {
            return $this->normalizePath($configured);
        }

        $candidates = [
            base_path('tools/mysql/bin/mariadb-dump.exe'),
            base_path('tools/mysql/bin/mysqldump.exe'),
            base_path('tools/mysql/bin/mysqldump'),
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MariaDB 11.4\\bin\\mysqldump.exe',
            'C:\\Program Files\\MariaDB 10.11\\bin\\mysqldump.exe',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $this->normalizePath($path);
            }
        }

        $which = PHP_OS_FAMILY === 'Windows'
            ? trim((string) shell_exec('where mysqldump 2>nul'))
            : trim((string) shell_exec('command -v mysqldump 2>/dev/null'));

        $first = preg_split('/\r\n|\r|\n/', $which)[0] ?? '';

        if ($first !== '' && is_file($first)) {
            return $this->normalizePath($first);
        }

        throw new RuntimeException(
            'mysqldump não encontrado. Defina MYSQLDUMP_PATH no .env ou instale o cliente MySQL.'
        );
    }

    public function resolveMysqlClientPath(): string
    {
        $configured = trim((string) env('MYSQL_PATH', ''));

        if ($configured !== '' && is_file($configured)) {
            return $this->normalizePath($configured);
        }

        try {
            $dumpPath = $this->resolveMysqldumpPath();
            $binDir = dirname($dumpPath);
            $siblings = [
                $binDir.DIRECTORY_SEPARATOR.'mariadb.exe',
                $binDir.DIRECTORY_SEPARATOR.'mysql.exe',
                $binDir.DIRECTORY_SEPARATOR.'mariadb',
                $binDir.DIRECTORY_SEPARATOR.'mysql',
            ];

            foreach ($siblings as $sibling) {
                if (is_file($sibling)) {
                    return $this->normalizePath($sibling);
                }
            }
        } catch (Throwable) {
            // continua nos candidates absolutos
        }

        $candidates = [
            base_path('tools/mysql/bin/mariadb.exe'),
            base_path('tools/mysql/bin/mysql.exe'),
            base_path('tools/mysql/bin/mariadb'),
            base_path('tools/mysql/bin/mysql'),
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
            'C:\\Program Files\\MariaDB 11.4\\bin\\mariadb.exe',
            'C:\\Program Files\\MariaDB 10.11\\bin\\mariadb.exe',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $this->normalizePath($path);
            }
        }

        $which = PHP_OS_FAMILY === 'Windows'
            ? trim((string) shell_exec('where mysql 2>nul'))
            : trim((string) shell_exec('command -v mysql 2>/dev/null'));

        $first = preg_split('/\r\n|\r|\n/', $which)[0] ?? '';

        if ($first !== '' && is_file($first)) {
            return $this->normalizePath($first);
        }

        throw new RuntimeException(
            'Cliente MySQL/MariaDB não encontrado. Defina MYSQL_PATH no .env ou instale o cliente MySQL.'
        );
    }

    /**
     * @return array{ok: bool, path?: string, name?: string, message: string}
     */
    public function validateSqlBackupPath(string $sqlPath): array
    {
        $normalized = $this->normalizePath(trim($sqlPath));

        if ($normalized === '' || str_contains($normalized, '..')) {
            return ['ok' => false, 'message' => 'Caminho do backup inválido.'];
        }

        if (! is_file($normalized)) {
            return ['ok' => false, 'message' => 'Arquivo de backup não encontrado.'];
        }

        $name = basename($normalized);
        $lower = mb_strtolower($name);

        if (! str_ends_with($lower, '.sql')) {
            return ['ok' => false, 'message' => 'Selecione um arquivo .sql de backup.'];
        }

        if (! str_starts_with($name, self::FILE_PREFIX) && ! str_starts_with($name, self::PRE_UPDATE_PREFIX)) {
            return ['ok' => false, 'message' => 'Arquivo não é um backup Unitec válido (prefixo unitec_erp_).'];
        }

        if (filesize($normalized) < 32) {
            return ['ok' => false, 'message' => 'Arquivo de backup vazio ou corrompido.'];
        }

        return [
            'ok' => true,
            'path' => $normalized,
            'name' => $name,
            'message' => 'ok',
        ];
    }

    /**
     * Lista dumps .sql Unitec em qualquer pasta legível.
     *
     * @return list<array{name: string, path: string, kind: string, size: int, size_label: string, modified_at: string, modified_ts: int}>
     */
    public function listSqlBackupsInDirectory(string $directory, int $limit = 80): array
    {
        $directory = $this->normalizePath(trim($directory));

        if ($directory === '' || str_contains($directory, '..') || ! is_dir($directory)) {
            return [];
        }

        $files = collect(File::files($directory))
            ->filter(function ($file): bool {
                $name = $file->getFilename();
                $lower = mb_strtolower($name);

                $isBackup = str_starts_with($name, self::FILE_PREFIX)
                    || str_starts_with($name, self::PRE_UPDATE_PREFIX);

                return $isBackup && str_ends_with($lower, '.sql') && $file->getSize() >= 32;
            })
            ->sortByDesc(fn ($file): int => $file->getMTime())
            ->take($limit)
            ->values();

        return $files->map(function ($file): array {
            $size = (int) $file->getSize();

            return [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'kind' => 'sql',
                'size' => $size,
                'size_label' => $this->formatBytes($size),
                'modified_at' => ErpTimezone::toLocal(
                    \Illuminate\Support\Carbon::createFromTimestamp($file->getMTime())
                )->format('d/m/Y H:i'),
                'modified_ts' => $file->getMTime(),
            ];
        })->all();
    }

    /**
     * Restaura um dump .sql no banco atual (gera backup de segurança antes).
     *
     * @return array{ok: bool, message: string, safety_path?: string, restored_path?: string, restored_name?: string}
     */
    public function restore(string $sqlPath, ?int $empresaId = null): array
    {
        $validated = $this->validateSqlBackupPath($sqlPath);

        if (! ($validated['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($validated['message'] ?? 'Backup inválido.'),
            ];
        }

        $sqlPath = (string) $validated['path'];
        $sqlName = (string) $validated['name'];

        $connection = config('database.connections.'.config('database.default'), []);
        $database = (string) ($connection['database'] ?? '');
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        if ($database === '' || $username === '') {
            return [
                'ok' => false,
                'message' => 'Configuração do banco incompleta (.env).',
            ];
        }

        try {
            $mysql = $this->resolveMysqlClientPath();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }

        $safety = $this->run($empresaId, scheduled: false, preUpdate: false);

        if (! ($safety['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => 'Não foi possível gerar o backup de segurança antes da restauração: '
                    .(string) ($safety['message'] ?? 'falha desconhecida.'),
            ];
        }

        $safetyPath = (string) ($safety['path'] ?? '');

        try {
            $import = $this->executeMysqlImport(
                mysql: $mysql,
                sqlPath: $sqlPath,
                database: $database,
                host: $host,
                port: $port,
                username: $username,
                password: $password,
            );

            if (! ($import['ok'] ?? false)) {
                $message = (string) ($import['message'] ?? 'Falha ao importar dump.');

                return array_filter([
                    'ok' => false,
                    'message' => 'Falha ao restaurar: '.$message
                        .($safetyPath !== '' ? ' Use o backup de segurança: '.$safetyPath : ''),
                    'safety_path' => $safetyPath !== '' ? $safetyPath : null,
                ], static fn ($value) => $value !== null);
            }

            return array_filter([
                'ok' => true,
                'message' => 'Banco restaurado a partir de '.$sqlName.'.'
                    .($safetyPath !== '' ? ' Backup de segurança: '.basename($safetyPath).'.' : ''),
                'safety_path' => $safetyPath !== '' ? $safetyPath : null,
                'restored_path' => $sqlPath,
                'restored_name' => $sqlName,
            ], static fn ($value) => $value !== null);
        } catch (Throwable $e) {
            report($e);

            return array_filter([
                'ok' => false,
                'message' => 'Erro ao restaurar: '.$e->getMessage()
                    .($safetyPath !== '' ? ' Use o backup de segurança: '.$safetyPath : ''),
                'safety_path' => $safetyPath !== '' ? $safetyPath : null,
            ], static fn ($value) => $value !== null);
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    protected function executeMysqlImport(
        string $mysql,
        string $sqlPath,
        string $database,
        string $host,
        string $port,
        string $username,
        string $password,
    ): array {
        $attempts = $this->connectionAttempts($host, $port);
        $lastError = '';

        foreach ($attempts as $index => $attempt) {
            if ($index > 0) {
                usleep(350_000);
            }

            $defaultsFile = null;
            $input = null;

            try {
                $defaultsFile = $this->writeDefaultsFile(
                    $attempt['host'],
                    $attempt['port'],
                    $username,
                    $password,
                );

                $command = [
                    $mysql,
                    '--defaults-extra-file='.$defaultsFile,
                    '--protocol='.$attempt['protocol'],
                    '--default-character-set=utf8mb4',
                    $database,
                ];

                $input = fopen($sqlPath, 'rb');
                if ($input === false) {
                    return ['ok' => false, 'message' => 'Não foi possível ler o arquivo SQL.'];
                }

                $process = new Process($command);
                $process->setTimeout(900);
                $process->setWorkingDirectory(dirname($mysql));
                $process->setEnv($this->processEnvironment(dirname($mysql)));
                $process->setInput($input);
                $process->run();

                if (is_resource($input)) {
                    fclose($input);
                    $input = null;
                }

                if ($process->isSuccessful()) {
                    return ['ok' => true, 'message' => 'ok'];
                }

                $lastError = trim($process->getErrorOutput().' '.$process->getOutput());
                $lastError = $lastError !== '' ? $lastError : 'Importação MySQL falhou sem mensagem.';

                if (! $this->isRetryableSocketError($lastError)) {
                    break;
                }
            } finally {
                if (is_resource($input)) {
                    fclose($input);
                }
                if ($defaultsFile && is_file($defaultsFile)) {
                    @unlink($defaultsFile);
                }
            }
        }

        return ['ok' => false, 'message' => $lastError];
    }

    /**
     * @return list<array{name: string, path: string, kind: string, size: int, size_label: string, modified_at: string, modified_ts: int}>
     */
    public function listBackups(?int $empresaId = null, int $limit = 40): array
    {
        $destination = $this->resolveDestination($empresaId);

        if (! is_dir($destination)) {
            return [];
        }

        $files = collect(File::files($destination))
            ->filter(function ($file): bool {
                $name = $file->getFilename();
                $lower = mb_strtolower($name);

                $isBackup = str_starts_with($name, self::FILE_PREFIX)
                    || str_starts_with($name, self::PRE_UPDATE_PREFIX);

                return $isBackup && (str_ends_with($lower, '.sql') || str_ends_with($lower, '.env'));
            })
            ->sortByDesc(fn ($file): int => $file->getMTime())
            ->take($limit)
            ->values();

        return $files->map(function ($file): array {
            $size = (int) $file->getSize();
            $lower = mb_strtolower($file->getFilename());

            return [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'kind' => str_ends_with($lower, '.env') ? 'env' : 'sql',
                'size' => $size,
                'size_label' => $this->formatBytes($size),
                'modified_at' => ErpTimezone::toLocal(
                    \Illuminate\Support\Carbon::createFromTimestamp($file->getMTime())
                )->format('d/m/Y H:i'),
                'modified_ts' => $file->getMTime(),
            ];
        })->all();
    }

    public function purgeOldBackups(string $destination, int $retentionDays = self::RETENTION_DAYS): int
    {
        if (! is_dir($destination)) {
            return 0;
        }

        $cutoff = time() - ($retentionDays * 86400);
        $removed = 0;

        foreach (File::files($destination) as $file) {
            $name = $file->getFilename();
            $lower = mb_strtolower($name);

            if (! str_starts_with($name, self::FILE_PREFIX) && ! str_starts_with($name, self::PRE_UPDATE_PREFIX)) {
                continue;
            }

            if (! str_ends_with($lower, '.sql') && ! str_ends_with($lower, '.env')) {
                continue;
            }

            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
                $removed++;
            }
        }

        return $removed;
    }

    public function intervalElapsed(?int $empresaId = null): bool
    {
        $last = ErpSystemConfig::backupLastAt($empresaId);

        if ($last === null || trim($last) === '') {
            return true;
        }

        try {
            $lastTs = ErpTimezone::toLocal(\Illuminate\Support\Carbon::parse($last))->getTimestamp();
        } catch (Throwable) {
            return true;
        }

        $hours = ErpSystemConfig::backupIntervalHours($empresaId);

        return (time() - $lastTs) >= ($hours * 3600);
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }

        return number_format($bytes / 1048576, 2, ',', '.').' MB';
    }

    protected function writeDefaultsFile(string $host, string $port, string $username, string $password): string
    {
        $dir = storage_path('app/backups/.tmp');
        File::ensureDirectoryExists($dir);

        $path = $dir.DIRECTORY_SEPARATOR.'my_'.bin2hex(random_bytes(8)).'.cnf';
        $escapedPassword = str_replace(['\\', '"'], ['\\\\', '\\"'], $password);

        $contents = "[client]\n"
            ."host=\"{$host}\"\n"
            ."port=\"{$port}\"\n"
            ."user=\"{$username}\"\n"
            ."password=\"{$escapedPassword}\"\n";

        file_put_contents($path, $contents);

        return $path;
    }

    protected function markStatus(?int $empresaId, string $status, ?string $detail = null): void
    {
        $empresa = ErpSystemConfig::empresa($empresaId);

        if (! $empresa) {
            return;
        }

        $payload = [
            'param_backup_ultimo_status' => $status,
        ];

        if ($status === 'ok' || $status === 'failed') {
            $payload['param_backup_ultimo_em'] = ErpTimezone::toLocal()->format('Y-m-d H:i:s');
        }

        if ($status === 'failed' && filled($detail)) {
            $payload['param_backup_ultimo_status'] = 'failed';
        }

        $empresa->forceFill($payload)->save();

        if (in_array($status, ['ok', 'failed'], true)) {
            app(PortalBkpReporter::class)->report(
                $empresa,
                $status,
                $detail ?? ($status === 'ok' ? 'Backup concluído.' : 'Falha ao executar backup.'),
            );
        }
    }

    protected function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
