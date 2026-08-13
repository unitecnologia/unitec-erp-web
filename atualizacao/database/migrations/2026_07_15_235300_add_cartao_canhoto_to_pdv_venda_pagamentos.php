<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_venda_pagamentos', function (Blueprint $table): void {
            if (! Schema::hasColumn('pdv_venda_pagamentos', 'cartao_nsu')) {
                $table->string('cartao_nsu', 40)->nullable()->after('valor');
            }
            if (! Schema::hasColumn('pdv_venda_pagamentos', 'cartao_autorizacao')) {
                $table->string('cartao_autorizacao', 40)->nullable()->after('cartao_nsu');
            }
            if (! Schema::hasColumn('pdv_venda_pagamentos', 'cartao_maquininha')) {
                $table->string('cartao_maquininha', 60)->nullable()->after('cartao_autorizacao');
            }
            if (! Schema::hasColumn('pdv_venda_pagamentos', 'cartao_bandeira')) {
                $table->string('cartao_bandeira', 40)->nullable()->after('cartao_maquininha');
            }
            if (! Schema::hasColumn('pdv_venda_pagamentos', 'cartao_parcela')) {
                $table->string('cartao_parcela', 20)->nullable()->after('cartao_bandeira');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pdv_venda_pagamentos', function (Blueprint $table): void {
            foreach (['cartao_nsu', 'cartao_autorizacao', 'cartao_maquininha', 'cartao_bandeira', 'cartao_parcela'] as $col) {
                if (Schema::hasColumn('pdv_venda_pagamentos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
