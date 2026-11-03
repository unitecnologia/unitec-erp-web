<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abertura/fechamento do Livro Caixa (por empresa + conta).
 * Independente de pdv_caixa_sessoes. Não altera lançamentos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Reexecução segura se a tentativa anterior criou tabelas sem FKs (nome longo).
        if (Schema::hasTable('caixa_livro_movimento_eventos') && ! Schema::hasTable('migrations')) {
            // no-op safeguard
        }

        if (Schema::hasTable('caixa_livro_movimento_eventos')
            && ! $this->hasForeignKey('caixa_livro_movimento_eventos', 'clm_evt_mov_fk')) {
            Schema::dropIfExists('caixa_livro_movimento_eventos');
        }

        if (Schema::hasTable('caixa_livro_movimentos')
            && ! Schema::hasTable('caixa_livro_movimento_eventos')
            && ! $this->migrationJaRegistrada()) {
            // Tabelas órfãs da falha anterior: recria limpo.
            Schema::dropIfExists('caixa_livro_movimentos');
        }

        if (! Schema::hasTable('caixa_livro_movimentos')) {
            Schema::create('caixa_livro_movimentos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->unsignedBigInteger('caixa_conta_id');
                $table->string('status', 20)->default('aberto'); // aberto|fechado

                $table->timestamp('aberto_em');
                $table->unsignedBigInteger('aberto_por');
                $table->decimal('saldo_abertura', 15, 2)->default(0);
                $table->decimal('troco_inicial', 15, 2)->default(0);
                $table->string('observacao_abertura', 500)->nullable();

                $table->timestamp('fechado_em')->nullable();
                $table->unsignedBigInteger('fechado_por')->nullable();
                $table->decimal('total_entradas', 15, 2)->nullable();
                $table->decimal('total_saidas', 15, 2)->nullable();
                $table->decimal('total_transferencias_entrada', 15, 2)->nullable();
                $table->decimal('total_transferencias_saida', 15, 2)->nullable();
                $table->decimal('saldo_calculado', 15, 2)->nullable();
                $table->decimal('valor_contado', 15, 2)->nullable();
                $table->decimal('diferenca', 15, 2)->nullable();
                $table->string('observacao_fechamento', 500)->nullable();

                $table->timestamp('reaberto_em')->nullable();
                $table->unsignedBigInteger('reaberto_por')->nullable();
                $table->string('motivo_reabertura', 500)->nullable();

                $table->timestamps();

                $table->foreign('caixa_conta_id', 'clm_conta_fk')
                    ->references('id')->on('caixa_contas')->restrictOnDelete();
                $table->foreign('aberto_por', 'clm_aberto_por_fk')
                    ->references('id')->on('users')->restrictOnDelete();
                $table->foreign('fechado_por', 'clm_fechado_por_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('reaberto_por', 'clm_reaberto_por_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->index(['empresa_id', 'caixa_conta_id', 'status'], 'clm_emp_conta_status_idx');
                $table->index(['empresa_id', 'caixa_conta_id', 'aberto_em'], 'clm_emp_conta_aber_idx');
                $table->index(['fechado_em'], 'clm_fechado_em_idx');
            });
        }

        if (! Schema::hasTable('caixa_livro_movimento_eventos')) {
            Schema::create('caixa_livro_movimento_eventos', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('caixa_livro_movimento_id');
                $table->string('tipo', 30); // abrir|fechar|reabrir|imprimir
                $table->unsignedBigInteger('user_id')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('caixa_livro_movimento_id', 'clm_evt_mov_fk')
                    ->references('id')->on('caixa_livro_movimentos')->cascadeOnDelete();
                $table->foreign('user_id', 'clm_evt_user_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->index(['caixa_livro_movimento_id', 'tipo'], 'clm_evt_mov_tipo_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caixa_livro_movimento_eventos');
        Schema::dropIfExists('caixa_livro_movimentos');
    }

    private function migrationJaRegistrada(): bool
    {
        if (! Schema::hasTable('migrations')) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('migrations')
            ->where('migration', '2026_11_01_234500_create_caixa_livro_movimentos_table')
            ->exists();
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        try {
            $db = \Illuminate\Support\Facades\DB::getDatabaseName();
            $prefix = \Illuminate\Support\Facades\DB::getTablePrefix();
            $full = $prefix.$table;

            return \Illuminate\Support\Facades\DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', $db)
                ->where('TABLE_NAME', $full)
                ->where('CONSTRAINT_NAME', $name)
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }
};
