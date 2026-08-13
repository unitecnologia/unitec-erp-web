<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 80);
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('numero_loja')->nullable();
            $table->unsignedInteger('empresa_ativa')->nullable();
            $table->unsignedInteger('numero_logico_terminal')->nullable();

            $table->boolean('eh_caixa')->default(true);
            $table->boolean('pdv')->default(true);
            $table->boolean('restaurante')->default(false);
            $table->boolean('delivery')->default(false);
            $table->boolean('logado')->default(false);
            $table->boolean('usa_tef')->default(false);
            $table->boolean('usa_pos')->default(false);

            $table->boolean('exibe_f3')->default(false);
            $table->boolean('exibe_f4')->default(false);
            $table->boolean('exibe_f5')->default(false);
            $table->boolean('exibe_f6')->default(false);
            $table->boolean('pesquisa_rapida')->default(false);
            $table->boolean('ler_peso')->default(false);
            $table->boolean('busca_balanca_barras')->default(true);
            $table->string('mensagem_pdv')->nullable();
            $table->boolean('mostrar_mensagem_pdv')->default(false);
            $table->boolean('mostrar_tela_caixa_livre')->default(false);
            $table->unsignedInteger('time_tela_caixa_livre')->nullable();

            $table->boolean('imprime')->default(true);
            $table->boolean('usa_gaveta')->default(false);
            $table->string('fab_impressora', 40)->nullable();
            $table->string('modelo', 60)->nullable();
            $table->string('porta', 120)->nullable();
            $table->unsignedInteger('velocidade')->nullable();
            $table->unsignedSmallInteger('nvias')->default(1);
            $table->string('serie', 20)->nullable();
            $table->unsignedInteger('numeracao_inicial')->nullable();
            $table->boolean('usar_numero_inicial')->default(false);
            $table->string('tipo_impressora', 10)->default('1');
            $table->string('tipo_fechamento', 10)->nullable();
            $table->boolean('meia_folha')->default(false);
            $table->string('impressora_nome')->nullable();
            $table->string('pagina_codigo', 40)->nullable();
            $table->decimal('margem_superior', 8, 2)->nullable();
            $table->decimal('margem_inferior', 8, 2)->nullable();
            $table->decimal('margem_esquerda', 8, 2)->nullable();
            $table->decimal('margem_direita', 8, 2)->nullable();
            $table->unsignedInteger('largura_bobina')->nullable();
            $table->unsignedInteger('tamanho_fonte')->nullable();

            $table->string('balanca_porta', 40)->nullable();
            $table->string('balanca_velocidade', 20)->nullable();
            $table->string('balanca_marca', 40)->nullable();
            $table->string('balanca_paridade', 20)->nullable();
            $table->string('balanca_databits', 10)->nullable();
            $table->string('balanca_stopbits', 10)->nullable();
            $table->string('balanca_handshaking', 20)->nullable();
            $table->unsignedTinyInteger('qtd_tentativa_conect_bal')->nullable();

            $table->string('caminho_sat_dll')->nullable();
            $table->string('modelo_sat_dll', 40)->nullable();
            $table->string('tipo_sat_dll', 40)->nullable();

            $table->unsignedSmallInteger('modelo_tef')->nullable();
            $table->unsignedSmallInteger('tef_gerenciador')->nullable();
            $table->string('ip_servidor_tef', 120)->nullable();
            $table->unsignedInteger('porta_pin_pad')->nullable();
            $table->string('mensagem_pin_pad')->nullable();
            $table->unsignedInteger('tef_max_cartoes')->nullable();
            $table->decimal('tef_troco_maximo', 12, 2)->nullable();
            $table->boolean('tef_via_reduzida')->default(false);
            $table->boolean('tef_multiplos_cartoes')->default(false);

            $table->string('caminho_cozinha')->nullable();
            $table->string('caminho_bar')->nullable();

            $table->json('impressora_extra')->nullable();
            $table->json('tef_extra')->nullable();

            $table->timestamps();

            $table->unique(['empresa_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminais');
    }
};
