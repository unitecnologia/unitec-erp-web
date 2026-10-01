<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Planos de conta removidos da aba Empresa → Parâmetros (defaults fixos no PDV/caixa).
     *
     * @return list<string>
     */
    private function columns(): array
    {
        return [
            'param_plano_venda',
            'param_plano_devolucao',
            'param_plano_sangria',
            'param_plano_abertura_caixa',
            'param_plano_ficha_cliente',
        ];
    }

    public function up(): void
    {
        $existing = array_values(array_filter(
            $this->columns(),
            fn (string $column): bool => Schema::hasColumn('empresas', $column),
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_plano_venda')) {
                $table->integer('param_plano_venda')->default(2);
            }

            if (! Schema::hasColumn('empresas', 'param_plano_devolucao')) {
                $table->integer('param_plano_devolucao')->default(9);
            }

            if (! Schema::hasColumn('empresas', 'param_plano_sangria')) {
                $table->integer('param_plano_sangria')->default(11);
            }

            if (! Schema::hasColumn('empresas', 'param_plano_abertura_caixa')) {
                $table->integer('param_plano_abertura_caixa')->default(14);
            }

            if (! Schema::hasColumn('empresas', 'param_plano_ficha_cliente')) {
                $table->integer('param_plano_ficha_cliente')->default(10);
            }
        });
    }
};
