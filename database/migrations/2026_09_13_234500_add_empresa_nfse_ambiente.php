<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'nfse_ambiente')) {
                $table->string('nfse_ambiente', 20)->nullable()->after('nfse_reg_ap_trib_sn');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'nfse_ambiente')) {
                $table->dropColumn('nfse_ambiente');
            }
        });
    }
};
