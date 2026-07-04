<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_pagar', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->date('emissao');
            $table->string('produto', 500)->nullable();
            $table->string('documento', 40)->nullable();
            $table->foreignId('fornecedor_id')->constrained('people')->cascadeOnDelete();
            $table->date('vencimento');
            $table->decimal('valor', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('juros', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->date('pago_em')->nullable();
            $table->decimal('saldo', 15, 2)->default(0);
            $table->timestamps();

            $table->index('emissao');
            $table->index('vencimento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_pagar');
    }
};
