<?php

namespace App\Support\Erp\Terminais;

/**
 * Lista impressoras instaladas no Windows do PC onde o PHP roda (caixa local).
 *
 * Get-Printer / wmic podem travar no spooler. Sem timeout, o artisan serve
 * (processo único) congela a aplicação inteira ao abrir Terminais.
 */
final class WindowsPrinterEnumerator
{
    private const TIMEOUT_SECONDS = 3;

    /** @var list<string>|null */
    private static ?array $cache = null;

    private static int $cacheAt = 0;

    private const CACHE_TTL_SECONDS = 120;

    /**
     * @return list<string>
     */
    public static function names(bool $forceRefresh = false): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        if (! $forceRefresh && self::$cache !== null && (time() - self::$cacheAt) < self::CACHE_TTL_SECONDS) {
            return self::$cache;
        }

        $names = self::viaPowerShell();

        if ($names === []) {
            $names = self::viaWmic();
        }

        $names = array_values(array_unique(array_filter(
            array_map(static fn (string $n): string => trim($n), $names),
            static fn (string $n): bool => $n !== '' && strcasecmp($n, 'Name') !== 0,
        )));

        natcasesort($names);
        self::$cache = array_values($names);
        self::$cacheAt = time();

        return self::$cache;
    }

    /**
     * @return list<string>
     */
    private static function viaPowerShell(): array
    {
        $script = '$j=Start-Job{Get-Printer|Select-Object -ExpandProperty Name};'
            .'if(Wait-Job $j -Timeout '.self::TIMEOUT_SECONDS.'){Receive-Job $j}'
            .'else{Stop-Job $j -Force;Remove-Job $j -Force}';

        $cmd = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "'.$script.'"';

        return self::runCommand($cmd);
    }

    /**
     * @return list<string>
     */
    private static function viaWmic(): array
    {
        return self::runCommand('wmic.exe printer get name');
    }

    /**
     * @return list<string>
     */
    private static function runCommand(string $command): array
    {
        if (! function_exists('proc_open')) {
            return [];
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, null, null);

        if (! is_resource($process)) {
            return [];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $started = microtime(true);
        $pid = null;

        while (true) {
            $status = proc_get_status($process);
            if ($pid === null && isset($status['pid'])) {
                $pid = (int) $status['pid'];
            }

            $chunk = stream_get_contents($pipes[1]);
            if (is_string($chunk) && $chunk !== '') {
                $stdout .= $chunk;
            }
            stream_get_contents($pipes[2]);

            if (! ($status['running'] ?? false)) {
                break;
            }

            if ((microtime(true) - $started) >= self::TIMEOUT_SECONDS + 2) {
                self::killProcessTree($pid);
                @proc_terminate($process, 9);
                break;
            }

            usleep(40_000);
        }

        $chunk = stream_get_contents($pipes[1]);
        if (is_string($chunk) && $chunk !== '') {
            $stdout .= $chunk;
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        @proc_close($process);

        $lines = [];
        foreach (preg_split('/\R/', $stdout) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || strcasecmp($line, 'Name') === 0) {
                continue;
            }
            $lines[] = $line;
        }

        return $lines;
    }

    private static function killProcessTree(?int $pid): void
    {
        if ($pid === null || $pid <= 0 || PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        @exec('taskkill /F /T /PID '.$pid.' 2>NUL');
    }
}
