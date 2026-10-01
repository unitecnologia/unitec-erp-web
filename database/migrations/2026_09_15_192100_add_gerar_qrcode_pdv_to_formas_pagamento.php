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

        Schema::table('formas_pagamento', function (Blueprint $table) {
            if (! Schema::hasColumn('formas_pagamento', 'gerar_qrcode_pdv')) {
                $table->boolean('gerar_qrcode_pdv')->default(false)->after('disponivel_mobile');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('formas_pagamento') || ! Schema::hasColumn('formas_pagamento', 'gerar_qrcode_pdv')) {
            return;
        }

        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->dropColumn('gerar_qrcode_pdv');
        });
    }
};
