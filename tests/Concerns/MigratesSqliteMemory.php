<?php

namespace Tests\Concerns;

use Illuminate\Testing\PendingCommand;
use PDO;
use Tests\Support\ForbidDestructiveDatabaseReset;

/**
 * Recria o schema só em SQLite :memory:. Não usa RefreshDatabase nem migrate:fresh.
 */
trait MigratesSqliteMemory
{
    private static ?PDO $sqliteMemoryPdo = null;

    private static bool $sqliteMemoryMigrated = false;

    protected function setUpMigratesSqliteMemory(): void
    {
        ForbidDestructiveDatabaseReset::assertSqliteMemoryOrFail();

        $connection = $this->app->make('db')->connection();

        if (! self::$sqliteMemoryMigrated || ! self::$sqliteMemoryPdo instanceof PDO) {
            $pending = $this->artisan('migrate', ['--force' => true]);
            $code = $pending instanceof PendingCommand ? $pending->run() : (int) $pending;

            if ($code !== 0) {
                throw new \RuntimeException('Falha ao migrar SQLite :memory: (exit '.$code.'). O banco de desenvolvimento não foi usado.');
            }

            self::$sqliteMemoryPdo = $connection->getPdo();
            self::$sqliteMemoryMigrated = true;
        } else {
            $connection->setPdo(self::$sqliteMemoryPdo);
        }

        $pdo = $connection->getPdo();
        // MySQL helpers usados em queries fiscais (IBPT etc.) — SQLite não tem nativo.
        if (! $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) || $connection->getDriverName() === 'sqlite') {
            $pdo->sqliteCreateFunction('LPAD', static function (?string $str, int $len, string $pad): string {
                $str = (string) $str;

                return str_pad($str, $len, $pad, STR_PAD_LEFT);
            }, 3);
        }

        $connection->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection): void {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        });
    }
}
