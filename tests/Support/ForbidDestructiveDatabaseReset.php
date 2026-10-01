<?php

namespace Tests\Support;

use Illuminate\Database\Connection;
use RuntimeException;

/**
 * Impede que a suíte recrie ou esvazie o banco de desenvolvimento.
 * SQLite :memory: pode recriar schema. MySQL/MariaDB (unitec_erp) não.
 */
final class ForbidDestructiveDatabaseReset
{
    /** @var list<string> */
    private const BLOCKED_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
        'schema:drop',
    ];

    public static function install($app): void
    {
        if (! $app->runningUnitTests()) {
            return;
        }

        $app->make('db')->connection()->beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
            self::assertSqlAllowed($query, $connection->getDriverName(), (string) $connection->getDatabaseName());
        });
    }

    public static function assertCommandAllowed(string $command): void
    {
        $name = strtolower(trim(strtok($command, ' ') ?: $command));

        if (! in_array($name, self::BLOCKED_COMMANDS, true)) {
            return;
        }

        [$driver, $database] = self::configuredConnection();

        if (self::isDisposableSqlite($driver, $database)) {
            return;
        }

        throw new RuntimeException(self::message($driver, $database, $name));
    }

    public static function assertSqlAllowed(string $sql, string $driver, string $database): void
    {
        if (! self::isDestructiveSql($sql)) {
            return;
        }

        if (self::isDisposableSqlite($driver, $database)) {
            return;
        }

        throw new RuntimeException(self::message($driver, $database, self::sqlSnippet($sql)));
    }

    public static function assertSqliteMemoryOrFail(): void
    {
        [$driver, $database] = self::configuredConnection();

        if (self::isDisposableSqlite($driver, $database)) {
            return;
        }

        throw new RuntimeException(self::message($driver, $database, 'recriação de schema'));
    }

    public static function isDisposableSqlite(string $driver, string $database): bool
    {
        return strtolower($driver) === 'sqlite' && $database === ':memory:';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function configuredConnection(): array
    {
        $name = (string) config('database.default');
        $config = config("database.connections.{$name}");
        $config = is_array($config) ? $config : [];

        return [
            strtolower((string) ($config['driver'] ?? $name)),
            (string) ($config['database'] ?? ''),
        ];
    }

    private static function isDestructiveSql(string $sql): bool
    {
        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', $sql)));

        return str_starts_with($normalized, 'drop database')
            || str_starts_with($normalized, 'drop schema')
            || str_starts_with($normalized, 'drop table')
            || str_starts_with($normalized, 'truncate');
    }

    private static function sqlSnippet(string $sql): string
    {
        $oneLine = trim((string) preg_replace('/\s+/', ' ', $sql));

        return strlen($oneLine) > 80 ? substr($oneLine, 0, 80).'...' : $oneLine;
    }

    private static function message(string $driver, string $database, string $acao): string
    {
        $banco = $database !== '' ? $database : '(sem nome)';

        return 'Teste bloqueado: a suíte está em '.$driver.' (banco '.$banco.') e tentou "'.$acao.'". '
            .'migrate:fresh, db:wipe, RefreshDatabase, DROP e TRUNCATE não podem limpar o banco de desenvolvimento. '
            .'Use SQLite :memory: ou DatabaseTransactions. O banco unitec_erp não pode ser zerado por testes.';
    }
}
