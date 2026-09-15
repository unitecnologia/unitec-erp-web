<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordens_servico', function (Blueprint $table) {
            $table->uuid('app_local_uuid')->nullable()->after('codigo_legado');
            $table->string('device_uuid', 120)->nullable()->after('app_local_uuid');
            $table->unique(['empresa_id', 'app_local_uuid'], 'os_empresa_app_local_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ordens_servico', function (Blueprint $table) {
            $table->dropUnique('os_empresa_app_local_uuid_unique');
            $table->dropColumn(['app_local_uuid', 'device_uuid']);
        });
    }
};
