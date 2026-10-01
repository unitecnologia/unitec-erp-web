<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_itens')) {
            return;
        }

        Schema::table('nfe_itens', function (Blueprint $table): void {
            if (! Schema::hasColumn('nfe_itens', 'origem')) {
                $table->unsignedTinyInteger('origem')->nullable()->after('csosn');
            }

            if (! Schema::hasColumn('nfe_itens', 'p_red_ibs')) {
                $table->decimal('p_red_ibs', 15, 4)->default(0)->after('alq_ibs_uf');
            }

            if (! Schema::hasColumn('nfe_itens', 'p_red_cbs')) {
                $table->decimal('p_red_cbs', 15, 4)->default(0)->after('p_red_ibs');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('nfe_itens')) {
            return;
        }

        Schema::table('nfe_itens', function (Blueprint $table): void {
            $cols = array_values(array_filter([
                Schema::hasColumn('nfe_itens', 'origem') ? 'origem' : null,
                Schema::hasColumn('nfe_itens', 'p_red_ibs') ? 'p_red_ibs' : null,
                Schema::hasColumn('nfe_itens', 'p_red_cbs') ? 'p_red_cbs' : null,
            ]));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
