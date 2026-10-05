<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_api_servicos_serper_url')) {
                $table->text('param_api_servicos_serper_url')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_api_servicos_serper_key')) {
                $table->text('param_api_servicos_serper_key')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'param_api_servicos_serper_url')) {
                $table->dropColumn('param_api_servicos_serper_url');
            }

            if (Schema::hasColumn('empresas', 'param_api_servicos_serper_key')) {
                $table->dropColumn('param_api_servicos_serper_key');
            }
        });
    }
};
