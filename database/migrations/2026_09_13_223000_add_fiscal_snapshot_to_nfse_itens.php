<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfse_itens', function (Blueprint $table): void {
            $table->string('c_trib_nac', 6)->nullable()->after('total');
            $table->string('c_nbs', 9)->nullable()->after('c_trib_nac');
            $table->string('c_trib_mun', 20)->nullable()->after('c_nbs');
            $table->string('c_ind_op', 6)->nullable()->after('c_trib_mun');
        });
    }

    public function down(): void
    {
        Schema::table('nfse_itens', function (Blueprint $table): void {
            $table->dropColumn(['c_trib_nac', 'c_nbs', 'c_trib_mun', 'c_ind_op']);
        });
    }
};
