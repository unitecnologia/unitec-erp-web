<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parâmetros mortos/duplicados removidos da aba Empresa → Parâmetros.
     *
     * @return list<string>
     */
    private function columns(): array
    {
        return [
            'param_cod_caixa_geral',
            'param_empresa_padrao_relatorios',
            'param_ultimo_nsu',
            'param_nfe_serie',
            'param_plano_compra',
            'param_plano_boleto',
            'param_plano_taxa_cartao',
            'param_plano_transferencia_credito',
            'param_plano_transferencia_debito',
            'param_cod_dinheiro_fpg',
            'param_pdv_modelo_balanca',
            'param_pdv_carga_intervalo_min',
            'param_pdv_marquee_texto',
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
            if (! Schema::hasColumn('empresas', 'param_cod_caixa_geral')) {
                $table->integer('param_cod_caixa_geral')->default(1);
            }

            if (! Schema::hasColumn('empresas', 'param_empresa_padrao_relatorios')) {
                $table->integer('param_empresa_padrao_relatorios')->default(1);
            }

            if (! Schema::hasColumn('empresas', 'param_ultimo_nsu')) {
                $table->string('param_ultimo_nsu')->default('0000000000');
            }

            if (! Schema::hasColumn('empresas', 'param_nfe_serie')) {
                $table->integer('param_nfe_serie')->default(1);
            }

            if (! Schema::hasColumn('empresas', 'param_plano_compra')) {
                $table->integer('param_plano_compra')->default(15);
            }

            if (! Schema::hasColumn('empresas', 'param_plano_boleto')) {
                $table->integer('param_plano_boleto')->default(16);
            }

            if (! Schema::hasColumn('empresas', 'param_plano_taxa_cartao')) {
                $table->integer('param_plano_taxa_cartao')->default(8);
            }

            if (! Schema::hasColumn('empresas', 'param_plano_transferencia_credito')) {
                $table->integer('param_plano_transferencia_credito')->default(3);
            }

            if (! Schema::hasColumn('empresas', 'param_plano_transferencia_debito')) {
                $table->integer('param_plano_transferencia_debito')->default(4);
            }

            if (! Schema::hasColumn('empresas', 'param_cod_dinheiro_fpg')) {
                $table->integer('param_cod_dinheiro_fpg')->default(1);
            }

            if (! Schema::hasColumn('empresas', 'param_pdv_modelo_balanca')) {
                $table->unsignedTinyInteger('param_pdv_modelo_balanca')->default(4);
            }

            if (! Schema::hasColumn('empresas', 'param_pdv_carga_intervalo_min')) {
                $table->integer('param_pdv_carga_intervalo_min')->default(15);
            }

            if (! Schema::hasColumn('empresas', 'param_pdv_marquee_texto')) {
                $table->string('param_pdv_marquee_texto')->nullable();
            }
        });
    }
};
