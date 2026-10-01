<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('estoque_movimentacoes')) {
            return;
        }

        Schema::create('estoque_movimentacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('produto_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('estoque_id')->nullable()->constrained('estoques')->nullOnDelete();
            $table->dateTime('data_movimentacao');
            $table->string('tipo', 40);
            $table->decimal('quantidade', 12, 3);
            $table->decimal('saldo_anterior', 12, 3);
            $table->decimal('saldo_atual', 12, 3);
            $table->string('origem_tipo', 40)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observacao', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['produto_id', 'empresa_id', 'data_movimentacao'], 'estoque_mov_prod_emp_data_idx');
            $table->index('tipo', 'estoque_mov_tipo_idx');
            $table->index(['origem_tipo', 'origem_id'], 'estoque_mov_origem_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_movimentacoes');
    }
};
