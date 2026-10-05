<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'nfse_aliquota_iss')) {
                $table->decimal('nfse_aliquota_iss', 5, 2)->nullable()->after('nfse_tipo_rps');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'nfse_aliquota_iss')) {
                $table->dropColumn('nfse_aliquota_iss');
            }
        });
    }
};
