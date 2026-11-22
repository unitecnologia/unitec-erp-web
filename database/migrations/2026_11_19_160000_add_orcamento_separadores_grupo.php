<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separadores de grupo no Orçamento: flag na empresa + linhas is_separador em orcamento_itens.
 * product_id passa a ser nullable só para separadores (sem produto fictício).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_orcamento_permitir_separadores_grupo')) {
                $table->boolean('param_orcamento_permitir_separadores_grupo')->default(false);
            }
        });

        if (! Schema::hasTable('orcamento_itens')) {
            return;
        }

        Schema::table('orcamento_itens', function (Blueprint $table): void {
            if (! Schema::hasColumn('orcamento_itens', 'is_separador')) {
                $table->boolean('is_separador')->default(false)->after('item');
            }
        });

        $this->tornarProductIdNullable();
    }

    public function down(): void
    {
        if (Schema::hasTable('orcamento_itens')) {
            if (Schema::hasColumn('orcamento_itens', 'is_separador')) {
                DB::table('orcamento_itens')->where('is_separador', true)->delete();

                Schema::table('orcamento_itens', function (Blueprint $table): void {
                    $table->dropColumn('is_separador');
                });
            }

            $this->restaurarProductIdNotNull();
        }

        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_orcamento_permitir_separadores_grupo')) {
                $table->dropColumn('param_orcamento_permitir_separadores_grupo');
            }
        });
    }

    private function tornarProductIdNullable(): void
    {
        if (! Schema::hasColumn('orcamento_itens', 'product_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('orcamento_itens', function (Blueprint $table): void {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            });

            return;
        }

        $this->dropProductForeign();

        Schema::table('orcamento_itens', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });

        Schema::table('orcamento_itens', function (Blueprint $table): void {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    private function restaurarProductIdNotNull(): void
    {
        if (! Schema::hasColumn('orcamento_itens', 'product_id')) {
            return;
        }

        DB::table('orcamento_itens')->whereNull('product_id')->delete();

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('orcamento_itens', function (Blueprint $table): void {
                $table->unsignedBigInteger('product_id')->nullable(false)->change();
            });

            return;
        }

        $this->dropProductForeign();

        Schema::table('orcamento_itens', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });

        Schema::table('orcamento_itens', function (Blueprint $table): void {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    private function dropProductForeign(): void
    {
        $sm = Schema::getConnection()->getSchemaBuilder();
        $foreignKeys = method_exists($sm, 'getForeignKeys')
            ? $sm->getForeignKeys('orcamento_itens')
            : [];

        foreach ($foreignKeys as $foreign) {
            $columns = $foreign['columns'] ?? [];

            if ($columns === ['product_id'] || in_array('product_id', $columns, true)) {
                Schema::table('orcamento_itens', function (Blueprint $table) use ($foreign): void {
                    $table->dropForeign($foreign['name'] ?? ['product_id']);
                });

                return;
            }
        }

        try {
            Schema::table('orcamento_itens', function (Blueprint $table): void {
                $table->dropForeign(['product_id']);
            });
        } catch (\Throwable) {
            // FK ausente ou nome diferente — segue com o change().
        }
    }
};
