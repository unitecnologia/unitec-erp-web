<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'acesso_app_inventario')) {
                $table->boolean('acesso_app_inventario')->default(false)->after('acesso_app_gestao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'acesso_app_inventario')) {
                $table->dropColumn('acesso_app_inventario');
            }
        });
    }
};
