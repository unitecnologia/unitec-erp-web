<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_receber', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->date('emissao');
            $table->string('historico', 500);
            $table->string('documento', 40)->nullable();
            $table->foreignId('cliente_id')->constrained('people')->cascadeOnDelete();
            $table->date('vencimento');
            $table->decimal('valor', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('juros', 15, 2)->default(0);
            $table->decimal('valor_recebido', 15, 2)->default(0);
            $table->date('recebido_em')->nullable();
            $table->decimal('saldo', 15, 2)->default(0);
            $table->string('forma', 20)->default('carteira');
            $table->timestamps();

            $table->index('emissao');
            $table->index('vencimento');
            $table->index('forma');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_receber');
    }
};
