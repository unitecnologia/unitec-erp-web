<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compras') && ! Schema::hasColumn('compras', 'codigo_legado')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->unsignedInteger('codigo_legado')->nullable()->after('id');
                $table->unique('codigo_legado');
            });
        }

        if (Schema::hasTable('notas_fornecedores') && ! Schema::hasColumn('notas_fornecedores', 'codigo_legado')) {
            Schema::table('notas_fornecedores', function (Blueprint $table) {
                $table->unsignedInteger('codigo_legado')->nullable()->after('id');
                $table->unique('codigo_legado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('compras') && Schema::hasColumn('compras', 'codigo_legado')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->dropUnique(['codigo_legado']);
                $table->dropColumn('codigo_legado');
            });
        }

        if (Schema::hasTable('notas_fornecedores') && Schema::hasColumn('notas_fornecedores', 'codigo_legado')) {
            Schema::table('notas_fornecedores', function (Blueprint $table) {
                $table->dropUnique(['codigo_legado']);
                $table->dropColumn('codigo_legado');
            });
        }
    }
};
