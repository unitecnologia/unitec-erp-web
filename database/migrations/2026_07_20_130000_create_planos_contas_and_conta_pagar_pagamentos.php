<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos_contas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('codigo')->unique();
            $table->string('descricao', 120);
            $table->string('dc', 1)->nullable()->comment('D=débito C=crédito');
            $table->unsignedTinyInteger('nivel')->nullable();
            $table->string('codigo_plano', 40)->nullable();
            $table->unsignedInteger('pai_codigo')->nullable();
            $table->string('conta_completa', 80)->nullable();
            $table->string('flag', 10)->nullable();
            $table->boolean('despesas')->default(false);
            $table->boolean('compras')->default(false);
            $table->boolean('entradas')->default(false);
            $table->decimal('taxa_juros', 8, 4)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('descricao');
            $table->index('pai_codigo');
        });

        Schema::create('conta_pagar_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('codigo_legado')->unique();
            $table->foreignId('conta_pagar_id')->constrained('contas_pagar')->cascadeOnDelete();
            $table->date('data');
            $table->decimal('valor_parcela', 15, 2)->default(0);
            $table->decimal('perc_juros', 8, 4)->default(0);
            $table->decimal('juros', 15, 2)->default(0);
            $table->decimal('perc_desconto', 8, 4)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->foreignId('plano_conta_id')->nullable()->constrained('planos_contas')->nullOnDelete();
            $table->foreignId('caixa_conta_id')->nullable()->constrained('caixa_contas')->nullOnDelete();
            $table->foreignId('forma_pagamento_id')->nullable()->constrained('formas_pagamento')->nullOnDelete();
            $table->string('numero_cheque', 40)->nullable();
            $table->foreignId('fornecedor_id')->nullable()->constrained('people')->nullOnDelete();
            $table->unsignedInteger('lote_legado')->nullable();
            $table->timestamps();

            $table->index(['conta_pagar_id', 'data']);
        });

        if (Schema::hasTable('caixa_lancamentos') && ! Schema::hasColumn('caixa_lancamentos', 'plano_conta_id')) {
            Schema::table('caixa_lancamentos', function (Blueprint $table): void {
                $table->foreignId('plano_conta_id')
                    ->nullable()
                    ->after('plano_contas')
                    ->constrained('planos_contas')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('caixa_lancamentos') && Schema::hasColumn('caixa_lancamentos', 'plano_conta_id')) {
            Schema::table('caixa_lancamentos', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('plano_conta_id');
            });
        }

        Schema::dropIfExists('conta_pagar_pagamentos');
        Schema::dropIfExists('planos_contas');
    }
};
