<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_pix_certificado_senha')) {
                $table->string('param_pix_certificado_senha', 255)->nullable()->after('param_pix_certificado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_pix_certificado_senha')) {
                $table->dropColumn('param_pix_certificado_senha');
            }
        });
    }
};
