<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C3 — Vínculo e auditoria da conciliação manual 1:1.
 * Não altera caixa_lancamentos, CR/CP, PDV nem saldos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extrato_conciliacoes')) {
            Schema::create('extrato_conciliacoes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->index();
                $table->unsignedBigInteger('caixa_conta_id')->index();
                $table->unsignedBigInteger('extrato_lancamento_id')->index();
                $table->unsignedBigInteger('caixa_lancamento_id')->index();

                // ativa | revertida
                $table->string('status', 20)->default('ativa')->index();

                $table->string('tipo_extrato', 16);
                $table->decimal('valor_extrato', 14, 2);
                $table->decimal('valor_caixa', 14, 2);
                $table->date('data_movimento_extrato');
                $table->date('data_emissao_caixa')->nullable();

                $table->string('historico_extrato', 500)->nullable();
                $table->string('fitid', 120)->nullable();
                $table->string('documento_caixa', 40)->nullable();
                $table->string('historico_caixa', 500)->nullable();

                $table->unsignedBigInteger('conciliado_por_user_id')->nullable()->index();
                $table->timestamp('conciliado_em')->nullable();

                $table->unsignedBigInteger('revertido_por_user_id')->nullable()->index();
                $table->timestamp('revertido_em')->nullable();
                $table->string('motivo_reversao', 255)->nullable();

                // Tokens únicos só enquanto ativa (NULL após reversão permite novo vínculo).
                $table->string('lock_extrato', 32)->nullable();
                $table->string('lock_caixa', 32)->nullable();

                $table->timestamps();

                $table->unique('lock_extrato', 'extrato_conc_lock_extrato_uq');
                $table->unique('lock_caixa', 'extrato_conc_lock_caixa_uq');
                $table->index(
                    ['empresa_id', 'caixa_conta_id', 'status'],
                    'extrato_conc_emp_conta_status_idx'
                );
                $table->index(
                    ['extrato_lancamento_id', 'status'],
                    'extrato_conc_extrato_status_idx'
                );
            });
        }

        // 1 caixa_lancamento ↔ no máximo 1 extrato ativo (NULLs repetidos ok no UNIQUE).
        if (Schema::hasTable('extrato_lancamentos')
            && Schema::hasColumn('extrato_lancamentos', 'caixa_lancamento_id')
            && ! $this->hasIndex('extrato_lancamentos', 'extrato_lanc_caixa_lanc_uq')
        ) {
            Schema::table('extrato_lancamentos', function (Blueprint $table) {
                $table->unique('caixa_lancamento_id', 'extrato_lanc_caixa_lanc_uq');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('extrato_lancamentos')
            && $this->hasIndex('extrato_lancamentos', 'extrato_lanc_caixa_lanc_uq')
        ) {
            Schema::table('extrato_lancamentos', function (Blueprint $table) {
                $table->dropUnique('extrato_lanc_caixa_lanc_uq');
            });
        }

        Schema::dropIfExists('extrato_conciliacoes');
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $indexes = $sm->getIndexes($table);

            foreach ($indexes as $index) {
                if (($index['name'] ?? '') === $indexName) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
};
