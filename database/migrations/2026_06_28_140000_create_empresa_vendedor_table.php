<?php

use App\Models\Vendedor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo N:N entre colaboradores (vendedores) e empresas.
 * Um colaborador pode pertencer a mais de uma empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresa_vendedor')) {
            Schema::create('empresa_vendedor', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('vendedor_id')->constrained('vendedores')->cascadeOnDelete();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['vendedor_id', 'empresa_id']);
            });
        }

        // Backfill: traz o empresa_id atual (empresa principal) para o pivot.
        if (Schema::hasColumn('vendedores', 'empresa_id')) {
            Vendedor::query()
                ->whereNotNull('empresa_id')
                ->get(['id', 'empresa_id'])
                ->each(function (Vendedor $vendedor): void {
                    DB::table('empresa_vendedor')->updateOrInsert(
                        ['vendedor_id' => $vendedor->id, 'empresa_id' => $vendedor->empresa_id],
                        ['updated_at' => now(), 'created_at' => now()],
                    );
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_vendedor');
    }
};
