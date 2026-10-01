<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_boleto_pos_vencimento')) {
                $table->string('param_boleto_pos_vencimento', 32)->nullable()->after('param_boleto_protesto_dias');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_boleto_pos_vencimento')) {
                $table->dropColumn('param_boleto_pos_vencimento');
            }
        });
    }
};
