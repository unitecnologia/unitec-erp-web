<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfe_itens', function (Blueprint $table): void {
            $table->string('motivo_desoneracao', 2)->nullable()->after('valor_icms');
            $table->decimal('base_desoneracao', 15, 2)->default(0)->after('motivo_desoneracao');
            $table->decimal('desc_desoneracao', 15, 2)->default(0)->after('base_desoneracao');
            $table->decimal('valor_desoneracao', 15, 2)->default(0)->after('desc_desoneracao');
            $table->string('class_trib', 10)->nullable()->after('vfcp');
            $table->string('cst_ibs_cbs', 3)->nullable()->after('class_trib');
            $table->decimal('v_ibs_mun', 15, 2)->default(0)->after('cst_ibs_cbs');
            $table->decimal('v_ibs_uf', 15, 2)->default(0)->after('v_ibs_mun');
            $table->decimal('v_cbs', 15, 2)->default(0)->after('v_ibs_uf');
            $table->decimal('bc_ibs', 15, 2)->default(0)->after('v_cbs');
            $table->decimal('alq_cbs', 15, 4)->default(0)->after('bc_ibs');
            $table->decimal('alq_ibs_mun', 15, 4)->default(0)->after('alq_cbs');
            $table->decimal('alq_ibs_uf', 15, 4)->default(0)->after('alq_ibs_mun');
        });
    }

    public function down(): void
    {
        Schema::table('nfe_itens', function (Blueprint $table): void {
            $table->dropColumn([
                'motivo_desoneracao',
                'base_desoneracao',
                'desc_desoneracao',
                'valor_desoneracao',
                'class_trib',
                'cst_ibs_cbs',
                'v_ibs_mun',
                'v_ibs_uf',
                'v_cbs',
                'bc_ibs',
                'alq_cbs',
                'alq_ibs_mun',
                'alq_ibs_uf',
            ]);
        });
    }
};
