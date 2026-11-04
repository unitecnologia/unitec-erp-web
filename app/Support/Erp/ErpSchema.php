<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Memoiza Schema::hasTable / hasColumn no processo PHP.
 * hasColumn carrega o listing da tabela UMA vez (evita N hits em information_schema).
 *
 * FrankenPHP reutiliza o worker: o memo de processo é invalidado quando o fingerprint
 * de migrations muda (arquivo novo / migrate aplicado), sem exigir restart.
 */
final class ErpSchema
{
    /** @var array<string, bool> */
    private static array $tables = [];

    /** @var array<string, array<string, true>> */
    private static array $columnMaps = [];

    private static ?string $migrationsFingerprint = null;

    /**
     * Quando true, assume migrations aplicadas (sem hits em information_schema).
     * Usado pelo dashboard ERP em navegação normal.
     */
    private static bool $assumeMigrated = false;

    public static function assumeMigrated(bool $assume = true): void
    {
        self::$assumeMigrated = $assume;
    }

    public static function hasTable(string $table): bool
    {
        if (self::$assumeMigrated) {
            return true;
        }

        self::invalidateIfMigrationsChanged();

        $key = self::normalizeTable($table);

        if (array_key_exists($key, self::$tables)) {
            return self::$tables[$key];
        }

        return self::$tables[$key] = Schema::hasTable($key);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        if (self::$assumeMigrated) {
            return true;
        }

        self::invalidateIfMigrationsChanged();

        $table = self::normalizeTable($table);
        $column = trim($column);

        if ($column === '') {
            return false;
        }

        if (! array_key_exists($table, self::$columnMaps)) {
            if (! self::hasTable($table)) {
                self::$columnMaps[$table] = [];

                return false;
            }

            $listing = Schema::getColumnListing($table);
            $map = [];
            foreach ($listing as $name) {
                $map[(string) $name] = true;
            }
            self::$columnMaps[$table] = $map;
        }

        return isset(self::$columnMaps[$table][$column]);
    }

    /**
     * Igual a hasColumn, mas não acredita em assumeMigrated().
     * O dashboard liga esse atalho para evitar information_schema em toda navegação;
     * colunas que nem toda instalação tem (ex.: pdv_vendas.empresa_id) precisam da checagem real.
     */
    public static function hasColumnActual(string $table, string $column): bool
    {
        $previous = self::$assumeMigrated;
        self::$assumeMigrated = false;

        try {
            return self::hasColumn($table, $column);
        } finally {
            self::$assumeMigrated = $previous;
        }
    }

    public static function flush(): void
    {
        self::$tables = [];
        self::$columnMaps = [];
        self::$migrationsFingerprint = null;

        if (app()->bound('request')) {
            request()->attributes->remove('erp.schema.fp_checked');
        }
    }

    /**
     * Uma verificação barata por request: se migrations mudaram (update/migrate),
     * descarta memo de tabelas/colunas sem reiniciar o FrankenPHP.
     */
    private static function invalidateIfMigrationsChanged(): void
    {
        if (app()->bound('request') && request()->attributes->get('erp.schema.fp_checked') === true) {
            return;
        }

        try {
            $fingerprint = self::migrationsFingerprint();
        } catch (Throwable) {
            // Sem fingerprint confiável: não limpa (evita storm); próxima request tenta de novo.
            if (app()->bound('request')) {
                request()->attributes->set('erp.schema.fp_checked', true);
            }

            return;
        }

        if (self::$migrationsFingerprint !== null && self::$migrationsFingerprint !== $fingerprint) {
            self::$tables = [];
            self::$columnMaps = [];
        }

        self::$migrationsFingerprint = $fingerprint;

        if (app()->bound('request')) {
            request()->attributes->set('erp.schema.fp_checked', true);
        }
    }

    /**
     * Mesma ideia do PendingMigrationsGuard::quickFingerprint — barato e sensível a update.
     * Evita Schema::hasTable('migrations') (outro hit em information_schema).
     */
    private static function migrationsFingerprint(): string
    {
        $dir = database_path('migrations');
        $fileCount = is_dir($dir) ? count(glob($dir.DIRECTORY_SEPARATOR.'*.php') ?: []) : 0;

        try {
            $ranCount = (int) DB::table('migrations')->count();
        } catch (Throwable) {
            return $fileCount.':missing';
        }

        return $fileCount.':'.$ranCount;
    }

    private static function normalizeTable(string $table): string
    {
        $table = trim($table);
        $prefix = (string) (config('database.connections.'.config('database.default').'.prefix') ?? '');

        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            $logical = substr($table, strlen($prefix));

            return $logical !== '' ? $logical : $table;
        }

        return $table;
    }
}
