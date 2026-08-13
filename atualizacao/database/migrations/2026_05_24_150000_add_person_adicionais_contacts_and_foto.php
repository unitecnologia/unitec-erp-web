<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('nome_mae')->nullable()->after('tipo_contribuinte');
            $table->string('nome_pai')->nullable()->after('nome_mae');
            $table->date('data_nascimento')->nullable()->after('nome_pai');
            $table->decimal('limite_credito', 15, 2)->default(0)->after('data_nascimento');
            $table->string('estado_civil')->nullable()->after('limite_credito');
            $table->string('sexo')->nullable()->after('estado_civil');
            $table->decimal('salario', 15, 2)->default(0)->after('sexo');
            $table->date('data_admissao')->nullable()->after('salario');
            $table->date('data_demissao')->nullable()->after('data_admissao');
            $table->text('observacoes')->nullable()->after('data_demissao');
            $table->boolean('is_atendente')->default(false)->after('observacoes');
            $table->boolean('is_tecnico')->default(false)->after('is_atendente');
            $table->string('foto_path')->nullable()->after('is_tecnico');
        });

        Schema::create('person_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->dateTime('contato_em');
            $table->date('data_retorno')->nullable();
            $table->string('pessoa')->nullable();
            $table->string('motivo')->nullable();
            $table->text('descricao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_contacts');

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn([
                'nome_mae',
                'nome_pai',
                'data_nascimento',
                'limite_credito',
                'estado_civil',
                'sexo',
                'salario',
                'data_admissao',
                'data_demissao',
                'observacoes',
                'is_atendente',
                'is_tecnico',
                'foto_path',
            ]);
        });
    }
};
