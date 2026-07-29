<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cartao_bandeiras')) {
            Schema::create('cartao_bandeiras', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('codigo')->unique();
                $table->string('nome', 60)->unique();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        $now = now();
        $bandeiras = [
            1 => 'VISA',
            2 => 'MASTERCARD',
            3 => 'ELO',
            4 => 'AMERICAN EXPRESS',
            5 => 'HIPERCARD',
            6 => 'DINERS CLUB',
            7 => 'CABAL',
            8 => 'SOROCRED',
            9 => 'BANESCARD',
            10 => 'AGIPLAN',
            11 => 'CREDSYSTEM',
            12 => 'CREDZ',
            13 => 'GOODCARD',
            14 => 'SICREDI',
            15 => 'AURA',
        ];

        foreach ($bandeiras as $codigo => $nome) {
            $exists = DB::table('cartao_bandeiras')
                ->where('codigo', $codigo)
                ->orWhere('nome', $nome)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('cartao_bandeiras')->insert([
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
        Schema::dropIfExists('cartao_bandeiras');
    }
};
