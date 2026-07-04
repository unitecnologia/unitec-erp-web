<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_pix_provedor')) {
                $table->string('param_pix_provedor', 40)->nullable()->default('mercadopago');
            }

            if (! Schema::hasColumn('empresas', 'param_pix_mp_access_token')) {
                $table->text('param_pix_mp_access_token')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['param_pix_provedor', 'param_pix_mp_access_token']);
        });
    }
};
