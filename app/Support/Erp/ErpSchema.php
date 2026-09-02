<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\Schema;

/**
 * Memoiza Schema::hasTable / hasColumn no processo PHP.
 * hasColumn carrega o listing da tabela UMA vez (evita N hits em information_schema).
 */
final class ErpSchema
{
    /** @var array<string, bool> */
    private static array $tables = [];

    /** @var array<string, array<string, true>> */
    private static array $columnMaps = [];

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

    public static function flush(): void
    {
        self::$tables = [];
        self::$columnMaps = [];
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
