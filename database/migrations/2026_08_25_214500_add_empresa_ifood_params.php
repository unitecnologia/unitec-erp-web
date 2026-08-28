<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parâmetros API iFood em TEXT (evita row size no MySQL).
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
            'param_ifood_habilitar',
            'param_ifood_client_id',
            'param_ifood_client_secret',
            'param_ifood_merchant_id',
            'param_ifood_ambiente',
            'param_ifood_access_token',
            'param_ifood_refresh_token',
            'param_ifood_webhook_secret',
        ] as $field) {
            if (Schema::hasColumn('empresas', $field)) {
                continue;
            }

            if ($field === 'param_ifood_habilitar') {
                DB::statement("ALTER TABLE `{$table}` ADD `{$field}` TINYINT(1) NOT NULL DEFAULT 0");

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
            'param_ifood_habilitar',
            'param_ifood_client_id',
            'param_ifood_client_secret',
            'param_ifood_merchant_id',
            'param_ifood_ambiente',
            'param_ifood_access_token',
            'param_ifood_refresh_token',
            'param_ifood_webhook_secret',
        ] as $field) {
            if (Schema::hasColumn('empresas', $field)) {
                DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$field}`");
            }
        }
    }

    private function empresasTable(): string
    {
        return (string) Schema::getConnection()->getTablePrefix().'empresas';
    }
};
