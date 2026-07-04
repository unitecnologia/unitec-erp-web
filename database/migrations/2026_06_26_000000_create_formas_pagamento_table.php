<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formas_pagamento', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('codigo')->unique();
            $table->string('descricao', 120);
            $table->string('tipo', 30)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        $now = now();

        $padrao = [
            ['codigo' => 1, 'descricao' => 'DINHEIRO', 'tipo' => 'dinheiro'],
            ['codigo' => 2, 'descricao' => 'PIX', 'tipo' => 'pix'],
            ['codigo' => 3, 'descricao' => 'POS DEBITO', 'tipo' => 'cartao_debito'],
            ['codigo' => 4, 'descricao' => 'POS CREDITO', 'tipo' => 'cartao_credito'],
            ['codigo' => 5, 'descricao' => 'DEPOSITO', 'tipo' => 'deposito'],
            ['codigo' => 6, 'descricao' => 'TEF', 'tipo' => 'tef'],
            ['codigo' => 8, 'descricao' => 'TROCA', 'tipo' => 'troca'],
        ];

        DB::table('formas_pagamento')->insert(array_map(static function (array $row) use ($now): array {
            return [
                ...$row,
                'ativo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $padrao));
    }

    public function down(): void
    {
        Schema::dropIfExists('formas_pagamento');
    }
};
