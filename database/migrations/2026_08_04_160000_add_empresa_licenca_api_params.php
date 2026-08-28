<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licença remota: só flags leves na empresa.
 * URL do portal fica nativa em config/unitec.php (evita varchar na unitec_empresas — row size).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_licenca_api_habilitar')) {
                $table->boolean('param_licenca_api_habilitar')->default(true);
            }

            if (! Schema::hasColumn('empresas', 'param_licenca_api_timeout')) {
                $table->unsignedTinyInteger('param_licenca_api_timeout')->default(8);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            foreach (['param_licenca_api_habilitar', 'param_licenca_api_timeout', 'param_licenca_api_url'] as $field) {
                if (Schema::hasColumn('empresas', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
};
