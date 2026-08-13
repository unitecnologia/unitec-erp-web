<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campos CF em TEXT para evitar row size no MySQL (empresas já tem muitas colunas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        $table = $this->empresasTable();

        foreach ([
            'param_cf_api_token',
            'param_cf_account_id',
            'param_cf_zone_id',
            'param_cf_base_domain',
            'param_cf_subdomain',
            'param_cf_tunnel_id',
            'param_cf_hostname',
        ] as $field) {
            if (Schema::hasColumn('empresas', $field)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$field}` TEXT NULL");

                continue;
            }

            DB::statement("ALTER TABLE `{$table}` ADD `{$field}` TEXT NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        $table = $this->empresasTable();

        foreach ([
            'param_cf_api_token',
            'param_cf_account_id',
            'param_cf_zone_id',
            'param_cf_base_domain',
            'param_cf_subdomain',
            'param_cf_tunnel_id',
            'param_cf_hostname',
        ] as $field) {
            if (Schema::hasColumn('empresas', $field)) {
                DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$field}`");
            }
        }
    }

    private function empresasTable(): string
    {
        $prefix = (string) Schema::getConnection()->getTablePrefix();

        return $prefix.'empresas';
    }
};
