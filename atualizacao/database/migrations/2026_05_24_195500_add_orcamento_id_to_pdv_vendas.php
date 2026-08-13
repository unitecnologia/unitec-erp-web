<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_vendas', function (Blueprint $table) {
            $table->foreignId('orcamento_id')
                ->nullable()
                ->after('user_id')
                ->constrained('orcamentos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdv_vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orcamento_id');
        });
    }
};
