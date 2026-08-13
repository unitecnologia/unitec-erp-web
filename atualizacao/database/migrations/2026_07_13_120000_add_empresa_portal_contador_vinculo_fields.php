<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_portal_contador_vinculo_id')) {
                $table->string('param_portal_contador_vinculo_id', 64)->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_portal_contador_contador_nome_portal')) {
                $table->string('param_portal_contador_contador_nome_portal', 120)->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_portal_contador_vinculado_em')) {
                $table->timestamp('param_portal_contador_vinculado_em')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            foreach ([
                'param_portal_contador_vinculo_id',
                'param_portal_contador_contador_nome_portal',
                'param_portal_contador_vinculado_em',
            ] as $field) {
                if (Schema::hasColumn('empresas', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
};
