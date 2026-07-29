<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_fornecedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->date('data_entrada');
            $table->date('data_emissao');
            $table->string('numero', 20);
            $table->string('chave', 44)->nullable();
            $table->string('cnpj', 14)->nullable();
            $table->string('nome');
            $table->string('nsu', 30)->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20)->default('pendente');
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'data_entrada']);
            $table->index('chave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_fornecedores');
    }
};
