<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pix_cobrancas')) {
            return;
        }

        Schema::table('pix_cobrancas', function (Blueprint $table): void {
            if (! Schema::hasColumn('pix_cobrancas', 'pdv_venda_id')) {
                $table->unsignedBigInteger('pdv_venda_id')->nullable()->index();
            }
            if (! Schema::hasColumn('pix_cobrancas', 'pdv_venda_pagamento_id')) {
                $table->unsignedBigInteger('pdv_venda_pagamento_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pix_cobrancas')) {
            return;
        }

        Schema::table('pix_cobrancas', function (Blueprint $table): void {
            if (Schema::hasColumn('pix_cobrancas', 'pdv_venda_pagamento_id')) {
                $table->dropColumn('pdv_venda_pagamento_id');
            }
            if (Schema::hasColumn('pix_cobrancas', 'pdv_venda_id')) {
                $table->dropColumn('pdv_venda_id');
            }
        });
    }
};
