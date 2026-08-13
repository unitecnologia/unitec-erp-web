<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cartao_maquininhas')) {
            Schema::create('cartao_maquininhas', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('codigo')->unique();
                $table->string('nome', 60)->unique();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        $now = now();
        $maquininhas = [
            1 => 'STONE',
            2 => 'CIELO',
            3 => 'REDE',
            4 => 'GETNET',
            5 => 'PAGBANK',
            6 => 'MERCADO PAGO',
            7 => 'SUMUP',
            8 => 'SAFRAPAY',
            9 => 'INFINITEPAY',
            10 => 'TON',
            11 => 'SIPAG',
            12 => 'PICPAY',
            13 => 'BIN',
            14 => 'FISERV',
            15 => 'PAGARME',
            16 => 'ZOOP',
            17 => 'SICOOB',
            18 => 'SICREDI',
        ];

        foreach ($maquininhas as $codigo => $nome) {
            $exists = DB::table('cartao_maquininhas')
                ->where('codigo', $codigo)
                ->orWhere('nome', $nome)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('cartao_maquininhas')->insert([
                'codigo' => $codigo,
                'nome' => $nome,
                'ativo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cartao_maquininhas');
    }
};
