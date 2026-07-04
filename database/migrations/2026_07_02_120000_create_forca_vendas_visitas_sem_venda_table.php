<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('forca_vendas_visitas_sem_venda')) {
            Schema::create('forca_vendas_visitas_sem_venda', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('device_uuid', 100)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->foreignId('cliente_id')->constrained('people')->cascadeOnDelete();
                $table->foreignId('vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
                $table->text('motivo');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('status', 20)->default('importado');
                $table->text('erro')->nullable();
                $table->timestamp('client_created_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->index(['vendedor_id', 'client_created_at'], 'fv_visitas_vend_data_idx');
                $table->index('empresa_id', 'fv_visitas_empresa_idx');
            });

            return;
        }

        Schema::table('forca_vendas_visitas_sem_venda', function (Blueprint $table): void {
            if (! $this->indexExists('forca_vendas_visitas_sem_venda', 'fv_visitas_vend_data_idx')) {
                $table->index(['vendedor_id', 'client_created_at'], 'fv_visitas_vend_data_idx');
            }

            if (! $this->indexExists('forca_vendas_visitas_sem_venda', 'fv_visitas_empresa_idx')) {
                $table->index('empresa_id', 'fv_visitas_empresa_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forca_vendas_visitas_sem_venda');
    }

    private function indexExists(string $table, string $index): bool
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $definition) {
            if (($definition['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
