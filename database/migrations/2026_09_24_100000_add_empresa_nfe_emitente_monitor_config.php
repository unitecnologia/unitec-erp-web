<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag + pivot: Matriz autoriza empresas emitentes de NF-e no Monitor.
 * Não altera emissão — só infraestrutura/configuração.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_monitor_vendas_escolher_empresa_emitente_nfe')) {
                $table->boolean('param_monitor_vendas_escolher_empresa_emitente_nfe')->default(false);
            }
        });

        if (! Schema::hasTable('empresa_nfe_emitente')) {
            Schema::create('empresa_nfe_emitente', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('empresa_id')
                    ->constrained('empresas')
                    ->cascadeOnDelete();
                $table->foreignId('emitente_empresa_id')
                    ->constrained('empresas')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['empresa_id', 'emitente_empresa_id'], 'empresa_nfe_emitente_unique');
                $table->index('emitente_empresa_id', 'empresa_nfe_emitente_emitente_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_nfe_emitente');

        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_monitor_vendas_escolher_empresa_emitente_nfe')) {
                $table->dropColumn('param_monitor_vendas_escolher_empresa_emitente_nfe');
            }
        });
    }
};
