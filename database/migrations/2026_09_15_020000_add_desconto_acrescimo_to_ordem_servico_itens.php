<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordem_servico_itens', function (Blueprint $table): void {
            if (! Schema::hasColumn('ordem_servico_itens', 'desconto')) {
                $table->decimal('desconto', 15, 2)->default(0)->after('preco');
            }

            if (! Schema::hasColumn('ordem_servico_itens', 'acrescimo')) {
                $table->decimal('acrescimo', 15, 2)->default(0)->after('desconto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordem_servico_itens', function (Blueprint $table): void {
            if (Schema::hasColumn('ordem_servico_itens', 'acrescimo')) {
                $table->dropColumn('acrescimo');
            }

            if (Schema::hasColumn('ordem_servico_itens', 'desconto')) {
                $table->dropColumn('desconto');
            }
        });
    }
};
