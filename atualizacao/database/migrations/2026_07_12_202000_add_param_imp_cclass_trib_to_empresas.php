<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_imp_cclass_trib')) {
                $table->string('param_imp_cclass_trib', 40)->nullable()->default(null);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'param_imp_cclass_trib')) {
                $table->dropColumn('param_imp_cclass_trib');
            }
        });
    }
};
