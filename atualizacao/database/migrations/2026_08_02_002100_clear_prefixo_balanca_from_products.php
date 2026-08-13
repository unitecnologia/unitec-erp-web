<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLU da balança passa a ser sempre o codigo do produto.
 * Zera prefixo_balanca legado (não é mais usado no cadastro/export).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'prefixo_balanca')) {
            return;
        }

        DB::table('products')
            ->whereNotNull('prefixo_balanca')
            ->where('prefixo_balanca', '!=', '')
            ->update(['prefixo_balanca' => null]);
    }

    public function down(): void
    {
        // Dados legados não são restaurados.
    }
};
