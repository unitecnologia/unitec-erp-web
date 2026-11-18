<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preserva hora de DTPOSTED quando o OFX traz HHMMSS.
 * Linhas já importadas só-data viram 00:00:00 (exibição continua DD/MM/AAAA).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->alterExtratoDatas('datetime');
    }

    public function down(): void
    {
        $this->alterExtratoDatas('date');
    }

    private function alterExtratoDatas(string $tipo): void
    {
        if (! Schema::hasTable('extrato_lancamentos')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $table = Schema::getConnection()->getTablePrefix().'extrato_lancamentos';
        $movimento = $tipo === 'datetime' ? 'DATETIME NOT NULL' : 'DATE NOT NULL';
        $posted = $tipo === 'datetime' ? 'DATETIME NULL' : 'DATE NULL';

        DB::statement("ALTER TABLE `{$table}` MODIFY data_movimento {$movimento}");
        if (Schema::hasColumn('extrato_lancamentos', 'data_posted')) {
            DB::statement("ALTER TABLE `{$table}` MODIFY data_posted {$posted}");
        }
    }
};
