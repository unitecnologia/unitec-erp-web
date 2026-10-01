<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabelas_prazo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forma_pagamento_id')->constrained('formas_pagamento')->cascadeOnDelete();
            $table->string('dias', 191);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });

        $boletoId = DB::table('formas_pagamento')
            ->where('codigo', 5)
            ->where('tipo', 'boleto')
            ->value('id');

        if ($boletoId) {
            $now = now();
            DB::table('tabelas_prazo')->insert([
                'forma_pagamento_id' => $boletoId,
                'dias' => '30,60,90',
                'ordem' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tabelas_prazo');
    }
};
