<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boleto_parametros', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();
            $table->string('banco', 20)->nullable();
            $table->string('layout', 35)->nullable();
            $table->string('carteira', 10)->nullable();
            $table->string('tipo_carteira', 20)->nullable();
            $table->string('especie_docto', 10)->nullable();
            $table->string('especie_moeda', 5)->nullable();
            $table->string('aceite', 3)->nullable();
            $table->string('tipo_documento', 15)->nullable();
            $table->string('tipo_distribuicao', 30)->nullable();
            $table->string('carac_titulo', 25)->nullable();
            $table->string('responsavel_emissao', 20)->nullable();
            $table->string('cnab_versao', 10)->nullable();
            $table->unsignedInteger('cnab_lv_lote')->nullable();
            $table->unsignedInteger('cnab_lv_arquivo')->nullable();
            $table->unsignedInteger('cnab_codigo_transmissao')->nullable();
            $table->unsignedInteger('cnab_densidade_gravacao')->nullable();
            $table->unsignedInteger('cnab_prefixo_remessa')->nullable();
            $table->string('cip', 20)->nullable();
            $table->boolean('homologacao')->default(true);
            $table->boolean('imp_msg_padrao_comp_banco')->default(false);
            $table->boolean('ler_cedente_arq_retorno')->default(false);
            $table->boolean('ler_nosso_num_completo')->default(false);
            $table->boolean('remover_acentuacao_remessa')->default(true);
            $table->boolean('imp_verso_fatura')->default(false);
            $table->string('local_pagamento', 500)->nullable();
            $table->unsignedInteger('ben_agencia')->nullable();
            $table->unsignedTinyInteger('ben_agencia_dv')->nullable();
            $table->unsignedBigInteger('ben_conta')->nullable();
            $table->unsignedTinyInteger('ben_conta_dv')->nullable();
            $table->unsignedTinyInteger('ben_agencia_conta_dv')->nullable();
            $table->string('ben_convenio', 20)->nullable();
            $table->string('ben_modalidade', 10)->nullable();
            $table->string('ben_operacao', 10)->nullable();
            $table->unsignedInteger('ben_cod_cedente')->nullable();
            $table->string('nosso_numero', 50)->nullable();
            $table->string('webservice_client_id', 500)->nullable();
            $table->string('webservice_client_secret', 500)->nullable();
            $table->string('webservice_key_user', 500)->nullable();
            $table->boolean('webservice_indicador_pix')->default(false);
            $table->string('webservice_ssl_lib', 20)->nullable();
            $table->string('path_remessa', 250)->nullable();
            $table->string('path_retorno', 250)->nullable();
            $table->unsignedInteger('codigo_legado')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('boletos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('conta_receber_id')->nullable()->constrained('contas_receber')->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('nosso_numero', 100)->nullable()->index();
            $table->string('numero_documento', 50)->nullable();
            $table->string('linha_digitavel', 250)->nullable();
            $table->date('emissao')->nullable();
            $table->date('vencimento')->nullable();
            $table->date('processamento')->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->decimal('valor_juros', 15, 2)->default(0);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->decimal('valor_abatimento', 15, 2)->default(0);
            $table->decimal('percentual_multa', 8, 4)->default(0);
            $table->date('data_juros')->nullable();
            $table->date('data_desconto')->nullable();
            $table->date('data_abatimento')->nullable();
            $table->date('data_protesto')->nullable();
            $table->string('sacado_nome', 150)->nullable();
            $table->string('sacado_documento', 20)->nullable();
            $table->string('sacado_logradouro', 250)->nullable();
            $table->string('sacado_numero', 20)->nullable();
            $table->string('sacado_bairro', 50)->nullable();
            $table->string('sacado_cidade', 100)->nullable();
            $table->string('sacado_uf', 2)->nullable();
            $table->string('sacado_cep', 20)->nullable();
            $table->string('instrucao1', 250)->nullable();
            $table->string('instrucao2', 250)->nullable();
            $table->string('path_pdf', 500)->nullable();
            $table->string('status', 1)->default('A')->index();
            $table->unsignedInteger('codigo_legado')->nullable()->unique();
            $table->timestamps();

            $table->index(['empresa_id', 'vencimento']);
        });

        Schema::create('boleto_remessas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->unsignedBigInteger('id_legado')->nullable()->unique();
            $table->string('uuid_legado', 44)->nullable();
            $table->timestamp('data')->nullable();
            $table->unsignedSmallInteger('banco_id')->nullable();
            $table->string('agencia', 15)->nullable();
            $table->string('agencia_digito', 3)->nullable();
            $table->string('conta', 20)->nullable();
            $table->string('conta_digito', 3)->nullable();
            $table->string('codigo_cedente', 10)->nullable();
            $table->string('convenio', 10)->nullable();
            $table->string('modalidade', 2)->nullable();
            $table->string('carteira', 10)->nullable();
            $table->string('local_pagamento', 200)->nullable();
            $table->string('mensagem', 200)->nullable();
            $table->string('instrucao1', 200)->nullable();
            $table->string('instrucao2', 200)->nullable();
            $table->decimal('percentual_juros', 8, 4)->default(0);
            $table->decimal('percentual_multa', 8, 4)->default(0);
            $table->timestamp('data_geracao')->nullable();
            $table->string('local_arquivo', 200)->nullable();
            $table->timestamp('data_proc_banco')->nullable();
            $table->boolean('cancelada')->default(false);
            $table->unsignedInteger('qtd_titulos')->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['empresa_id', 'data']);
        });

        Schema::create('boleto_remessa_titulos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('boleto_remessa_id')->constrained('boleto_remessas')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->unsignedBigInteger('id_legado')->nullable()->index();
            $table->timestamp('emissao')->nullable();
            $table->timestamp('vencimento')->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->string('cliente_razao', 100)->nullable();
            $table->string('cliente_documento', 15)->nullable();
            $table->string('cliente_endereco', 100)->nullable();
            $table->string('cliente_numero', 20)->nullable();
            $table->string('cliente_bairro', 100)->nullable();
            $table->string('cliente_cidade', 100)->nullable();
            $table->string('cliente_uf', 2)->nullable();
            $table->string('cliente_cep', 8)->nullable();
            $table->date('data_pagamento')->nullable();
            $table->boolean('cancelamento_loja')->default(false);
            $table->boolean('pagamento_loja')->default(false);
            $table->boolean('alteracao_loja')->default(false);
            $table->unsignedInteger('numero_boleto')->nullable();
            $table->timestamps();
        });

        Schema::create('boleto_retornos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->unsignedBigInteger('id_legado')->nullable()->unique();
            $table->timestamp('cadastrado_em')->nullable();
            $table->timestamp('processado_em')->nullable();
            $table->timestamp('arquivado_em')->nullable();
            $table->string('arquivo_nome', 250)->nullable();
            $table->timestamp('arquivo_data')->nullable();
            $table->unsignedInteger('arquivo_numero')->nullable();
            $table->string('arquivo_local', 300)->nullable();
            $table->string('arquivo_md5', 300)->nullable();
            $table->unsignedInteger('arquivo_qtd_titulos')->default(0);
            $table->unsignedSmallInteger('situacao')->default(0)->index();
            $table->timestamps();

            $table->index(['empresa_id', 'cadastrado_em']);
        });

        Schema::create('boleto_retorno_titulos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('boleto_retorno_id')->constrained('boleto_retornos')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->unsignedBigInteger('id_legado')->nullable()->index();
            $table->unsignedBigInteger('titulo_legado')->nullable();
            $table->boolean('titulo_localizado')->default(false);
            $table->boolean('titulo_ja_liquidado')->default(false);
            $table->boolean('titulo_sem_registro')->default(false);
            $table->boolean('titulo_liquidado_limite')->default(false);
            $table->boolean('titulo_recusado')->default(false);
            $table->string('seu_numero', 50)->nullable();
            $table->string('nosso_numero', 50)->nullable()->index();
            $table->decimal('valor_documento', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->decimal('valor_recebido', 15, 2)->default(0);
            $table->decimal('valor_juros', 15, 2)->default(0);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->decimal('valor_despesa', 15, 2)->default(0);
            $table->date('data_ocorrencia')->nullable();
            $table->string('banco_id', 3)->nullable();
            $table->string('agencia_id', 11)->nullable();
            $table->string('origem', 300)->nullable();
            $table->string('forma_pagamento', 300)->nullable();
            $table->unsignedInteger('tipo_ocorrencia')->nullable();
            $table->string('tipo_ocorrencia_desc', 300)->nullable();
            $table->string('mot_rej_comando', 300)->nullable();
            $table->string('mot_rej_comando_desc', 300)->nullable();
            $table->string('historico', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boleto_retorno_titulos');
        Schema::dropIfExists('boleto_retornos');
        Schema::dropIfExists('boleto_remessa_titulos');
        Schema::dropIfExists('boleto_remessas');
        Schema::dropIfExists('boletos');
        Schema::dropIfExists('boleto_parametros');
    }
};
