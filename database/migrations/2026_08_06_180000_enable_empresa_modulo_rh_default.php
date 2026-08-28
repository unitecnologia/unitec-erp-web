<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'param_modulo_rh')) {
            return;
        }

        // RH já tem telas reais; o flag ficou 0 sem UI para ligar e escondia menu mesmo de admin.
        DB::table('empresas')->where('param_modulo_rh', false)->update(['param_modulo_rh' => true]);

        $driver = Schema::getConnection()->getDriverName();
        $table = Schema::getConnection()->getTablePrefix().'empresas';

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `{$table}` MODIFY param_modulo_rh TINYINT(1) NOT NULL DEFAULT 1");
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('empresas', 'param_modulo_rh')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $table = Schema::getConnection()->getTablePrefix().'empresas';

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `{$table}` MODIFY param_modulo_rh TINYINT(1) NOT NULL DEFAULT 0");
        }
    }
};
