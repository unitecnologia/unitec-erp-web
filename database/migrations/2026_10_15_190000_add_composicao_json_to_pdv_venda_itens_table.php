<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_venda_itens')) {
            return;
        }

        Schema::table('pdv_venda_itens', function (Blueprint $table): void {
            if (! Schema::hasColumn('pdv_venda_itens', 'composicao_json')) {
                $table->text('composicao_json')->nullable()->after('observacao');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pdv_venda_itens')) {
            return;
        }

        Schema::table('pdv_venda_itens', function (Blueprint $table): void {
            if (Schema::hasColumn('pdv_venda_itens', 'composicao_json')) {
                $table->dropColumn('composicao_json');
            }
        });
    }
};
