<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cliente_credito_movimentacoes')) {
            return;
        }

        Schema::create('cliente_credito_movimentacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('cliente_id')->constrained('people')->cascadeOnDelete();
            $table->dateTime('data_movimentacao');
            $table->string('tipo', 20);
            $table->decimal('valor', 15, 2);
            $table->smallInteger('sinal');
            $table->decimal('saldo_anterior', 15, 2);
            $table->decimal('saldo_atual', 15, 2);
            $table->string('origem_tipo', 40)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->string('origem_numero', 40)->nullable();
            $table->foreignId('estorna_id')->nullable()->constrained('cliente_credito_movimentacoes')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observacao', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cliente_id', 'empresa_id', 'data_movimentacao'], 'cli_cred_mov_cli_emp_data_idx');
            $table->index('tipo', 'cli_cred_mov_tipo_idx');
            $table->index(['origem_tipo', 'origem_id'], 'cli_cred_mov_origem_idx');
            $table->unique('estorna_id', 'cli_cred_mov_estorna_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_credito_movimentacoes');
    }
};
