<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'nfse_reg_esp_trib')) {
                $table->string('nfse_reg_esp_trib', 1)->nullable()->after('regime_tributario');
            }

            if (! Schema::hasColumn('empresas', 'nfse_reg_ap_trib_sn')) {
                $table->string('nfse_reg_ap_trib_sn', 1)->nullable()->after('nfse_reg_esp_trib');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'nfse_reg_ap_trib_sn')) {
                $table->dropColumn('nfse_reg_ap_trib_sn');
            }

            if (Schema::hasColumn('empresas', 'nfse_reg_esp_trib')) {
                $table->dropColumn('nfse_reg_esp_trib');
            }
        });
    }
};
