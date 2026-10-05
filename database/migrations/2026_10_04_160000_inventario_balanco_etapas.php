<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventario_etapas')) {
            Schema::create('inventario_etapas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('inventario_contagem_id')->constrained('inventario_contagens')->cascadeOnDelete();
                $table->string('nome', 80);
                $table->string('status', 20)->default('aberta');
                $table->foreignId('user_id')->constrained('users');
                $table->timestamp('fechada_em')->nullable();
                $table->foreignId('fechada_por')->nullable()->constrained('users');
                $table->timestamps();

                $table->index(['inventario_contagem_id', 'status'], 'inventario_etapas_status_idx');
            });
        }

        if ($this->temIndiceUnicoProduto()) {
            Schema::table('inventario_contagem_itens', function (Blueprint $table): void {
                $table->dropUnique('inventario_item_produto_uq');
            });
        }

        if (! Schema::hasColumn('inventario_contagem_itens', 'inventario_etapa_id')) {
            Schema::table('inventario_contagem_itens', function (Blueprint $table): void {
                $table->foreignId('inventario_etapa_id')->nullable()->constrained('inventario_etapas')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('inventario_contagem_itens', 'user_id')) {
            Schema::table('inventario_contagem_itens', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('inventario_contagem_itens', 'contado_em')) {
            Schema::table('inventario_contagem_itens', function (Blueprint $table): void {
                $table->timestamp('contado_em')->nullable();
            });
        }

        if (! Schema::hasColumn('inventario_contagem_itens', 'saldo_antes')) {
            Schema::table('inventario_contagem_itens', function (Blueprint $table): void {
                $table->decimal('saldo_antes', 12, 3)->nullable();
            });
        }

        if (! Schema::hasColumn('inventario_contagem_itens', 'saldo_depois')) {
            Schema::table('inventario_contagem_itens', function (Blueprint $table): void {
                $table->decimal('saldo_depois', 12, 3)->nullable();
            });
        }

        if (! Schema::hasColumn('inventario_contagem_itens', 'idempotencia')) {
            Schema::table('inventario_contagem_itens', function (Blueprint $table): void {
                $table->string('idempotencia', 40)->nullable();
                $table->unique('idempotencia', 'inventario_item_idempotencia_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::table('inventario_contagem_itens', function (Blueprint $table): void {
            if (Schema::hasColumn('inventario_contagem_itens', 'idempotencia')) {
                $table->dropUnique('inventario_item_idempotencia_uq');
                $table->dropColumn('idempotencia');
            }

            foreach (['saldo_depois', 'saldo_antes', 'contado_em', 'user_id', 'inventario_etapa_id'] as $coluna) {
                if (Schema::hasColumn('inventario_contagem_itens', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });

        Schema::dropIfExists('inventario_etapas');
    }

    private function temIndiceUnicoProduto(): bool
    {
        foreach (Schema::getIndexes('inventario_contagem_itens') as $indice) {
            if (str_contains((string) ($indice['name'] ?? ''), 'inventario_item_produto_uq')) {
                return true;
            }
        }

        return false;
    }
};
