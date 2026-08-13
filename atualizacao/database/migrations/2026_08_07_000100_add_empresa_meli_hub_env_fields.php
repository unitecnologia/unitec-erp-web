<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            // Boolean já cabe; URLs em TEXT para não estourar row size InnoDB (65535).
            if (! Schema::hasColumn('empresas', 'param_meli_is_hub')) {
                $table->boolean('param_meli_is_hub')->default(false);
            }

            if (! Schema::hasColumn('empresas', 'param_meli_app_url')) {
                $table->text('param_meli_app_url')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_meli_hub_url')) {
                $table->text('param_meli_hub_url')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            foreach (['param_meli_is_hub', 'param_meli_app_url', 'param_meli_hub_url'] as $column) {
                if (Schema::hasColumn('empresas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
