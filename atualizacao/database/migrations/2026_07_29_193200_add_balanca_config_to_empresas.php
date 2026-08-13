<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_balanca_modelo')) {
                $table->string('param_balanca_modelo', 32)->nullable()->after('param_ui_density');
            }

            if (! Schema::hasColumn('empresas', 'param_balanca_diretorio')) {
                $table->string('param_balanca_diretorio', 500)->nullable()->after('param_balanca_modelo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            foreach (['param_balanca_diretorio', 'param_balanca_modelo'] as $column) {
                if (Schema::hasColumn('empresas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
