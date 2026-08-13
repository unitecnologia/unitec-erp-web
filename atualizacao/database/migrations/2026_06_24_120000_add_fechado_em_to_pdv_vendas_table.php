<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pdv_vendas', 'fechado_em')) {
            Schema::table('pdv_vendas', function (Blueprint $table) {
                $table->timestamp('fechado_em')->nullable()->after('situacao');
            });
        }

        DB::table('pdv_vendas')
            ->whereNull('fechado_em')
            ->update(['fechado_em' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('pdv_vendas', 'fechado_em')) {
            Schema::table('pdv_vendas', function (Blueprint $table) {
                $table->dropColumn('fechado_em');
            });
        }
    }
};
