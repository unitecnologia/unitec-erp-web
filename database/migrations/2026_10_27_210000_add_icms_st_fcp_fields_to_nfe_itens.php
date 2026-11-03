<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos estruturados de ICMS-ST / FCP na NF-e (item e totais).
 * ST retida é informativa e não se confunde com ST cobrada (base_icms_st / valor_icms_st).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfe_itens', function (Blueprint $table) {
            if (! Schema::hasColumn('nfe_itens', 'mod_bc_st')) {
                $table->string('mod_bc_st', 1)->nullable()->after('valor_icms_st');
            }
            if (! Schema::hasColumn('nfe_itens', 'p_mva_st')) {
                $table->decimal('p_mva_st', 7, 4)->default(0)->after('mod_bc_st');
            }
            if (! Schema::hasColumn('nfe_itens', 'p_red_bc_st')) {
                $table->decimal('p_red_bc_st', 7, 4)->default(0)->after('p_mva_st');
            }
            if (! Schema::hasColumn('nfe_itens', 'p_fcp')) {
                $table->decimal('p_fcp', 7, 4)->default(0)->after('vfcp');
            }
            if (! Schema::hasColumn('nfe_itens', 'v_bc_fcp')) {
                $table->decimal('v_bc_fcp', 15, 2)->default(0)->after('p_fcp');
            }
            if (! Schema::hasColumn('nfe_itens', 'v_bc_fcp_st')) {
                $table->decimal('v_bc_fcp_st', 15, 2)->default(0)->after('v_bc_fcp');
            }
            if (! Schema::hasColumn('nfe_itens', 'p_fcp_st')) {
                $table->decimal('p_fcp_st', 7, 4)->default(0)->after('v_bc_fcp_st');
            }
            if (! Schema::hasColumn('nfe_itens', 'v_fcp_st')) {
                $table->decimal('v_fcp_st', 15, 2)->default(0)->after('p_fcp_st');
            }
            if (! Schema::hasColumn('nfe_itens', 'base_icms_st_ret')) {
                $table->decimal('base_icms_st_ret', 15, 2)->default(0)->after('v_fcp_st');
            }
            if (! Schema::hasColumn('nfe_itens', 'p_st_ret')) {
                $table->decimal('p_st_ret', 7, 4)->default(0)->after('base_icms_st_ret');
            }
            if (! Schema::hasColumn('nfe_itens', 'valor_icms_st_ret')) {
                $table->decimal('valor_icms_st_ret', 15, 2)->default(0)->after('p_st_ret');
            }
            if (! Schema::hasColumn('nfe_itens', 'v_bc_fcp_st_ret')) {
                $table->decimal('v_bc_fcp_st_ret', 15, 2)->default(0)->after('valor_icms_st_ret');
            }
            if (! Schema::hasColumn('nfe_itens', 'p_fcp_st_ret')) {
                $table->decimal('p_fcp_st_ret', 7, 4)->default(0)->after('v_bc_fcp_st_ret');
            }
            if (! Schema::hasColumn('nfe_itens', 'v_fcp_st_ret')) {
                $table->decimal('v_fcp_st_ret', 15, 2)->default(0)->after('p_fcp_st_ret');
            }
        });

        Schema::table('nfes', function (Blueprint $table) {
            if (! Schema::hasColumn('nfes', 'vfcp_st')) {
                $table->decimal('vfcp_st', 15, 2)->default(0)->after('vfcp');
            }
            if (! Schema::hasColumn('nfes', 'vfcp_st_ret')) {
                $table->decimal('vfcp_st_ret', 15, 2)->default(0)->after('vfcp_st');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nfe_itens', function (Blueprint $table) {
            foreach ([
                'mod_bc_st', 'p_mva_st', 'p_red_bc_st', 'p_fcp', 'v_bc_fcp',
                'v_bc_fcp_st', 'p_fcp_st', 'v_fcp_st',
                'base_icms_st_ret', 'p_st_ret', 'valor_icms_st_ret',
                'v_bc_fcp_st_ret', 'p_fcp_st_ret', 'v_fcp_st_ret',
            ] as $col) {
                if (Schema::hasColumn('nfe_itens', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('nfes', function (Blueprint $table) {
            foreach (['vfcp_st', 'vfcp_st_ret'] as $col) {
                if (Schema::hasColumn('nfes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
