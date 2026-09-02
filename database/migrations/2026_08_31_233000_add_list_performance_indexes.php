<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendas')) {
            Schema::table('vendas', function (Blueprint $table): void {
                if (! $this->indexExists('vendas', 'vendas_empresa_data_idx')) {
                    $table->index(['empresa_id', 'data'], 'vendas_empresa_data_idx');
                }
                if (! $this->indexExists('vendas', 'vendas_empresa_status_data_idx')) {
                    $table->index(['empresa_id', 'status', 'data'], 'vendas_empresa_status_data_idx');
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (! $this->indexExists('products', 'products_ativo_grupo_idx')) {
                    $table->index(['ativo', 'grupo'], 'products_ativo_grupo_idx');
                }
                if (Schema::hasColumn('products', 'codigo_barras_caixa')
                    && ! $this->indexExists('products', 'products_codigo_barras_caixa_idx')) {
                    $table->index('codigo_barras_caixa', 'products_codigo_barras_caixa_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendas')) {
            Schema::table('vendas', function (Blueprint $table): void {
                if ($this->indexExists('vendas', 'vendas_empresa_data_idx')) {
                    $table->dropIndex('vendas_empresa_data_idx');
                }
                if ($this->indexExists('vendas', 'vendas_empresa_status_data_idx')) {
                    $table->dropIndex('vendas_empresa_status_data_idx');
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if ($this->indexExists('products', 'products_ativo_grupo_idx')) {
                    $table->dropIndex('products_ativo_grupo_idx');
                }
                if (Schema::hasColumn('products', 'codigo_barras_caixa')
                    && $this->indexExists('products', 'products_codigo_barras_caixa_idx')) {
                    $table->dropIndex('products_codigo_barras_caixa_idx');
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $prefix = $connection->getTablePrefix();
        $tableName = $prefix.$table;

        $row = $connection->selectOne(
            'select 1 as ok from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $tableName, $index]
        );

        return $row !== null;
    }
};