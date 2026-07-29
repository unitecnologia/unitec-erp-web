<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDVs (terminais) liberados por colaborador — 1 ou vários.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('terminal_vendedor')) {
            return;
        }

        Schema::create('terminal_vendedor', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('vendedores')->cascadeOnDelete();
            $table->foreignId('terminal_id')->constrained('terminais')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['vendedor_id', 'terminal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_vendedor');
    }
};
