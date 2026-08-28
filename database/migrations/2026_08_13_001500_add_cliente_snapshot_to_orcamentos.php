<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orcamentos', function (Blueprint $table): void {
            $table->string('cliente_nome')->nullable()->after('cliente_id');
            $table->string('cliente_cpf_cnpj', 20)->nullable()->after('cliente_nome');
            $table->string('cliente_endereco')->nullable()->after('cliente_cpf_cnpj');
            $table->string('cliente_numero', 30)->nullable()->after('cliente_endereco');
            $table->string('cliente_bairro', 120)->nullable()->after('cliente_numero');
            $table->string('cliente_cep', 12)->nullable()->after('cliente_bairro');
            $table->string('cliente_cidade', 120)->nullable()->after('cliente_cep');
            $table->string('cliente_uf', 2)->nullable()->after('cliente_cidade');
            $table->string('cliente_fone', 30)->nullable()->after('cliente_uf');
            $table->string('cliente_whatsapp', 30)->nullable()->after('cliente_fone');
        });
    }

    public function down(): void
    {
        Schema::table('orcamentos', function (Blueprint $table): void {
            $table->dropColumn([
                'cliente_nome',
                'cliente_cpf_cnpj',
                'cliente_endereco',
                'cliente_numero',
                'cliente_bairro',
                'cliente_cep',
                'cliente_cidade',
                'cliente_uf',
                'cliente_fone',
                'cliente_whatsapp',
            ]);
        });
    }
};
