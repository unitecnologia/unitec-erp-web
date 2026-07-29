<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('codigo')->unique();
            $table->date('emissao');
            $table->decimal('valor', 15, 2);
            $table->string('extenso', 500)->nullable();
            $table->string('recebi_de', 200);
            $table->text('referente_a')->nullable();
            $table->timestamps();

            $table->index('emissao');
            $table->index('recebi_de');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};
