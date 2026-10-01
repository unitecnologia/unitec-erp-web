<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conta_receber_pagamentos')) {
            Schema::create('conta_receber_pagamentos', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('codigo_legado')->unique();
                $table->foreignId('conta_receber_id')->constrained('contas_receber')->cascadeOnDelete();
                $table->date('data');
                $table->decimal('valor_parcela', 15, 2)->default(0);
                $table->decimal('perc_juros', 8, 4)->default(0);
                $table->decimal('juros', 15, 2)->default(0);
                $table->decimal('perc_desconto', 8, 4)->default(0);
                $table->decimal('desconto', 15, 2)->default(0);
                $table->decimal('valor_recebido', 15, 2)->default(0);
                $table->foreignId('plano_conta_id')->nullable()->constrained('planos_contas')->nullOnDelete();
                $table->foreignId('caixa_conta_id')->nullable()->constrained('caixa_contas')->nullOnDelete();
                $table->foreignId('forma_pagamento_id')->nullable()->constrained('formas_pagamento')->nullOnDelete();
                $table->string('numero_cheque', 40)->nullable();
                $table->foreignId('cliente_id')->nullable()->constrained('people')->nullOnDelete();
                $table->timestamps();

                $table->index(['conta_receber_id', 'data']);
            });
        }

        if (DB::table('conta_receber_pagamentos')->exists()) {
            return;
        }

        $codigo = 1;
        $agora = now();

        DB::table('contas_receber')
            ->where('valor_recebido', '>', 0)
            ->orderBy('id')
            ->get([
                'id', 'cliente_id', 'emissao', 'recebido_em', 'valor', 'juros', 'desconto',
                'valor_recebido', 'numero_cheque',
            ])
            ->each(function (object $conta) use (&$codigo, $agora): void {
                DB::table('conta_receber_pagamentos')->insert([
                    'codigo_legado' => $codigo,
                    'conta_receber_id' => $conta->id,
                    'data' => $conta->recebido_em ?: $conta->emissao ?: $agora->toDateString(),
                    'valor_parcela' => $conta->valor,
                    'perc_juros' => 0,
                    'juros' => $conta->juros,
                    'perc_desconto' => 0,
                    'desconto' => $conta->desconto,
                    'valor_recebido' => $conta->valor_recebido,
                    'plano_conta_id' => null,
                    'caixa_conta_id' => null,
                    'forma_pagamento_id' => null,
                    'numero_cheque' => $conta->numero_cheque,
                    'cliente_id' => $conta->cliente_id,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
                $codigo++;
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('conta_receber_pagamentos');
    }
};
