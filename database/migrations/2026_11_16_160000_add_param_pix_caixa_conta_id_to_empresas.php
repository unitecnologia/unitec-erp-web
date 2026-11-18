<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresas') || Schema::hasColumn('empresas', 'param_pix_caixa_conta_id')) {
            return;
        }

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $table = Schema::getConnection()->getTablePrefix().'empresas';
            try {
                DB::statement("ALTER TABLE `{$table}` ROW_FORMAT=DYNAMIC");
            } catch (\Throwable) {
                // Já DYNAMIC ou engine antigo.
            }
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $table->unsignedBigInteger('param_pix_caixa_conta_id')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresas') || ! Schema::hasColumn('empresas', 'param_pix_caixa_conta_id')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $table->dropColumn('param_pix_caixa_conta_id');
        });
    }
};
