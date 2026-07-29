<?php

use App\Support\Erp\EmpresaParametros;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos novos da aba Imposto Padrão (espelho do produto).
     *
     * @var list<string>
     */
    private const NEW_FIELDS = [
        'param_imp_origem',
        'param_imp_cfop_externo',
        'param_imp_icms_cst_externo',
        'param_imp_csosn_externo',
        'param_imp_icms_aliquota_externo',
        'param_imp_cod_enq_ipi',
        'param_imp_fcp_pct',
        'param_imp_mva_pct',
        'param_imp_mva_normal',
        'param_imp_reducao_base_pct',
        'param_imp_cod_beneficio',
        'param_imp_tipo_tributacao',
        'param_imp_icms_diferido',
        'param_imp_aliq_deson',
        'param_imp_motivo_desoneracao',
        'param_imp_iva_cst',
        'param_imp_aliq_ibs_uf',
        'param_imp_aliq_cbs',
        'param_imp_aliq_ibs_mun',
        'param_imp_aliq_adrem_ibs',
        'param_imp_aliq_adrem_cbs',
        'param_imp_reducao_cbs',
        'param_imp_reducao_ibs',
    ];

    public function up(): void
    {
        $defs = EmpresaParametros::impostoFields();

        Schema::table('empresas', function (Blueprint $table) use ($defs) {
            foreach (self::NEW_FIELDS as $field) {
                if (Schema::hasColumn('empresas', $field) || ! isset($defs[$field])) {
                    continue;
                }

                $meta = $defs[$field];
                $default = $meta['default'];

                match ($meta['type']) {
                    'decimal' => $table->decimal($field, 12, $meta['decimals'] ?? 2)->default($default ?? '0.00'),
                    default => $table->string($field, 40)->nullable()->default($default === '' ? null : (string) $default),
                };
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $drop = array_values(array_filter(
                self::NEW_FIELDS,
                fn (string $field): bool => Schema::hasColumn('empresas', $field),
            ));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
