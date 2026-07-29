<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function columns(): array
    {
        return [
            'param_pdv_exibir_estoque_negativo',
            'param_pdv_bloquear_preco',
            'param_pdv_bloquear_inatividade',
            'param_tempo_bloqueio_pdv_min',
            'param_pdv_exibir_f4_busca_avancada',
            'param_geral_transmitir_cartao_auto',
            'param_geral_usar_pdv_retaguarda',
            'param_geral_ocultar_saldo_livro_caixa',
            'param_geral_usar_smtp_proprio',
            'param_fiscal_perguntar_segunda_via_nfce',
            'param_fiscal_usar_nfe_num_inicial',
            'param_nfe_num_inicial',
            'param_fiscal_resp_tecnico_xml',
            'param_fiscal_abrir_whatsapp_inicio',
            'param_fiscal_imposto_custo_xml',
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
            if (! Schema::hasColumn('empresas', 'param_pdv_exibir_estoque_negativo')) {
                $table->boolean('param_pdv_exibir_estoque_negativo')->default(true);
            }

            if (! Schema::hasColumn('empresas', 'param_pdv_bloquear_preco')) {
                $table->boolean('param_pdv_bloquear_preco')->default(false);
            }

            if (! Schema::hasColumn('empresas', 'param_pdv_bloquear_inatividade')) {
                $table->boolean('param_pdv_bloquear_inatividade')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_tempo_bloqueio_pdv_min')) {
                $table->unsignedInteger('param_tempo_bloqueio_pdv_min')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_pdv_exibir_f4_busca_avancada')) {
                $table->boolean('param_pdv_exibir_f4_busca_avancada')->default(false);
            }

            if (! Schema::hasColumn('empresas', 'param_geral_transmitir_cartao_auto')) {
                $table->boolean('param_geral_transmitir_cartao_auto')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_geral_usar_pdv_retaguarda')) {
                $table->boolean('param_geral_usar_pdv_retaguarda')->default(true);
            }

            if (! Schema::hasColumn('empresas', 'param_geral_ocultar_saldo_livro_caixa')) {
                $table->boolean('param_geral_ocultar_saldo_livro_caixa')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_geral_usar_smtp_proprio')) {
                $table->boolean('param_geral_usar_smtp_proprio')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_fiscal_perguntar_segunda_via_nfce')) {
                $table->boolean('param_fiscal_perguntar_segunda_via_nfce')->default(false);
            }

            if (! Schema::hasColumn('empresas', 'param_fiscal_usar_nfe_num_inicial')) {
                $table->boolean('param_fiscal_usar_nfe_num_inicial')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_nfe_num_inicial')) {
                $table->unsignedInteger('param_nfe_num_inicial')->nullable()->default(1);
            }

            if (! Schema::hasColumn('empresas', 'param_fiscal_resp_tecnico_xml')) {
                $table->boolean('param_fiscal_resp_tecnico_xml')->default(true);
            }

            if (! Schema::hasColumn('empresas', 'param_fiscal_abrir_whatsapp_inicio')) {
                $table->boolean('param_fiscal_abrir_whatsapp_inicio')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_fiscal_imposto_custo_xml')) {
                $table->boolean('param_fiscal_imposto_custo_xml')->default(true);
            }
        });
    }
};
