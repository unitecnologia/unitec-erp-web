<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'iva_cst')) {
                $table->string('iva_cst', 3)->nullable()->after('cod_beneficio');
            }
            if (! Schema::hasColumn('products', 'cclass_trib')) {
                $table->string('cclass_trib', 10)->nullable()->after('iva_cst');
            }
            if (! Schema::hasColumn('products', 'cclass_trib_descricao')) {
                $table->string('cclass_trib_descricao', 255)->nullable()->after('cclass_trib');
            }
            if (! Schema::hasColumn('products', 'aliq_ibs_uf')) {
                $table->decimal('aliq_ibs_uf', 8, 4)->default(0)->after('cclass_trib_descricao');
            }
            if (! Schema::hasColumn('products', 'aliq_cbs')) {
                $table->decimal('aliq_cbs', 8, 4)->default(0)->after('aliq_ibs_uf');
            }
            if (! Schema::hasColumn('products', 'aliq_ibs_mun')) {
                $table->decimal('aliq_ibs_mun', 8, 4)->default(0)->after('aliq_cbs');
            }
            if (! Schema::hasColumn('products', 'aliq_adrem_ibs')) {
                $table->decimal('aliq_adrem_ibs', 8, 4)->default(0)->after('aliq_ibs_mun');
            }
            if (! Schema::hasColumn('products', 'aliq_adrem_cbs')) {
                $table->decimal('aliq_adrem_cbs', 8, 4)->default(0)->after('aliq_adrem_ibs');
            }
            if (! Schema::hasColumn('products', 'reducao_cbs')) {
                $table->decimal('reducao_cbs', 8, 4)->default(0)->after('aliq_adrem_cbs');
            }
            if (! Schema::hasColumn('products', 'reducao_ibs')) {
                $table->decimal('reducao_ibs', 8, 4)->default(0)->after('reducao_cbs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $cols = [
                'iva_cst',
                'cclass_trib',
                'cclass_trib_descricao',
                'aliq_ibs_uf',
                'aliq_cbs',
                'aliq_ibs_mun',
                'aliq_adrem_ibs',
                'aliq_adrem_cbs',
                'reducao_cbs',
                'reducao_ibs',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
