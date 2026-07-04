<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('numero', 20);
            $table->string('serie', 10)->default('1');
            $table->date('data_emissao');
            $table->date('data_saida')->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();
            $table->string('chave', 44)->nullable();
            $table->string('protocolo', 30)->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20)->default('aberta');
            $table->timestamps();

            $table->index(['status', 'data_emissao']);
            $table->index('chave');
            $table->index('numero');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfes');
    }
};
