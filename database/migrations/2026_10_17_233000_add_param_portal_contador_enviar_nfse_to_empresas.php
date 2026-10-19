<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag independente: Enviar NFS-e ao Portal do Contador.
 * Nullable = não sobrescreve preferências antigas; null trata-se como habilitado (default de novas configs).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('empresas', 'param_portal_contador_enviar_nfse')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $table->boolean('param_portal_contador_enviar_nfse')
                ->nullable()
                ->after('param_portal_contador_enviar_vendas');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('empresas', 'param_portal_contador_enviar_nfse')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $table->dropColumn('param_portal_contador_enviar_nfse');
        });
    }
};
