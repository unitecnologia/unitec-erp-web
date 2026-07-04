<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orcamentos', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->default(0)->after('vendedor_id');
            $table->decimal('percentual_desconto', 8, 2)->default(0)->after('subtotal');
            $table->decimal('desconto_valor', 15, 2)->default(0)->after('percentual_desconto');
            $table->string('forma_pagamento', 120)->nullable()->after('desconto_valor');
            $table->unsignedSmallInteger('validade_dias')->default(0)->after('forma_pagamento');
            $table->text('observacoes')->nullable()->after('validade_dias');
        });

        Schema::table('orcamento_itens', function (Blueprint $table) {
            $table->unsignedSmallInteger('item')->default(1)->after('orcamento_id');
            $table->decimal('desconto', 12, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('orcamento_itens', function (Blueprint $table) {
            $table->dropColumn(['item', 'desconto']);
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal',
                'percentual_desconto',
                'desconto_valor',
                'forma_pagamento',
                'validade_dias',
                'observacoes',
            ]);
        });
    }
};
