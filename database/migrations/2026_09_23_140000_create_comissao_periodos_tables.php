<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comissao_periodos')) {
            Schema::create('comissao_periodos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('vendedor_id')->constrained('vendedores')->cascadeOnDelete();
                $table->date('periodo_de');
                $table->date('periodo_ate');
                $table->string('status', 20)->default('aberta'); // aberta|fechada|paga|cancelada
                $table->decimal('base_avista', 14, 2)->default(0);
                $table->decimal('base_aprazo', 14, 2)->default(0);
                $table->decimal('comissao_avista', 14, 2)->default(0);
                $table->decimal('comissao_aprazo', 14, 2)->default(0);
                $table->decimal('comissao_total', 14, 2)->default(0);
                $table->decimal('percentual_av', 8, 2)->default(0);
                $table->decimal('percentual_ap', 8, 2)->default(0);
                $table->foreignId('credor_person_id')->nullable()->constrained('people')->nullOnDelete();
                $table->foreignId('conta_pagar_id')->nullable()->constrained('contas_pagar')->nullOnDelete();
                $table->timestamp('fechado_em')->nullable();
                $table->foreignId('fechado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelado_em')->nullable();
                $table->foreignId('cancelado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('motivo_cancelamento', 255)->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'vendedor_id', 'periodo_de', 'periodo_ate'], 'comissao_periodos_escopo_idx');
                $table->index(['status'], 'comissao_periodos_status_idx');
            });
        }

        if (! Schema::hasTable('comissao_periodo_vendas')) {
            Schema::create('comissao_periodo_vendas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('comissao_periodo_id')->constrained('comissao_periodos')->cascadeOnDelete();
                $table->foreignId('venda_id')->constrained('vendas')->restrictOnDelete();
                $table->date('data');
                $table->decimal('base', 14, 2)->default(0);
                $table->string('tipo', 10); // av|ap
                $table->decimal('percentual', 8, 2)->default(0);
                $table->decimal('comissao', 14, 2)->default(0);
                $table->timestamps();

                $table->unique(['comissao_periodo_id', 'venda_id'], 'comissao_periodo_vendas_uq');
                $table->index(['venda_id'], 'comissao_periodo_vendas_venda_idx');
            });
        }

        // Reserva ativa: unique(venda_id) impede comissão duplicada sob concorrência.
        if (! Schema::hasTable('comissao_venda_ativa')) {
            Schema::create('comissao_venda_ativa', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('vendedor_id')->constrained('vendedores')->cascadeOnDelete();
                $table->foreignId('venda_id')->constrained('vendas')->restrictOnDelete();
                $table->foreignId('comissao_periodo_id')->constrained('comissao_periodos')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['venda_id'], 'comissao_venda_ativa_venda_uq');
                $table->index(['empresa_id', 'vendedor_id'], 'comissao_venda_ativa_escopo_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comissao_venda_ativa');
        Schema::dropIfExists('comissao_periodo_vendas');
        Schema::dropIfExists('comissao_periodos');
    }
};
