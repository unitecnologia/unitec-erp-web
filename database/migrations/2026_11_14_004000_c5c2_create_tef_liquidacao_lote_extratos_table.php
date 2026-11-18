<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C5C.2 — Vínculo de controle lote TEF ↔ crédito OFX.
 * Não cria movimento financeiro nem altera C3/PDV/TEF.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tef_liquidacao_lote_extratos')) {
            Schema::create('tef_liquidacao_lote_extratos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->index();
                $table->unsignedBigInteger('caixa_conta_id')->index();
                $table->unsignedBigInteger('lote_id')->index();
                $table->unsignedBigInteger('extrato_lancamento_id')->index();

                $table->decimal('valor_vinculado', 15, 2);
                $table->decimal('valor_lote_liquido_ref', 15, 2);
                $table->decimal('valor_extrato_ref', 15, 2);
                $table->decimal('diferenca', 15, 2)->default(0);
                $table->string('diferenca_motivo', 255)->nullable();

                // ativo | revertido
                $table->string('status', 20)->default('ativo')->index();

                $table->unsignedBigInteger('vinculado_por_user_id')->nullable()->index();
                $table->timestamp('vinculado_em')->nullable();
                $table->unsignedBigInteger('revertido_por_user_id')->nullable()->index();
                $table->timestamp('revertido_em')->nullable();
                $table->string('motivo_reversao', 255)->nullable();

                // Lock enquanto ativo (NULL após reversão libera novo vínculo parcial).
                $table->string('lock_token', 64)->nullable();

                $table->timestamps();

                $table->unique('lock_token', 'tef_liq_lote_ext_lock_uq');
                $table->index(
                    ['extrato_lancamento_id', 'status'],
                    'tef_liq_lote_ext_extrato_status_idx'
                );
                $table->index(
                    ['lote_id', 'status'],
                    'tef_liq_lote_ext_lote_status_idx'
                );
                $table->index(
                    ['empresa_id', 'caixa_conta_id', 'status'],
                    'tef_liq_lote_ext_emp_conta_status_idx'
                );
            });
        }

        if (Schema::hasTable('tef_liquidacao_lotes')
            && ! Schema::hasColumn('tef_liquidacao_lotes', 'ofx_situacao')
        ) {
            Schema::table('tef_liquidacao_lotes', function (Blueprint $table) {
                $table->string('ofx_situacao', 24)
                    ->default('pendente')
                    ->after('situacao')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tef_liquidacao_lotes')
            && Schema::hasColumn('tef_liquidacao_lotes', 'ofx_situacao')
        ) {
            Schema::table('tef_liquidacao_lotes', function (Blueprint $table) {
                $table->dropColumn('ofx_situacao');
            });
        }

        Schema::dropIfExists('tef_liquidacao_lote_extratos');
    }
};
