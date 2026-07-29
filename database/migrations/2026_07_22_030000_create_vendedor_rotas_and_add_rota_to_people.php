<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendedor_rotas')) {
            Schema::create('vendedor_rotas', function (Blueprint $table): void {
                $table->id();
                $table->string('nome', 120);
                $table->foreignId('vendedor_id')
                    ->nullable()
                    ->constrained('vendedores')
                    ->nullOnDelete();
                $table->text('observacao')->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->index(['vendedor_id', 'ativo']);
                $table->index('nome');
            });
        }

        Schema::table('people', function (Blueprint $table): void {
            if (! Schema::hasColumn('people', 'vendedor_rota_id')) {
                $table->foreignId('vendedor_rota_id')
                    ->nullable()
                    ->after('vendedor_loja_id')
                    ->constrained('vendedor_rotas')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('people', 'visita_ordem')) {
                $table->unsignedInteger('visita_ordem')
                    ->nullable()
                    ->after('vendedor_rota_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            if (Schema::hasColumn('people', 'visita_ordem')) {
                $table->dropColumn('visita_ordem');
            }

            if (Schema::hasColumn('people', 'vendedor_rota_id')) {
                $table->dropConstrainedForeignId('vendedor_rota_id');
            }
        });

        Schema::dropIfExists('vendedor_rotas');
    }
};
