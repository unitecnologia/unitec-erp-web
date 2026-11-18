<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_holerites')) {
            Schema::create('rh_holerites', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 20);
                $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->restrictOnDelete();
                $table->unsignedSmallInteger('competencia_ano');
                $table->unsignedTinyInteger('competencia_mes');
                $table->date('pago_em')->nullable();
                $table->string('observacao', 500)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('codigo');
                $table->unique(['funcionario_id', 'competencia_ano', 'competencia_mes'], 'rh_hol_func_comp_uq');
                $table->index(['competencia_ano', 'competencia_mes', 'ativo'], 'rh_hol_comp_ativo_idx');
                $table->index(['funcionario_id', 'ativo'], 'rh_hol_func_ativo_idx');
            });
        }

        if (! Schema::hasTable('rh_holerite_logs')) {
            Schema::create('rh_holerite_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('holerite_id')->constrained('rh_holerites')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('acao', 40);
                $table->json('antes')->nullable();
                $table->json('depois')->nullable();
                $table->string('motivo', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['holerite_id', 'created_at'], 'rh_hol_log_idx');
            });
        }

        if (! Schema::hasTable('rh_beneficios')) {
            Schema::create('rh_beneficios', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 20);
                $table->string('descricao', 160);
                $table->string('tipo', 40);
                $table->decimal('valor_padrao', 12, 2)->nullable();
                $table->string('periodicidade', 40)->default('mensal');
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('codigo');
                $table->index(['tipo', 'ativo'], 'rh_ben_tipo_ativo_idx');
                $table->index('ativo', 'rh_ben_ativo_idx');
            });
        }

        if (! Schema::hasTable('rh_beneficio_vinculos')) {
            Schema::create('rh_beneficio_vinculos', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 20);
                $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->restrictOnDelete();
                $table->foreignId('beneficio_id')->constrained('rh_beneficios')->restrictOnDelete();
                $table->date('inicio_em');
                $table->date('termino_em')->nullable();
                $table->decimal('valor', 12, 2);
                $table->decimal('participacao_funcionario', 12, 2)->nullable();
                $table->string('situacao', 20)->default('ativo');
                $table->string('observacao', 500)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('codigo');
                $table->index(['funcionario_id', 'situacao'], 'rh_ben_vin_func_sit_idx');
                $table->index(['beneficio_id', 'situacao'], 'rh_ben_vin_ben_sit_idx');
                $table->index(['inicio_em', 'termino_em'], 'rh_ben_vin_periodo_idx');
            });
        }

        if (! Schema::hasTable('rh_beneficio_vinculo_logs')) {
            Schema::create('rh_beneficio_vinculo_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('vinculo_id')->constrained('rh_beneficio_vinculos')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('acao', 40);
                $table->json('antes')->nullable();
                $table->json('depois')->nullable();
                $table->string('motivo', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['vinculo_id', 'created_at'], 'rh_ben_vin_log_idx');
            });
        }

        if (! Schema::hasTable('rh_ocorrencias')) {
            Schema::create('rh_ocorrencias', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 20);
                $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->restrictOnDelete();
                $table->string('tipo', 40);
                $table->date('ocorrido_em');
                $table->string('descricao', 1000);
                $table->string('responsavel', 120)->nullable();
                $table->string('situacao', 20)->default('registrada');
                $table->boolean('acesso_restrito')->default(false);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('codigo');
                $table->index(['funcionario_id', 'ocorrido_em'], 'rh_ocor_func_data_idx');
                $table->index(['tipo', 'ocorrido_em'], 'rh_ocor_tipo_data_idx');
                $table->index(['situacao', 'ativo'], 'rh_ocor_sit_ativo_idx');
            });
        }

        if (! Schema::hasTable('rh_ocorrencia_logs')) {
            Schema::create('rh_ocorrencia_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ocorrencia_id')->constrained('rh_ocorrencias')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('acao', 40);
                $table->json('antes')->nullable();
                $table->json('depois')->nullable();
                $table->string('motivo', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['ocorrencia_id', 'created_at'], 'rh_ocor_log_idx');
            });
        }

        if (! Schema::hasTable('rh_ferias_periodos')) {
            Schema::create('rh_ferias_periodos', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 20);
                $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->restrictOnDelete();
                $table->date('aquisitivo_inicio');
                $table->date('aquisitivo_fim');
                $table->date('concessivo_inicio');
                $table->date('concessivo_fim');
                $table->unsignedSmallInteger('dias_direito')->default(30);
                $table->unsignedSmallInteger('dias_saldo')->default(30);
                $table->string('situacao', 20)->default('aberto');
                $table->string('observacao', 500)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('codigo');
                $table->unique(['funcionario_id', 'aquisitivo_inicio'], 'rh_fer_per_func_aq_uq');
                $table->index(['funcionario_id', 'situacao'], 'rh_fer_per_func_sit_idx');
                $table->index(['concessivo_fim', 'situacao'], 'rh_fer_per_conc_sit_idx');
            });
        }

        if (! Schema::hasTable('rh_ferias_gozos')) {
            Schema::create('rh_ferias_gozos', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 20);
                $table->foreignId('periodo_id')->constrained('rh_ferias_periodos')->restrictOnDelete();
                $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->restrictOnDelete();
                $table->date('gozo_inicio');
                $table->date('gozo_fim');
                $table->unsignedSmallInteger('dias');
                $table->unsignedTinyInteger('fracao')->default(1);
                $table->date('aviso_em')->nullable();
                $table->string('situacao', 20)->default('programada');
                $table->boolean('ajuste_admin')->default(false);
                $table->string('observacao', 500)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('codigo');
                $table->index(['funcionario_id', 'gozo_inicio', 'gozo_fim'], 'rh_fer_goz_func_per_idx');
                $table->index(['periodo_id', 'situacao'], 'rh_fer_goz_per_sit_idx');
                $table->index(['situacao', 'ativo'], 'rh_fer_goz_sit_ativo_idx');
            });
        }

        if (! Schema::hasTable('rh_ferias_gozo_logs')) {
            Schema::create('rh_ferias_gozo_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('gozo_id')->constrained('rh_ferias_gozos')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('acao', 40);
                $table->json('antes')->nullable();
                $table->json('depois')->nullable();
                $table->string('motivo', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['gozo_id', 'created_at'], 'rh_fer_goz_log_idx');
            });
        }

        if (Schema::hasTable('rh_escalas') && ! Schema::hasColumn('rh_escalas', 'tipo')) {
            Schema::table('rh_escalas', function (Blueprint $table): void {
                $table->string('tipo', 40)->default('personalizada')->after('nome');
                $table->time('hora_entrada')->nullable()->after('tipo');
                $table->time('hora_saida')->nullable()->after('hora_entrada');
                $table->unsignedSmallInteger('intervalo_minutos')->nullable()->after('hora_saida');
                $table->json('dias_trabalho')->nullable()->after('intervalo_minutos');
                $table->json('dias_folga')->nullable()->after('dias_trabalho');
            });
        }

        if (! Schema::hasTable('rh_escala_vinculos')) {
            Schema::create('rh_escala_vinculos', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 20);
                $table->foreignId('escala_id')->constrained('rh_escalas')->restrictOnDelete();
                $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->restrictOnDelete();
                $table->date('inicio_em');
                $table->date('termino_em')->nullable();
                $table->string('observacao', 500)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('codigo');
                $table->index(['funcionario_id', 'inicio_em', 'termino_em'], 'rh_esc_vin_func_per_idx');
                $table->index(['escala_id', 'ativo'], 'rh_esc_vin_esc_ativo_idx');
            });
        }

        if (! Schema::hasTable('rh_escala_ajustes')) {
            Schema::create('rh_escala_ajustes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('vinculo_id')->nullable()->constrained('rh_escala_vinculos')->nullOnDelete();
                $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->restrictOnDelete();
                $table->date('data_ref');
                $table->string('tipo', 20);
                $table->time('hora_inicio')->nullable();
                $table->time('hora_fim')->nullable();
                $table->string('motivo', 255)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['funcionario_id', 'data_ref'], 'rh_esc_aju_func_data_uq');
                $table->index(['data_ref', 'tipo'], 'rh_esc_aju_data_tipo_idx');
            });
        }

        if (! Schema::hasTable('rh_escala_logs')) {
            Schema::create('rh_escala_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('escala_id')->nullable()->constrained('rh_escalas')->nullOnDelete();
                $table->foreignId('vinculo_id')->nullable()->constrained('rh_escala_vinculos')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('acao', 40);
                $table->json('antes')->nullable();
                $table->json('depois')->nullable();
                $table->string('motivo', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['escala_id', 'created_at'], 'rh_esc_log_esc_idx');
                $table->index(['vinculo_id', 'created_at'], 'rh_esc_log_vin_idx');
            });
        }

        if (! Schema::hasColumn('rh_funcionarios', 'tipo_vinculo')) {
            Schema::table('rh_funcionarios', function (Blueprint $table): void {
                $table->string('tipo_vinculo', 40)->nullable()->after('tipo_salario');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_escala_logs');
        Schema::dropIfExists('rh_escala_ajustes');
        Schema::dropIfExists('rh_escala_vinculos');

        if (Schema::hasColumn('rh_escalas', 'tipo')) {
            Schema::table('rh_escalas', function (Blueprint $table): void {
                $table->dropColumn([
                    'tipo',
                    'hora_entrada',
                    'hora_saida',
                    'intervalo_minutos',
                    'dias_trabalho',
                    'dias_folga',
                ]);
            });
        }

        Schema::dropIfExists('rh_ferias_gozo_logs');
        Schema::dropIfExists('rh_ferias_gozos');
        Schema::dropIfExists('rh_ferias_periodos');
        Schema::dropIfExists('rh_ocorrencia_logs');
        Schema::dropIfExists('rh_ocorrencias');
        Schema::dropIfExists('rh_beneficio_vinculo_logs');
        Schema::dropIfExists('rh_beneficio_vinculos');
        Schema::dropIfExists('rh_beneficios');
        Schema::dropIfExists('rh_holerite_logs');
        Schema::dropIfExists('rh_holerites');

        if (Schema::hasColumn('rh_funcionarios', 'tipo_vinculo')) {
            Schema::table('rh_funcionarios', function (Blueprint $table): void {
                $table->dropColumn('tipo_vinculo');
            });
        }
    }
};
