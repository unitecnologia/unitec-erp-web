<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresas') || Schema::hasColumn('empresas', 'param_portal_bkp_token')) {
            return;
        }

        $table = (string) Schema::getConnection()->getTablePrefix().'empresas';
        DB::statement("ALTER TABLE `{$table}` ADD `param_portal_bkp_token` TEXT NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresas') || ! Schema::hasColumn('empresas', 'param_portal_bkp_token')) {
            return;
        }

        $table = (string) Schema::getConnection()->getTablePrefix().'empresas';
        DB::statement("ALTER TABLE `{$table}` DROP COLUMN `param_portal_bkp_token`");
    }
};
