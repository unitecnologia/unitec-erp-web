<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_vendas', function (Blueprint $table) {
            $table->foreignId('venda_id')
                ->nullable()
                ->after('orcamento_id')
                ->constrained('vendas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdv_vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venda_id');
        });
    }
};
