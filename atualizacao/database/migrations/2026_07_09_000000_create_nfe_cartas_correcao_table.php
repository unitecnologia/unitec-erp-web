<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_cartas_correcao')) {
            return;
        }

        Schema::create('nfe_cartas_correcao', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nfe_id')->constrained('nfes')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequencia');
            $table->text('correcao');
            $table->string('protocolo', 20)->nullable();
            $table->longText('xml')->nullable();
            $table->timestamps();

            $table->unique(['nfe_id', 'sequencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_cartas_correcao');
    }
};
