<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_erp_public_url')) {
                $table->text('param_erp_public_url')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_gestor_public_url')) {
                $table->text('param_gestor_public_url')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_gestor_public_url')) {
                $table->dropColumn('param_gestor_public_url');
            }

            if (Schema::hasColumn('empresas', 'param_erp_public_url')) {
                $table->dropColumn('param_erp_public_url');
            }
        });
    }
};
