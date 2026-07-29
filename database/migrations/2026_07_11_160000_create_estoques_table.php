<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoques', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('codigo', 20);
            $table->string('nome', 120);
            $table->foreignId('vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
            $table->index(['empresa_id', 'ativo']);
        });

        Schema::table('vendedores', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendedores', 'estoque_id')) {
                $table->foreignId('estoque_id')
                    ->nullable()
                    ->after('estoque')
                    ->constrained('estoques')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendedores', function (Blueprint $table): void {
            if (Schema::hasColumn('vendedores', 'estoque_id')) {
                $table->dropConstrainedForeignId('estoque_id');
            }
        });

        Schema::dropIfExists('estoques');
    }
};
