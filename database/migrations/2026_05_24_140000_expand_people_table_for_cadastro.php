<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('pessoa_tipo')->default('fisica')->after('codigo');
            $table->string('cep')->nullable()->after('apelido_fantasia');
            $table->string('numero')->nullable()->after('endereco');
            $table->string('complemento')->nullable()->after('numero');
            $table->string('bairro')->nullable()->after('complemento');
            $table->string('cidade_codigo')->nullable()->after('bairro');
            $table->string('cidade_nome')->nullable()->after('cidade_codigo');
            $table->string('uf', 2)->nullable()->after('cidade_nome');
            $table->string('email')->nullable()->after('uf');
            $table->string('fone1')->nullable()->after('email');
            $table->string('fone2')->nullable()->after('fone1');
            $table->string('celular1')->nullable()->after('fone2');
            $table->string('celular2')->nullable()->after('celular1');
            $table->string('whatsapp')->nullable()->after('celular2');
            $table->string('regime_tributario')->nullable()->after('whatsapp');
            $table->string('tipo_recebimento')->nullable()->after('regime_tributario');
            $table->string('tipo_contribuinte')->nullable()->after('tipo_recebimento');
            $table->boolean('is_cliente')->default(false)->after('tipo_contribuinte');
            $table->boolean('is_fornecedor')->default(false)->after('is_cliente');
            $table->boolean('is_funcionario')->default(false)->after('is_fornecedor');
            $table->boolean('is_administradora')->default(false)->after('is_funcionario');
            $table->boolean('is_parceiro')->default(false)->after('is_administradora');
            $table->boolean('is_ccf_spc')->default(false)->after('is_parceiro');
        });

        if (Schema::hasColumn('people', 'tipo')) {
            foreach (DB::table('people')->orderBy('id')->get() as $person) {
                DB::table('people')->where('id', $person->id)->update([
                    'is_cliente' => $person->tipo === 'cliente',
                    'is_fornecedor' => $person->tipo === 'fornecedor',
                    'is_funcionario' => $person->tipo === 'funcionario',
                    'is_administradora' => $person->tipo === 'administradora',
                    'is_parceiro' => $person->tipo === 'parceiro',
                    'pessoa_tipo' => str($person->cpf_cnpj ?? '')->contains('/') ? 'juridica' : 'fisica',
                ]);
            }

            Schema::table('people', function (Blueprint $table) {
                $table->dropColumn('tipo');
            });
        }
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('tipo')->default('cliente')->after('endereco');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn([
                'pessoa_tipo',
                'cep',
                'numero',
                'complemento',
                'bairro',
                'cidade_codigo',
                'cidade_nome',
                'uf',
                'email',
                'fone1',
                'fone2',
                'celular1',
                'celular2',
                'whatsapp',
                'regime_tributario',
                'tipo_recebimento',
                'tipo_contribuinte',
                'is_cliente',
                'is_fornecedor',
                'is_funcionario',
                'is_administradora',
                'is_parceiro',
                'is_ccf_spc',
            ]);
        });
    }
};
