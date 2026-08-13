<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminais', function (Blueprint $table) {
            if (! Schema::hasColumn('terminais', 'usar_device_service')) {
                $table->boolean('usar_device_service')->default(false)->after('usa_gaveta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('terminais', function (Blueprint $table) {
            if (Schema::hasColumn('terminais', 'usar_device_service')) {
                $table->dropColumn('usar_device_service');
            }
        });
    }
};
