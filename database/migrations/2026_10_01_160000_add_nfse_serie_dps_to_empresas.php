<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'nfse_serie_dps')) {
                $table->string('nfse_serie_dps', 5)->nullable()->after('nfse_ambiente');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'nfse_serie_dps')) {
                $table->dropColumn('nfse_serie_dps');
            }
        });
    }
};
