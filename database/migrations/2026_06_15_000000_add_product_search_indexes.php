<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['ativo', 'codigo'], 'products_ativo_codigo_index');
            $table->index('codigo_barras', 'products_codigo_barras_index');
            $table->index('referencia', 'products_referencia_index');
            $table->index(['ativo', 'descricao'], 'products_ativo_descricao_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_ativo_codigo_index');
            $table->dropIndex('products_codigo_barras_index');
            $table->dropIndex('products_referencia_index');
            $table->dropIndex('products_ativo_descricao_index');
        });
    }
};
