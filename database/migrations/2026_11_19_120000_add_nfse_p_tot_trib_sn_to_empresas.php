<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'nfse_p_tot_trib_sn')) {
                $table->decimal('nfse_p_tot_trib_sn', 5, 2)->nullable()->after('nfse_aliquota_iss');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'nfse_p_tot_trib_sn')) {
                $table->dropColumn('nfse_p_tot_trib_sn');
            }
        });
    }
};
