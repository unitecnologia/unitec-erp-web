<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_vendas', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('user_id')->constrained('people')->nullOnDelete();
            $table->string('cpf_nota', 20)->nullable()->after('person_id');
            $table->foreignId('vendedor_id')->nullable()->after('cpf_nota')->constrained('vendedores')->nullOnDelete();
            $table->string('vendedor_nome')->nullable()->after('vendedor_id');
            $table->decimal('subtotal', 12, 2)->default(0)->after('numero');
            $table->decimal('desconto', 12, 2)->default(0)->after('subtotal');
            $table->decimal('acrescimo', 12, 2)->default(0)->after('desconto');
            $table->decimal('troco', 12, 2)->default(0)->after('total');
            $table->decimal('dinheiro', 12, 2)->default(0)->after('troco');
            $table->string('situacao', 1)->default('F')->after('fiscal');
        });

        Schema::table('pdv_venda_itens', function (Blueprint $table) {
            $table->text('observacao')->nullable()->after('unidade');
            $table->decimal('desconto', 12, 2)->default(0)->after('preco_unitario');
            $table->foreignId('product_grade_id')->nullable()->after('product_id')->constrained('product_grades')->nullOnDelete();
        });

        Schema::table('pdv_caixa_movimentos', function (Blueprint $table) {
            $table->string('sangria_destino')->nullable()->after('forma_pagamento');
        });

        Schema::create('pdv_venda_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdv_venda_id')->constrained('pdv_vendas')->cascadeOnDelete();
            $table->string('forma', 30);
            $table->decimal('valor', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_venda_pagamentos');

        Schema::table('pdv_caixa_movimentos', function (Blueprint $table) {
            $table->dropColumn('sangria_destino');
        });

        Schema::table('pdv_venda_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_grade_id');
            $table->dropColumn(['observacao', 'desconto']);
        });

        Schema::table('pdv_vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
            $table->dropConstrainedForeignId('vendedor_id');
            $table->dropColumn([
                'cpf_nota',
                'vendedor_nome',
                'subtotal',
                'desconto',
                'acrescimo',
                'troco',
                'dinheiro',
                'situacao',
            ]);
        });
    }
};
