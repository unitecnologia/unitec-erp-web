<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('referencia')->nullable()->after('codigo');
            $table->string('codigo_barras')->nullable()->after('referencia');
            $table->string('grupo')->default('DIVERSOS')->after('descricao');
            $table->string('localizacao')->nullable()->after('estoque');
            $table->date('validade')->nullable()->after('localizacao');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'referencia',
                'codigo_barras',
                'grupo',
                'localizacao',
                'validade',
            ]);
        });
    }
};
