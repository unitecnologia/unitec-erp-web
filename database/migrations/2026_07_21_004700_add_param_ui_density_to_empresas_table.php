<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_ui_density')) {
                $table->string('param_ui_density', 16)->default('normal')->after('param_backup_ultimo_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'param_ui_density')) {
                $table->dropColumn('param_ui_density');
            }
        });
    }
};
