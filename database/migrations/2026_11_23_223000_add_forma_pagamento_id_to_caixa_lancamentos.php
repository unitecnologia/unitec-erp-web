<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meio de pagamento opcional no lançamento manual do Livro Caixa.
 * Antigos permanecem NULL → consulta exibe "Não informado".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caixa_lancamentos')) {
            return;
        }

        if (Schema::hasColumn('caixa_lancamentos', 'forma_pagamento_id')) {
            return;
        }

        Schema::table('caixa_lancamentos', function (Blueprint $table): void {
            $table->unsignedBigInteger('forma_pagamento_id')->nullable()->after('caixa_conta_id');
            $table->index('forma_pagamento_id', 'caixa_lancamentos_forma_pagamento_id_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('caixa_lancamentos')) {
            return;
        }

        if (! Schema::hasColumn('caixa_lancamentos', 'forma_pagamento_id')) {
            return;
        }

        Schema::table('caixa_lancamentos', function (Blueprint $table): void {
            $table->dropIndex('caixa_lancamentos_forma_pagamento_id_index');
            $table->dropColumn('forma_pagamento_id');
        });
    }
};
