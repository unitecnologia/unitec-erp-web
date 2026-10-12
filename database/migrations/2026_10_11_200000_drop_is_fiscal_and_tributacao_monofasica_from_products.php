<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove parâmetros mortos do cadastro de produtos:
     * É Fiscal (is_fiscal) e Tributação Monofásica (tributacao_monofasica).
     *
     * @return list<string>
     */
    private function columns(): array
    {
        return [
            'is_fiscal',
            'tributacao_monofasica',
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $existing = array_values(array_filter(
            $this->columns(),
            fn (string $column): bool => Schema::hasColumn('products', $column),
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('products', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'is_fiscal')) {
                $table->boolean('is_fiscal')->default(true)->after('ativo');
            }

            if (! Schema::hasColumn('products', 'tributacao_monofasica')) {
                $table->boolean('tributacao_monofasica')->default(false)->after('cod_beneficio');
            }
        });
    }
};
