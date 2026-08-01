<?php

namespace App\Support\Erp\Import;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Lê o Firebird legado via isql.exe (servidor x86 / auth Legacy).
 */
final class FirebirdIsqlClient
{
    public function __construct(
        private readonly ?array $config = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    protected function cfg(): array
    {
        return $this->config ?? config('firebird', []);
    }

    public function isqlPath(): string
    {
        $configured = trim((string) ($this->cfg()['isql'] ?? ''), " \t\"'");

        if ($configured !== '' && is_file($configured)) {
            return $this->normalizePath($configured);
        }

        $candidates = [
            'C:\\Program Files (x86)\\Firebird\\Firebird_3_0\\isql.exe',
            'C:\\Program Files\\Firebird\\Firebird_3_0\\isql.exe',
            'C:\\Program Files (x86)\\Firebird\\Firebird_4_0\\isql.exe',
            'C:\\Program Files\\Firebird\\Firebird_4_0\\isql.exe',
            'C:\\Sistema\\isql.exe',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('isql.exe do Firebird não encontrado. Defina FB_ISQL no .env.');
    }

    /**
     * Diretório do Firebird (onde estão plugins/firebird.conf corretos).
     */
    public function firebirdHome(): string
    {
        return dirname($this->isqlPath());
    }

    public function databasePath(): string
    {
        $database = trim((string) ($this->cfg()['database'] ?? ''), " \t\"'");

        if ($database === '') {
            throw new RuntimeException('FB_DATABASE não configurado.');
        }

        return $this->normalizePath($database);
    }

    /**
     * String de CONNECT do isql.
     * Preferir TCP (localhost/3050:arquivo) quando o serviço Firebird está ativo —
     * embedded + FIREBIRD=install conflita com locks em C:\ProgramData\firebird.
     */
    public function databaseTarget(): string
    {
        $database = $this->databasePath();

        if (($this->cfg()['use_embedded'] ?? false) === true) {
            return $database;
        }

        $host = trim((string) ($this->cfg()['host'] ?? 'localhost')) ?: 'localhost';
        $port = (int) ($this->cfg()['port'] ?? 3050);

        return $host.'/'.$port.':'.$database;
    }

    public function ping(): bool
    {
        $rows = $this->query('SELECT 1 AS OK FROM RDB$DATABASE');

        return isset($rows[0]['OK']);
    }

    /**
     * @return list<string>
     */
    public function listTables(): array
    {
        $rows = $this->query(
            'SELECT TRIM(RDB$RELATION_NAME) AS NOME
             FROM RDB$RELATIONS
             WHERE RDB$SYSTEM_FLAG = 0 AND RDB$VIEW_BLR IS NULL
             ORDER BY 1'
        );

        return array_values(array_filter(array_map(
            fn (array $row): string => trim((string) ($row['NOME'] ?? '')),
            $rows,
        )));
    }

    /**
     * Executa SELECT e devolve linhas associativas (SET LIST ON).
     *
     * @return list<array<string, mixed>>
     */
    public function query(string $sql): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            return [];
        }

        if (! str_ends_with(rtrim($sql, " \t\n\r;"), ';')) {
            $sql .= ';';
        }

        $user = (string) ($this->cfg()['username'] ?? 'SYSDBA');
        $password = (string) ($this->cfg()['password'] ?? '');
        $database = str_replace("'", "''", $this->databaseTarget());
        $userSql = str_replace("'", "''", $user);
        $passSql = str_replace("'", "''", $password);

        // CONNECT no script evita herança ruim de FIREBIRD=/tools/php e
        // falhas CryptAcquireContext do plugin SRP no processo do PHP web.
        $script = "CONNECT '{$database}' USER '{$userSql}' PASSWORD '{$passSql}';\n"
            ."SET LIST ON;\n"
            ."SET HEADING OFF;\n"
            ."SET STATS OFF;\n"
            ."SET PLAN OFF;\n"
            .$sql."\n"
            ."EXIT;\n";

        $tmpDir = storage_path('app/firebird-migra');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $processTmp = $tmpDir.DIRECTORY_SEPARATOR.'tmp';
        if (! is_dir($processTmp)) {
            mkdir($processTmp, 0777, true);
        }

        // PHP/Symfony Process usam sys_get_temp_dir() — se TEMP/TMP vierem vazios
        // (comum no artisan serve / Livewire no Windows), cai em C:\Windows e dá Permission denied.
        $this->forceProcessTempDir($processTmp);

        $id = bin2hex(random_bytes(8));
        $scriptPath = $tmpDir.DIRECTORY_SEPARATOR.'q_'.$id.'.sql';
        $outPath = $tmpDir.DIRECTORY_SEPARATOR.'q_'.$id.'.out';

        // ASCII sem BOM — isql no Windows rejeita BOM na linha CONNECT.
        file_put_contents($scriptPath, $script);

        try {
            $process = new Process([
                $this->isqlPath(),
                '-i', $this->normalizePath($scriptPath),
                '-o', $this->normalizePath($outPath),
            ]);

            $process->setTimeout(600);
            $process->setWorkingDirectory($this->firebirdHome());
            $process->setEnv($this->cleanProcessEnv($processTmp));

            $process->run();

            $output = is_file($outPath) ? (string) file_get_contents($outPath) : '';
            $stderr = trim($process->getErrorOutput().' '.$process->getOutput());

            $failed = str_contains($stderr, 'Statement failed')
                || str_contains($stderr, 'SQLSTATE')
                || str_contains($stderr, 'Wrong file for memory mapping');

            if ($failed) {
                // Fallback: se TCP falhou e ainda não tentamos embedded (ou vice-versa).
                if (! ($this->cfg()['_retried'] ?? false)) {
                    return $this->retryAlternateMode($sql);
                }

                throw new RuntimeException($this->friendlyError($stderr));
            }

            if (! $process->isSuccessful() && $output === '') {
                throw new RuntimeException($this->friendlyError($stderr !== '' ? $stderr : 'Falha ao executar isql.'));
            }

            return $this->parseListOutput($output);
        } finally {
            @unlink($scriptPath);
            @unlink($outPath);
        }
    }

    /**
     * Tenta o modo oposto (TCP ↔ embedded) uma vez.
     *
     * @return list<array<string, mixed>>
     */
    protected function retryAlternateMode(string $sql): array
    {
        $cfg = $this->cfg();
        $cfg['use_embedded'] = ! (($cfg['use_embedded'] ?? false) === true);
        $cfg['_retried'] = true;

        return (new self($cfg))->query($sql);
    }

    /**
     * Ambiente para o isql: herda o processo e só ajusta PATH + TEMP gravável.
     * Não força FIREBIRD=pasta do isql — isso gera "Wrong file for memory mapping"
     * quando o serviço já usa C:\ProgramData\firebird.
     *
     * @return array<string, string|false>
     */
    protected function cleanProcessEnv(?string $tempDir = null): array
    {
        $home = $this->firebirdHome();
        $path = getenv('PATH') ?: ($_SERVER['PATH'] ?? '');
        $tempDir = $tempDir ?: $this->writableTempDir();

        $env = [];

        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                $env[$key] = $value;
            }
        }

        foreach (['PATH', 'SystemRoot', 'USERNAME', 'USERPROFILE', 'APPDATA', 'LOCALAPPDATA', 'ComSpec', 'PATHEXT'] as $key) {
            $val = getenv($key);
            if (is_string($val) && $val !== '') {
                $env[$key] = $val;
            }
        }

        $env['PATH'] = $home.';'.($env['PATH'] ?? $path);
        $env['TEMP'] = $tempDir;
        $env['TMP'] = $tempDir;
        $env['TMPDIR'] = $tempDir;
        $env['FIREBIRD_TMP'] = $tempDir;

        // Remove FIREBIRD herdado de tools/php ou de tentativa anterior.
        $env['FIREBIRD'] = false;

        return $env;
    }

    protected function writableTempDir(): string
    {
        $dir = storage_path('app/firebird-migra/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    /**
     * Garante que tempnam()/Symfony Process não caiam em C:\Windows.
     */
    protected function forceProcessTempDir(string $tempDir): void
    {
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        putenv('TEMP='.$tempDir);
        putenv('TMP='.$tempDir);
        putenv('TMPDIR='.$tempDir);
        $_ENV['TEMP'] = $tempDir;
        $_ENV['TMP'] = $tempDir;
        $_ENV['TMPDIR'] = $tempDir;
        $_SERVER['TEMP'] = $tempDir;
        $_SERVER['TMP'] = $tempDir;
        $_SERVER['TMPDIR'] = $tempDir;

        if (function_exists('ini_set')) {
            @ini_set('sys.temp_dir', $tempDir);
        }
    }

    protected function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path, " \t\"'"));
    }

    protected function friendlyError(string $stderr): string
    {
        $stderr = trim(preg_replace("/\s+/", ' ', $stderr) ?? $stderr);

        if (str_contains($stderr, 'Wrong file for memory mapping') || str_contains($stderr, 'ConfigStorage')) {
            return 'Conflito com o serviço Firebird (lock em ProgramData). '
                .'Na tela, use Host=localhost e Porta=3050 (conexão TCP) e deixe o Delphi/PDV aberto. '
                .'No .env use FB_USE_EMBEDDED=false. Detalhe: '.$stderr;
        }

        if (str_contains($stderr, 'CryptAcquireContext') || str_contains($stderr, '08006')) {
            return 'Falha ao autenticar no Firebird (plugin de login). '
                .'Confira se o serviço Firebird está rodando, se a senha está correta '
                .'(SYSDBA/masterkey) e se o arquivo .fdb não está bloqueado. Detalhe: '.$stderr;
        }

        return $stderr !== '' ? $stderr : 'Falha ao executar isql.';
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseListOutput(string $output): array
    {
        if (! mb_check_encoding($output, 'UTF-8')) {
            $output = mb_convert_encoding($output, 'UTF-8', 'Windows-1252') ?: $output;
        }

        $lines = preg_split("/\r\n|\n|\r/", $output) ?: [];
        $rows = [];
        $current = [];

        foreach ($lines as $line) {
            $trim = trim($line);

            if ($trim === '') {
                if ($current !== []) {
                    $rows[] = $current;
                    $current = [];
                }

                continue;
            }

            if (str_starts_with($trim, 'BLOB display')
                || str_starts_with($trim, "can't format message")
                || str_starts_with($trim, 'Database:')
                || str_starts_with($trim, 'SQL>')
                || str_starts_with($trim, 'CON>')
            ) {
                continue;
            }

            if (! preg_match('/^([A-Z0-9_$#]+)\s+(.*)$/u', $line, $m)) {
                continue;
            }

            $field = strtoupper(trim($m[1]));
            $value = trim($m[2]);

            if (strtoupper($value) === '<NULL>' || $value === '') {
                $current[$field] = null;
            } else {
                $current[$field] = $value;
            }
        }

        if ($current !== []) {
            $rows[] = $current;
        }

        return $rows;
    }
}
