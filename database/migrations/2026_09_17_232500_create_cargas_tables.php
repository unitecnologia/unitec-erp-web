<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cargas')) {
            Schema::create('cargas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
                $table->string('numero', 20);
                $table->date('data');
                $table->foreignId('motorista_id')->nullable()->constrained('transportadoras')->nullOnDelete();
                $table->foreignId('veiculo_id')->nullable()->constrained('veiculos')->nullOnDelete();
                $table->string('status', 20)->default('aberta');
                $table->text('observacao')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'numero'], 'cargas_empresa_numero_unique');
                $table->index(['empresa_id', 'status', 'data'], 'cargas_empresa_status_data_index');
            });
        }

        if (! Schema::hasTable('carga_pedidos')) {
            Schema::create('carga_pedidos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('carga_id')->constrained('cargas')->cascadeOnDelete();
                $table->foreignId('pedido_id')->constrained('vendas')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['carga_id', 'pedido_id'], 'carga_pedidos_carga_pedido_unique');
                $table->index('pedido_id', 'carga_pedidos_pedido_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('carga_pedidos');
        Schema::dropIfExists('cargas');
    }
};
