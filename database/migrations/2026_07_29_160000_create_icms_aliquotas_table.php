<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icms_aliquotas', function (Blueprint $table) {
            $table->id();
            $table->char('uf_origem', 2);
            $table->char('uf_destino', 2);
            $table->decimal('aliquota', 5, 2);
            $table->timestamps();

            $table->unique(['uf_origem', 'uf_destino']);
            $table->index('uf_destino');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icms_aliquotas');
    }
};
