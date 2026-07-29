<?php

use App\Support\Erp\EmpresaParametros;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            foreach (EmpresaParametros::impostoTextFields() as $field => $meta) {
                if ($field === 'param_imp_observacao' || Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                // TEXT: unitec_empresas já no limite de row size InnoDB (VARCHAR falha com 1118).
                $table->text($field)->nullable();
            }
        });

        if (! Schema::hasTable('fiscal_classificacoes_tributarias')) {
            Schema::create('fiscal_classificacoes_tributarias', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 10)->unique();
                $table->string('cst_ibs_cbs', 3)->nullable()->index();
                $table->string('descricao', 500)->nullable();
                $table->string('nome_reduzido', 255)->nullable();
                $table->date('vigencia_inicio')->nullable();
                $table->date('vigencia_fim')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fiscal_ibpt_itens')) {
            Schema::create('fiscal_ibpt_itens', function (Blueprint $table) {
                $table->id();
                $table->string('ncm', 10)->index();
                $table->string('ex_tipi', 4)->nullable();
                $table->string('tipo', 1)->nullable();
                $table->string('descricao', 500)->nullable();
                $table->decimal('aliq_nacional', 8, 2)->default(0);
                $table->decimal('aliq_importado', 8, 2)->default(0);
                $table->decimal('aliq_estadual', 8, 2)->default(0);
                $table->decimal('aliq_municipal', 8, 2)->default(0);
                $table->date('vigencia_inicio')->nullable();
                $table->date('vigencia_fim')->nullable();
                $table->string('chave', 80)->nullable();
                $table->string('versao', 40)->nullable();
                $table->string('fonte', 80)->nullable();
                $table->timestamps();

                $table->unique(['ncm', 'ex_tipi', 'tipo', 'versao'], 'fiscal_ibpt_itens_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_ibpt_itens');
        Schema::dropIfExists('fiscal_classificacoes_tributarias');

        Schema::table('empresas', function (Blueprint $table) {
            $drop = [];

            foreach (array_keys(EmpresaParametros::impostoTextFields()) as $field) {
                if ($field === 'param_imp_observacao') {
                    continue;
                }

                if (Schema::hasColumn('empresas', $field)) {
                    $drop[] = $field;
                }
            }

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
