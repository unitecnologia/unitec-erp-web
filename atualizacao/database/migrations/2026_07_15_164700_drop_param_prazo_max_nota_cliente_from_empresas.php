<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'param_prazo_max_nota_cliente')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('param_prazo_max_nota_cliente');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresas', 'param_prazo_max_nota_cliente')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table) {
            $table->decimal('param_prazo_max_nota_cliente', 12, 2)->default('1.00');
        });
    }
};
