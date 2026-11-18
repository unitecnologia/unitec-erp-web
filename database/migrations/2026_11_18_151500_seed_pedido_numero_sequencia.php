<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inicializa a sequência global do DAV (chave `pedido` em venda_numero_sequencias).
 * Não cria tabela nova — reutiliza o mecanismo já usado por vendas/cargas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venda_numero_sequencias') || ! Schema::hasTable('pedidos')) {
            return;
        }

        $exists = DB::table('venda_numero_sequencias')
            ->where('chave', 'pedido')
            ->exists();

        if ($exists) {
            return;
        }

        // Índice único em numero: MAX textual basta (DAV zero-padded).
        $numero = DB::table('pedidos')->max('numero');
        $max = max(0, (int) preg_replace('/\D/', '', (string) ($numero ?? '0')));

        DB::table('venda_numero_sequencias')->insert([
            'chave' => 'pedido',
            'ultimo_numero' => max(0, $max),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('venda_numero_sequencias')) {
            return;
        }

        DB::table('venda_numero_sequencias')->where('chave', 'pedido')->delete();
    }
};
