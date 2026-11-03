<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('formas_pagamento')) {
            return;
        }

        Schema::table('formas_pagamento', function (Blueprint $table): void {
            if (! Schema::hasColumn('formas_pagamento', 'nao_pagar_comissao')) {
                $after = Schema::hasColumn('formas_pagamento', 'nfce') ? 'nfce' : null;
                $col = $table->boolean('nao_pagar_comissao')->default(false);
                if ($after !== null) {
                    $col->after($after);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('formas_pagamento') || ! Schema::hasColumn('formas_pagamento', 'nao_pagar_comissao')) {
            return;
        }

        Schema::table('formas_pagamento', function (Blueprint $table): void {
            $table->dropColumn('nao_pagar_comissao');
        });
    }
};
