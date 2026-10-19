<?php

namespace App\Support\Erp\Database;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * migrate --force com tolerância a schema já existente
 * (tabela/coluna/índice presentes, mas linha ausente em migrations).
 */
final class TolerantMigrateRunner
{
    private const MAX_ROUNDS = 250;

    /**
     * @param  callable(string): void|null  $write
     * @return array{ok: bool, rounds: int, skipped: list<string>, message: string}
     */
    public function run(?string $appPath = null, ?callable $write = null): array
    {
        $root = rtrim($appPath ?: base_path(), '\\/');
        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $write ??= static function (string $line): void {
            echo $line;
        };

        $skipped = [];
        $write('Aplicando migrations pendentes (modo tolerante)...'.PHP_EOL);
        $write('PHP: '.$php.PHP_EOL);
        $write('App: '.$root.PHP_EOL.PHP_EOL);

        for ($round = 1; $round <= self::MAX_ROUNDS; $round++) {
            $write("----- rodada {$round}/".self::MAX_ROUNDS.' -----'.PHP_EOL);

            // UNITEC_MIGRATE_PLAIN evita loop com App\Console\Commands\MigrateCommand.
            $env = $this->processEnvironment(['UNITEC_MIGRATE_PLAIN' => '1']);
            $process = new Process([$php, 'artisan', 'migrate', '--force'], $root, $env);
            $process->setTimeout(600);
            $process->run(static function (string $type, string $buffer) use ($write): void {
                $write($buffer);
            });

            $output = $process->getOutput().$process->getErrorOutput();
            $code = $process->getExitCode() ?? 1;

            if ($code === 0) {
                $write(PHP_EOL.'[OK] migrate concluiu sem erro.'.PHP_EOL);

                return [
                    'ok' => true,
                    'rounds' => $round,
                    'skipped' => $skipped,
                    'message' => 'migrate ok',
                ];
            }

            $failed = $this->extractFailedMigration($output);
            if ($failed === null || ! $this->isSkippableSchemaError($output)) {
                $message = 'Falha nao recuperavel automatica.';
                if ($failed !== null) {
                    $message .= ' Migration: '.$failed;
                }
                $write(PHP_EOL.'[ERRO] '.$message.PHP_EOL);

                return [
                    'ok' => false,
                    'rounds' => $round,
                    'skipped' => $skipped,
                    'message' => $message,
                ];
            }

            $write(PHP_EOL."Schema ja existia — registrando e continuando: {$failed}".PHP_EOL);
            $this->markRan($failed, $write);
            $skipped[] = $failed;
            $write(PHP_EOL);
        }

        $message = 'Limite de rodadas atingido.';
        $write('[ERRO] '.$message.PHP_EOL);

        return [
            'ok' => false,
            'rounds' => self::MAX_ROUNDS,
            'skipped' => $skipped,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function processEnvironment(array $extra): array
    {
        $env = [];
        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }

        return array_merge($env, $extra);
    }

    private function isSkippableSchemaError(string $output): bool
    {
        foreach ([
            'already exists',
            '42S01',
            '1050',
            'Duplicate column',
            '42S21',
            '1060',
            'Duplicate key name',
            '1061',
        ] as $needle) {
            if (str_contains($output, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function extractFailedMigration(string $output): ?string
    {
        if (preg_match('/^\s*(\d{4}_\d{2}_\d{2}_\d{6}_[A-Za-z0-9_]+)\s+\.+?\s+FAIL\b/m', $output, $m)) {
            return $m[1];
        }

        if (preg_match('/(\d{4}_\d{2}_\d{2}_\d{6}_[A-Za-z0-9_]+).{0,120}FAIL/s', $output, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @param  callable(string): void  $write
     */
    private function markRan(string $migration, callable $write): void
    {
        try {
            if (DB::table('migrations')->where('migration', $migration)->exists()) {
                $write("Ja estava em migrations: {$migration}".PHP_EOL);

                return;
            }

            $batch = (int) DB::table('migrations')->max('batch') + 1;
            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $batch,
            ]);
            $write("Marcada como Ran (objeto ja existia no banco): {$migration}  batch={$batch}".PHP_EOL);
        } catch (Throwable $e) {
            throw new \RuntimeException(
                'Falha ao registrar migration ja existente ('.$migration.'): '.$e->getMessage(),
                0,
                $e
            );
        }
    }
}
