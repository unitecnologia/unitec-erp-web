<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag por forma: canhoto POS manual (maquininha), independente do tipo_movimento.
 * Default false — não altera cadastros existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('formas_pagamento')) {
            return;
        }

        Schema::table('formas_pagamento', function (Blueprint $table): void {
            if (! Schema::hasColumn('formas_pagamento', 'usa_pos')) {
                $after = Schema::hasColumn('formas_pagamento', 'usa_tef')
                    ? 'usa_tef'
                    : (Schema::hasColumn('formas_pagamento', 'tipo_movimento') ? 'tipo_movimento' : null);
                $col = $table->boolean('usa_pos')->default(false);
                if ($after !== null) {
                    $col->after($after);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('formas_pagamento') || ! Schema::hasColumn('formas_pagamento', 'usa_pos')) {
            return;
        }

        Schema::table('formas_pagamento', function (Blueprint $table): void {
            $table->dropColumn('usa_pos');
        });
    }
};
