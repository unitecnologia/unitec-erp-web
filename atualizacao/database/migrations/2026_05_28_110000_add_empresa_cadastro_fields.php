<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('pessoa_tipo', 20)->default('juridica')->after('razao_social');
            $table->string('im', 30)->nullable()->after('ie');
            $table->string('cnae', 20)->nullable()->after('im');
            $table->string('regime_tributario', 30)->default('normal')->after('cnae');
            $table->string('cep', 10)->nullable()->after('regime_tributario');
            $table->string('endereco')->nullable()->after('cep');
            $table->string('numero', 20)->nullable()->after('endereco');
            $table->string('complemento')->nullable()->after('numero');
            $table->string('bairro')->nullable()->after('complemento');
            $table->string('cidade_codigo', 20)->nullable()->after('bairro');
            $table->string('uf', 2)->default('SC')->after('cidade');
            $table->string('pais_codigo', 10)->default('1058')->after('uf');
            $table->string('pais', 60)->default('BRASIL')->after('pais_codigo');
            $table->string('email')->nullable()->after('pais');
            $table->string('site')->nullable()->after('email');
            $table->string('telefone', 20)->nullable()->after('site');
            $table->string('responsavel')->nullable()->after('telefone');
            $table->string('cnpj_representante', 14)->nullable()->after('responsavel');
            $table->string('tipo_atividade', 40)->default('informatica')->after('cnpj_representante');
            $table->text('obs_fisco')->nullable()->after('tipo_atividade');
            $table->text('obs_carne')->nullable()->after('obs_fisco');
            $table->text('obs_nfce')->nullable()->after('obs_carne');
            $table->text('msg_cobranca_whatsapp')->nullable()->after('obs_nfce');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'pessoa_tipo',
                'im',
                'cnae',
                'regime_tributario',
                'cep',
                'endereco',
                'numero',
                'complemento',
                'bairro',
                'cidade_codigo',
                'uf',
                'pais_codigo',
                'pais',
                'email',
                'site',
                'telefone',
                'responsavel',
                'cnpj_representante',
                'tipo_atividade',
                'obs_fisco',
                'obs_carne',
                'obs_nfce',
                'msg_cobranca_whatsapp',
            ]);
        });
    }
};
