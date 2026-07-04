<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venda_numero_sequencias', function (Blueprint $table) {
            $table->string('chave', 32)->primary();
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });

        $max = DB::table('vendas')
            ->pluck('numero')
            ->map(fn (?string $numero): int => (int) preg_replace('/\D/', '', (string) $numero))
            ->max() ?? 0;

        DB::table('venda_numero_sequencias')->insert([
            'chave' => 'global',
            'ultimo_numero' => $max,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('venda_numero_sequencias');
    }
};
