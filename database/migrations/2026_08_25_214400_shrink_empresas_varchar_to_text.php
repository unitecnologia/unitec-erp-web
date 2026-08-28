<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Libera row size em unitec_empresas (MySQL 65535) antes dos params iFood.
 * VARCHAR largo em utf8mb4 reserva até 4 bytes/char; TEXT conta só o ponteiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->isMysqlFamily() || ! Schema::hasTable('empresas')) {
            return;
        }

        $table = $this->empresasTable();

        try {
            DB::statement("ALTER TABLE `{$table}` ROW_FORMAT=DYNAMIC");
        } catch (\Throwable) {
            // Já DYNAMIC, ou engine antigo — segue convertendo VARCHAR.
        }

        $columns = $this->wideUnindexedVarcharColumns($table);

        if ($columns === []) {
            return;
        }

        $modifies = implode(', ', array_map(
            fn (string $column): string => "MODIFY `{$column}` TEXT NULL",
            $columns,
        ));

        try {
            DB::statement("ALTER TABLE `{$table}` {$modifies}");
        } catch (\Throwable) {
            foreach ($columns as $column) {
                try {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TEXT NULL");
                } catch (\Throwable) {
                    // Coluna já TEXT, ou conversão recusada — tenta as demais.
                }
            }
        }
    }

    public function down(): void
    {
        // Não restaura VARCHAR: voltar o tipo reestoura o limite de row size.
    }

    private function isMysqlFamily(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function empresasTable(): string
    {
        return (string) Schema::getConnection()->getTablePrefix().'empresas';
    }

    /**
     * @return list<string>
     */
    private function wideUnindexedVarcharColumns(string $table): array
    {
        $indexed = DB::select(
            'SELECT DISTINCT COLUMN_NAME AS column_name
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME IS NOT NULL',
            [$table],
        );

        $indexedNames = array_fill_keys(
            array_map(
                fn (object $row): string => (string) $row->column_name,
                $indexed,
            ),
            true,
        );

        $rows = DB::select(
            'SELECT COLUMN_NAME AS column_name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND DATA_TYPE IN (\'varchar\', \'char\')
               AND CHARACTER_MAXIMUM_LENGTH >= 64
             ORDER BY COLUMN_NAME',
            [$table],
        );

        $columns = [];

        foreach ($rows as $row) {
            $name = (string) $row->column_name;

            if ($name === '' || isset($indexedNames[$name])) {
                continue;
            }

            $columns[] = $name;
        }

        return $columns;
    }
};
