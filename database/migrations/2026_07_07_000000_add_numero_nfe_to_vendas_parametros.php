<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            $table->unsignedInteger('numero_nfe')->nullable()->after('serie_nfe');
        });

        DB::table('vendas_parametros')
            ->orderBy('empresa_id')
            ->lazy()
            ->each(function (object $row): void {
                $max = DB::table('nfes')
                    ->where('empresa_id', $row->empresa_id)
                    ->pluck('numero')
                    ->map(fn (mixed $numero): int => (int) preg_replace('/\D/', '', (string) $numero))
                    ->max();

                $numeroNfe = $max !== null ? $max + 1 : 1;

                DB::table('vendas_parametros')
                    ->where('empresa_id', $row->empresa_id)
                    ->update(['numero_nfe' => $numeroNfe]);
            });
    }

    public function down(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            $table->dropColumn('numero_nfe');
        });
    }
};
