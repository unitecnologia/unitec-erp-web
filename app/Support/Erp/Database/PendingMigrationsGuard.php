<?php

namespace App\Support\Erp\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Detecta migrations pendentes (ex.: após restore de dump antigo) e aplica
 * somente `artisan migrate --force` — nunca migrate:fresh / wipe.
 */
final class PendingMigrationsGuard
{
    private const CACHE_OK_KEY = 'erp.schema.checked';

    private const LOCK_KEY = 'erp.schema.migrate';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * @return array{ran: bool, pending: int, ok: bool, message: ?string, auto: bool}
     */
    public function ensureUpToDate(): array
    {
        $auto = (bool) config('unitec.auto_migrate', true);

        if (Cache::get(self::CACHE_OK_KEY) === true) {
            return [
                'ran' => false,
                'pending' => 0,
                'ok' => true,
                'message' => null,
                'auto' => $auto,
            ];
        }

        try {
            if (! Schema::hasTable('migrations')) {
                return $this->handleMissingMigrationsTable($auto);
            }

            $pending = $this->pendingMigrationFiles();
            $pendingCount = count($pending);

            if ($pendingCount === 0) {
                Cache::put(self::CACHE_OK_KEY, true, self::CACHE_TTL_SECONDS);

                return [
                    'ran' => false,
                    'pending' => 0,
                    'ok' => true,
                    'message' => null,
                    'auto' => $auto,
                ];
            }

            if (! $auto) {
                $message = 'Banco desatualizado: '.$pendingCount.' migration(s) pendente(s). Defina ERP_AUTO_MIGRATE=true ou rode php artisan migrate.';
                Log::warning($message);
                $this->storeSessionError($message);

                return [
                    'ran' => false,
                    'pending' => $pendingCount,
                    'ok' => false,
                    'message' => $message,
                    'auto' => false,
                ];
            }

            return $this->runMigrateWithLock($pendingCount);
        } catch (Throwable $e) {
            $message = 'Falha ao verificar schema do banco: '.$e->getMessage();
            Log::error($message);
            $this->storeSessionError($message);

            return [
                'ran' => false,
                'pending' => 0,
                'ok' => false,
                'message' => $message,
                'auto' => $auto,
            ];
        }
    }

    /**
     * @return list<string>
     */
    private function pendingMigrationFiles(): array
    {
        /** @var \Illuminate\Database\Migrations\Migrator $migrator */
        $migrator = app('migrator');

        if (! $migrator->repositoryExists()) {
            $paths = array_values(array_unique(array_filter(array_merge(
                [database_path('migrations')],
                $migrator->paths(),
            ))));

            return array_keys($migrator->getMigrationFiles($paths));
        }

        $paths = array_values(array_unique(array_filter(array_merge(
            [database_path('migrations')],
            $migrator->paths(),
        ))));

        $files = $migrator->getMigrationFiles($paths);
        $ran = $migrator->getRepository()->getRan();

        return array_values(array_diff(array_keys($files), $ran));
    }

    /**
     * @return array{ran: bool, pending: int, ok: bool, message: ?string, auto: bool}
     */
    private function handleMissingMigrationsTable(bool $auto): array
    {
        $message = 'Tabela migrations ausente — banco incompleto ou restore inválido.';
        Log::error($message);

        if (! $auto) {
            $this->storeSessionError($message);

            return [
                'ran' => false,
                'pending' => -1,
                'ok' => false,
                'message' => $message,
                'auto' => false,
            ];
        }

        return $this->runMigrateWithLock(-1);
    }

    /**
     * @return array{ran: bool, pending: int, ok: bool, message: ?string, auto: bool}
     */
    private function runMigrateWithLock(int $pendingCount): array
    {
        $lock = Cache::lock(self::LOCK_KEY, 120);

        if (! $lock->get()) {
            return [
                'ran' => false,
                'pending' => max(0, $pendingCount),
                'ok' => true,
                'message' => null,
                'auto' => true,
            ];
        }

        try {
            // Outro processo pode ter migrado enquanto esperávamos o lock.
            if ($pendingCount >= 0) {
                $still = $this->pendingMigrationFiles();
                if ($still === []) {
                    Cache::put(self::CACHE_OK_KEY, true, self::CACHE_TTL_SECONDS);

                    return [
                        'ran' => false,
                        'pending' => 0,
                        'ok' => true,
                        'message' => null,
                        'auto' => true,
                    ];
                }
                $pendingCount = count($still);
            }

            Log::info('ERP auto-migrate: aplicando migrations pendentes.', [
                'pending' => $pendingCount,
            ]);

            $exit = Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());

            if ($exit !== 0) {
                $message = 'Banco desatualizado — migrate falhou'
                    .($output !== '' ? ': '.$output : ' (código '.$exit.').');
                Log::error($message);
                $this->storeSessionError($message);

                return [
                    'ran' => true,
                    'pending' => max(0, $pendingCount),
                    'ok' => false,
                    'message' => $message,
                    'auto' => true,
                ];
            }

            Cache::put(self::CACHE_OK_KEY, true, self::CACHE_TTL_SECONDS);
            Cache::forget('erp.schema.migrate_error');

            $okMessage = null;
            if ($pendingCount > 0) {
                $okMessage = 'Schema atualizado: '.$pendingCount.' migration(s) aplicada(s).';
                Log::info('ERP auto-migrate: '.$okMessage);
                $this->storeSessionSuccess($okMessage);
            } else {
                Log::info('ERP auto-migrate: schema verificado.');
            }

            return [
                'ran' => true,
                'pending' => max(0, $pendingCount),
                'ok' => true,
                'message' => $okMessage,
                'auto' => true,
            ];
        } catch (Throwable $e) {
            $message = 'Banco desatualizado — migrate falhou: '.$e->getMessage();
            Log::error($message);
            $this->storeSessionError($message);

            return [
                'ran' => true,
                'pending' => max(0, $pendingCount),
                'ok' => false,
                'message' => $message,
                'auto' => true,
            ];
        } finally {
            $lock->release();
        }
    }

    private function storeSessionError(string $message): void
    {
        try {
            if (app()->bound('session')) {
                session()->flash('erp_migrate_error', $message);
            }
            Cache::put('erp.schema.migrate_error', $message, self::CACHE_TTL_SECONDS);
        } catch (Throwable) {
            // ignore
        }
    }

    private function storeSessionSuccess(string $message): void
    {
        try {
            if (app()->bound('session')) {
                session()->flash('erp_migrate_ok', $message);
            }
        } catch (Throwable) {
            // ignore
        }
    }
}
