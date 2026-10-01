<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa PEDIDO/DAV de ORÇAMENTO:
 * - pedidos / pedido_itens = documento do Monitor (DAV)
 * - orcamentos = só orçamento (tela Orçamentos + tipo orçamento do app)
 *
 * Não altera a tabela de produtos. A cópia dos DAVs antigos desliga a checagem
 * de FK só durante o insert, para pedido com produto já apagado não derrubar
 * a atualização do cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedidos')) {
            Schema::create('pedidos', function (Blueprint $table) {
                $table->id();
                $table->string('numero', 20)->unique();
                $table->date('data');
                $table->string('hora', 8)->nullable();
                $table->foreignId('cliente_id')->constrained('people')->cascadeOnDelete();
                $table->string('cliente_nome')->nullable();
                $table->string('cliente_cpf_cnpj', 20)->nullable();
                $table->string('cliente_endereco')->nullable();
                $table->string('cliente_numero', 20)->nullable();
                $table->string('cliente_bairro')->nullable();
                $table->string('cliente_cep', 12)->nullable();
                $table->string('cliente_cidade')->nullable();
                $table->string('cliente_uf', 2)->nullable();
                $table->string('cliente_fone', 30)->nullable();
                $table->string('cliente_whatsapp', 30)->nullable();
                $table->foreignId('vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('percentual_desconto', 8, 4)->default(0);
                $table->decimal('desconto_valor', 15, 2)->default(0);
                $table->string('forma_pagamento')->nullable();
                $table->unsignedSmallInteger('validade_dias')->default(0);
                $table->text('observacoes')->nullable();
                $table->decimal('total', 15, 2)->default(0);
                $table->string('status', 20)->default('aberto');
                $table->string('plataforma', 20)->nullable();
                $table->timestamps();

                $table->index('data');
                $table->index('status');
                $table->index('plataforma');
            });
        }

        if (! Schema::hasTable('pedido_itens')) {
            Schema::create('pedido_itens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
                $table->unsignedSmallInteger('item')->default(1);
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('product_grade_id')->nullable()->constrained('product_grades')->nullOnDelete();
                $table->decimal('quantidade', 12, 3);
                $table->decimal('preco_unitario', 12, 2);
                $table->decimal('desconto', 12, 2)->default(0);
                $table->decimal('total', 12, 2);
                $table->string('descricao')->nullable();
                $table->timestamps();

                $table->index(['pedido_id', 'product_id']);
                $table->index(['pedido_id', 'item']);
            });
        }

        if (Schema::hasTable('forca_vendas_orders') && ! Schema::hasColumn('forca_vendas_orders', 'pedido_id')) {
            Schema::table('forca_vendas_orders', function (Blueprint $table) {
                $table->foreignId('pedido_id')
                    ->nullable()
                    ->after('orcamento_id')
                    ->constrained('pedidos')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('estoque_reservas')) {
            Schema::table('estoque_reservas', function (Blueprint $table) {
                if (! Schema::hasColumn('estoque_reservas', 'pedido_id')) {
                    $table->foreignId('pedido_id')
                        ->nullable()
                        ->after('orcamento_id')
                        ->constrained('pedidos')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('estoque_reservas', 'pedido_item_id')) {
                    $table->unsignedBigInteger('pedido_item_id')->nullable()->after('pedido_id');
                }
            });
        }

        $this->semChecagemDeFk(fn () => $this->migrarPedidosFv());
    }

    public function down(): void
    {
        // Não reverte dados (evitar misturar de novo). Só remove estruturas se vazias.
        if (Schema::hasTable('estoque_reservas')) {
            Schema::table('estoque_reservas', function (Blueprint $table) {
                if (Schema::hasColumn('estoque_reservas', 'pedido_item_id')) {
                    $table->dropColumn('pedido_item_id');
                }
                if (Schema::hasColumn('estoque_reservas', 'pedido_id')) {
                    $table->dropConstrainedForeignId('pedido_id');
                }
            });
        }

        if (Schema::hasTable('forca_vendas_orders') && Schema::hasColumn('forca_vendas_orders', 'pedido_id')) {
            Schema::table('forca_vendas_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pedido_id');
            });
        }

        Schema::dropIfExists('pedido_itens');
        Schema::dropIfExists('pedidos');
    }

    private function migrarPedidosFv(): void
    {
        if (! Schema::hasTable('forca_vendas_orders') || ! Schema::hasTable('orcamentos')) {
            return;
        }

        $orders = DB::table('forca_vendas_orders')
            ->where('tipo', 'pedido')
            ->whereNotNull('orcamento_id')
            ->whereNull('pedido_id')
            ->orderBy('id')
            ->get(['id', 'orcamento_id']);

        foreach ($orders as $order) {
            $orc = DB::table('orcamentos')->where('id', $order->orcamento_id)->first();

            if ($orc === null) {
                continue;
            }

            $pedidoId = $this->pedidoIdParaOrcamento($orc);
            $itemMap = $this->itensPedidoParaOrcamento($pedidoId, $orc);

            DB::table('forca_vendas_orders')
                ->where('id', $order->id)
                ->update([
                    'pedido_id' => $pedidoId,
                    'orcamento_id' => null,
                ]);

            if (Schema::hasTable('estoque_reservas') && Schema::hasColumn('estoque_reservas', 'pedido_id')) {
                $reservas = DB::table('estoque_reservas')
                    ->where('forca_vendas_order_id', $order->id)
                    ->get(['id', 'orcamento_item_id']);

                foreach ($reservas as $reserva) {
                    $pedidoItemId = null;
                    if ($reserva->orcamento_item_id && isset($itemMap[(int) $reserva->orcamento_item_id])) {
                        $pedidoItemId = $itemMap[(int) $reserva->orcamento_item_id];
                    }

                    DB::table('estoque_reservas')
                        ->where('id', $reserva->id)
                        ->update([
                            'pedido_id' => $pedidoId,
                            'pedido_item_id' => $pedidoItemId,
                            'orcamento_id' => null,
                            'orcamento_item_id' => null,
                        ]);
                }
            }

            DB::table('orcamento_itens')->where('orcamento_id', $orc->id)->delete();
            DB::table('orcamentos')->where('id', $orc->id)->delete();
        }
    }

    private function pedidoIdParaOrcamento(object $orc): int
    {
        $existente = DB::table('pedidos')
            ->where('numero', $orc->numero)
            ->where('cliente_id', $orc->cliente_id)
            ->whereDate('data', $orc->data)
            ->orderBy('id')
            ->first();

        if ($existente) {
            return (int) $existente->id;
        }

        return (int) DB::table('pedidos')->insertGetId([
            'numero' => $this->numeroPedidoLivre((string) ($orc->numero ?? '')),
            'data' => $orc->data,
            'hora' => $orc->hora ?? null,
            'cliente_id' => $orc->cliente_id,
            'cliente_nome' => $orc->cliente_nome ?? null,
            'cliente_cpf_cnpj' => $orc->cliente_cpf_cnpj ?? null,
            'cliente_endereco' => $orc->cliente_endereco ?? null,
            'cliente_numero' => $orc->cliente_numero ?? null,
            'cliente_bairro' => $orc->cliente_bairro ?? null,
            'cliente_cep' => $orc->cliente_cep ?? null,
            'cliente_cidade' => $orc->cliente_cidade ?? null,
            'cliente_uf' => $orc->cliente_uf ?? null,
            'cliente_fone' => $orc->cliente_fone ?? null,
            'cliente_whatsapp' => $orc->cliente_whatsapp ?? null,
            'vendedor_id' => $orc->vendedor_id ?? null,
            'subtotal' => $orc->subtotal ?? 0,
            'percentual_desconto' => $orc->percentual_desconto ?? 0,
            'desconto_valor' => $orc->desconto_valor ?? 0,
            'forma_pagamento' => $orc->forma_pagamento ?? null,
            'validade_dias' => $orc->validade_dias ?? 0,
            'observacoes' => $orc->observacoes ?? null,
            'total' => $orc->total ?? 0,
            'status' => $orc->status ?? 'aberto',
            'plataforma' => $orc->plataforma ?? 'fv',
            'created_at' => $orc->created_at ?? now(),
            'updated_at' => $orc->updated_at ?? now(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function itensPedidoParaOrcamento(int $pedidoId, object $orc): array
    {
        $itemMap = [];
        $itens = DB::table('orcamento_itens')
            ->where('orcamento_id', $orc->id)
            ->orderBy('item')
            ->orderBy('id')
            ->get();

        $itensExistentes = DB::table('pedido_itens')
            ->where('pedido_id', $pedidoId)
            ->orderBy('item')
            ->orderBy('id')
            ->get();

        if ($itensExistentes->isNotEmpty()) {
            foreach ($itens as $index => $item) {
                $existente = $itensExistentes->get($index);
                if ($existente) {
                    $itemMap[(int) $item->id] = (int) $existente->id;
                }
            }

            return $itemMap;
        }

        foreach ($itens as $item) {
            $novoItemId = DB::table('pedido_itens')->insertGetId([
                'pedido_id' => $pedidoId,
                'item' => $item->item ?? 1,
                'product_id' => $item->product_id,
                'product_grade_id' => $item->product_grade_id ?? null,
                'quantidade' => $item->quantidade,
                'preco_unitario' => $item->preco_unitario,
                'desconto' => $item->desconto ?? 0,
                'total' => $item->total,
                'descricao' => $item->descricao ?? null,
                'created_at' => $item->created_at ?? now(),
                'updated_at' => $item->updated_at ?? now(),
            ]);
            $itemMap[(int) $item->id] = $novoItemId;
        }

        return $itemMap;
    }

    private function numeroPedidoLivre(string $base): string
    {
        $base = trim($base);
        if ($base === '') {
            $base = '000001';
        }

        $candidato = $base;
        $tentativa = 0;

        while (DB::table('pedidos')->where('numero', $candidato)->exists()) {
            $tentativa++;

            if (preg_match('/^\d+$/', $base) === 1) {
                $candidato = str_pad(
                    (string) ((int) $base + $tentativa),
                    max(strlen($base), 6),
                    '0',
                    STR_PAD_LEFT
                );
            } else {
                $candidato = $base.'-'.$tentativa;
            }

            if ($tentativa > 10000) {
                $candidato = $base.'-'.substr(uniqid('', false), -6);

                break;
            }
        }

        return $candidato;
    }

    /**
     * Copia DAV antigo como está. Não lê nem grava a tabela de produtos.
     */
    private function semChecagemDeFk(callable $fn): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                $fn();
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            try {
                $fn();
            } finally {
                DB::statement('PRAGMA foreign_keys = ON');
            }

            return;
        }

        $fn();
    }
};
