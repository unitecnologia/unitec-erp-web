<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->unsignedBigInteger('conta_destino_id')->nullable()->after('descricao');
            $table->decimal('taxa_cartao', 8, 2)->default(0)->after('tipo');
            $table->unsignedSmallInteger('prazo_cartao')->default(0)->after('taxa_cartao');
            $table->unsignedSmallInteger('max_parcelas')->default(1)->after('prazo_cartao');
            $table->unsignedSmallInteger('intervalo_parcelas')->default(30)->after('max_parcelas');
            $table->string('atalho', 5)->nullable()->after('intervalo_parcelas');
            $table->string('tipo_movimento', 20)->default('nenhum')->after('atalho');
            $table->boolean('usa_tef')->default(false)->after('tipo_movimento');
            $table->boolean('usa_super_tef')->default(false)->after('usa_tef');
            $table->boolean('aparece_venda')->default(true)->after('usa_super_tef');
            $table->boolean('aparece_contas_receber')->default(false)->after('aparece_venda');

            $table->index('conta_destino_id');
        });
    }

    public function down(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->dropIndex(['conta_destino_id']);
            $table->dropColumn([
                'conta_destino_id',
                'taxa_cartao',
                'prazo_cartao',
                'max_parcelas',
                'intervalo_parcelas',
                'atalho',
                'tipo_movimento',
                'usa_tef',
                'usa_super_tef',
                'aparece_venda',
                'aparece_contas_receber',
            ]);
        });
    }
};
