<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cartao_bandeiras') && ! Schema::hasColumn('cartao_bandeiras', 'ordem')) {
            Schema::table('cartao_bandeiras', function (Blueprint $table): void {
                $table->unsignedInteger('ordem')->default(100)->after('nome');
            });
        }

        if (Schema::hasTable('cartao_maquininhas') && ! Schema::hasColumn('cartao_maquininhas', 'ordem')) {
            Schema::table('cartao_maquininhas', function (Blueprint $table): void {
                $table->unsignedInteger('ordem')->default(100)->after('nome');
            });
        }

        $bandeiraOrdem = [
            'MASTERCARD' => 1,
            'VISA' => 2,
            'ELO' => 3,
            'HIPERCARD' => 4,
            'AMERICAN EXPRESS' => 5,
        ];

        foreach ($bandeiraOrdem as $nome => $ordem) {
            DB::table('cartao_bandeiras')->where('nome', $nome)->update(['ordem' => $ordem]);
        }

        DB::table('cartao_bandeiras')
            ->whereNotIn('nome', array_keys($bandeiraOrdem))
            ->update(['ordem' => 100]);

        $maquininhaOrdem = [
            'REDE' => 1,
            'STONE' => 2,
            'GETNET' => 3,
            'ITAU' => 4,
            'CIELO' => 5,
        ];

        $now = now();
        if (! DB::table('cartao_maquininhas')->where('nome', 'ITAU')->exists()) {
            $codigo = (int) (DB::table('cartao_maquininhas')->max('codigo') ?? 0) + 1;
            DB::table('cartao_maquininhas')->insert([
                'codigo' => $codigo,
                'nome' => 'ITAU',
                'ordem' => 4,
                'ativo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($maquininhaOrdem as $nome => $ordem) {
            DB::table('cartao_maquininhas')->where('nome', $nome)->update(['ordem' => $ordem]);
        }

        DB::table('cartao_maquininhas')
            ->whereNotIn('nome', array_keys($maquininhaOrdem))
            ->update(['ordem' => 100]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('cartao_bandeiras', 'ordem')) {
            Schema::table('cartao_bandeiras', function (Blueprint $table): void {
                $table->dropColumn('ordem');
            });
        }

        if (Schema::hasColumn('cartao_maquininhas', 'ordem')) {
            Schema::table('cartao_maquininhas', function (Blueprint $table): void {
                $table->dropColumn('ordem');
            });
        }
    }
};
