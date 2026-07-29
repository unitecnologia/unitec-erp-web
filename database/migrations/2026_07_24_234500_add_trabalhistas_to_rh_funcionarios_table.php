<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_funcionarios', function (Blueprint $table): void {
            $table->string('pis_pasep', 20)->nullable()->after('rg');
            $table->string('ctps', 30)->nullable()->after('pis_pasep');
            $table->string('inss', 30)->nullable()->after('ctps');
            $table->string('tipo_salario', 40)->nullable()->after('departamento_id');

            $table->string('cep', 10)->nullable()->after('email');
            $table->string('logradouro', 40)->nullable()->after('cep');
            $table->string('endereco', 120)->nullable()->after('logradouro');
            $table->string('numero', 20)->nullable()->after('endereco');
            $table->string('bairro', 80)->nullable()->after('numero');
            $table->string('complemento', 80)->nullable()->after('bairro');
            $table->string('cidade_codigo', 20)->nullable()->after('complemento');
            $table->string('cidade_nome', 80)->nullable()->after('cidade_codigo');
            $table->string('uf', 2)->nullable()->after('cidade_nome');
        });
    }

    public function down(): void
    {
        Schema::table('rh_funcionarios', function (Blueprint $table): void {
            $table->dropColumn([
                'pis_pasep',
                'ctps',
                'inss',
                'tipo_salario',
                'cep',
                'logradouro',
                'endereco',
                'numero',
                'bairro',
                'complemento',
                'cidade_codigo',
                'cidade_nome',
                'uf',
            ]);
        });
    }
};
