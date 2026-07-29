<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $columns = collect(['comissao_pct', 'desconto_pct', 'preco_venda_prazo'])
                ->filter(fn (string $column): bool => Schema::hasColumn('products', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'preco_venda_prazo')) {
                $table->decimal('preco_venda_prazo', 12, 2)->default(0)->after('preco_venda');
            }

            if (! Schema::hasColumn('products', 'comissao_pct')) {
                $table->decimal('comissao_pct', 8, 2)->default(0)->after('preco_especial');
            }

            if (! Schema::hasColumn('products', 'desconto_pct')) {
                $table->decimal('desconto_pct', 8, 2)->default(0)->after('comissao_pct');
            }
        });
    }
};
