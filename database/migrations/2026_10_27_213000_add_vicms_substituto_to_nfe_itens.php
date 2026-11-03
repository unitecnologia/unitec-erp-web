<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Campo opcional do grupo ICMS60 / ICMSSN500 (vICMSSubstituto). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfe_itens', function (Blueprint $table) {
            if (! Schema::hasColumn('nfe_itens', 'v_icms_substituto')) {
                $table->decimal('v_icms_substituto', 15, 2)->default(0)->after('valor_icms_st_ret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nfe_itens', function (Blueprint $table) {
            if (Schema::hasColumn('nfe_itens', 'v_icms_substituto')) {
                $table->dropColumn('v_icms_substituto');
            }
        });
    }
};
