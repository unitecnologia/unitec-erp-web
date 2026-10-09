<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesas do PDV (restaurante). Uma linha por mesa usada na empresa, criada sob demanda.
 * Itens ficam em JSON até o fechamento normal do PDV; nada aqui gera venda, estoque,
 * financeiro ou NFC-e. reserva_token/reservado_ate implementam a edição exclusiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_mesas')) {
            Schema::create('pdv_mesas', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedSmallInteger('numero');
                $table->unsignedInteger('qtd_itens')->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->longText('itens')->nullable();
                $table->timestamp('aberta_em')->nullable();
                $table->string('reserva_token', 64)->nullable();
                $table->unsignedBigInteger('reservado_user_id')->nullable();
                $table->unsignedBigInteger('reservado_terminal_id')->nullable();
                $table->string('reservado_nome', 120)->nullable();
                $table->timestamp('reservado_ate')->nullable();
                $table->unsignedBigInteger('ultima_pdv_venda_id')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'numero'], 'pdv_mesas_empresa_numero_uq');
            });
        }

        if (Schema::hasTable('empresas') && ! Schema::hasColumn('empresas', 'param_pdv_qtd_mesas')) {
            Schema::table('empresas', function (Blueprint $table): void {
                $table->unsignedSmallInteger('param_pdv_qtd_mesas')->default(20);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('empresas') && Schema::hasColumn('empresas', 'param_pdv_qtd_mesas')) {
            Schema::table('empresas', function (Blueprint $table): void {
                $table->dropColumn('param_pdv_qtd_mesas');
            });
        }

        Schema::dropIfExists('pdv_mesas');
    }
};
