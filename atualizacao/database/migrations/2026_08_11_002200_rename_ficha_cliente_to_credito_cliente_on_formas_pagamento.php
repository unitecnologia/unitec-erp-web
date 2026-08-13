<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('formas_pagamento') || ! Schema::hasColumn('formas_pagamento', 'tipo_movimento')) {
            return;
        }

        DB::table('formas_pagamento')
            ->where('tipo_movimento', 'ficha_cliente')
            ->update(['tipo_movimento' => 'credito_cliente']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('formas_pagamento') || ! Schema::hasColumn('formas_pagamento', 'tipo_movimento')) {
            return;
        }

        DB::table('formas_pagamento')
            ->where('tipo_movimento', 'credito_cliente')
            ->update(['tipo_movimento' => 'ficha_cliente']);
    }
};
