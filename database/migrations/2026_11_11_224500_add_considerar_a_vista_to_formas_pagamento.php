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
            if (! Schema::hasColumn('formas_pagamento', 'considerar_a_vista')) {
                $after = Schema::hasColumn('formas_pagamento', 'usa_super_tef')
                    ? 'usa_super_tef'
                    : (Schema::hasColumn('formas_pagamento', 'gerar_qrcode_pdv') ? 'gerar_qrcode_pdv' : null);
                $col = $table->boolean('considerar_a_vista')->default(false);
                if ($after !== null) {
                    $col->after($after);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('formas_pagamento') || ! Schema::hasColumn('formas_pagamento', 'considerar_a_vista')) {
            return;
        }

        Schema::table('formas_pagamento', function (Blueprint $table): void {
            $table->dropColumn('considerar_a_vista');
        });
    }
};
