<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendas')) {
            return;
        }

        Schema::table('vendas', function (Blueprint $table) {
            if (! Schema::hasColumn('vendas', 'requer_entrega')) {
                $table->boolean('requer_entrega')->default(false)->after('plataforma');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendas')) {
            return;
        }

        Schema::table('vendas', function (Blueprint $table) {
            if (Schema::hasColumn('vendas', 'requer_entrega')) {
                $table->dropColumn('requer_entrega');
            }
        });
    }
};
