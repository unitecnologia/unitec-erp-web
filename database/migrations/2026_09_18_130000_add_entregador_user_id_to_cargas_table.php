<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargas', function (Blueprint $table): void {
            $table->foreignId('entregador_user_id')
                ->nullable()
                ->after('motorista_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['empresa_id', 'entregador_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('cargas', function (Blueprint $table): void {
            $table->dropForeign(['entregador_user_id']);
            $table->dropIndex(['empresa_id', 'entregador_user_id', 'status']);
            $table->dropColumn('entregador_user_id');
        });
    }
};
