<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orcamentos')) {
            return;
        }

        Schema::table('orcamentos', function (Blueprint $table): void {
            if (Schema::hasTable('os_veiculos') && ! Schema::hasColumn('orcamentos', 'os_veiculo_id')) {
                $table->foreignId('os_veiculo_id')
                    ->nullable()
                    ->after('observacoes')
                    ->constrained('os_veiculos')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('orcamentos', 'numero_serie')) {
                $table->string('numero_serie', 60)->nullable();
            }
            if (! Schema::hasColumn('orcamentos', 'descricao')) {
                $table->string('descricao', 150)->nullable();
            }
            if (! Schema::hasColumn('orcamentos', 'descricao2')) {
                $table->string('descricao2', 150)->nullable();
            }
            if (! Schema::hasColumn('orcamentos', 'modelo')) {
                $table->string('modelo', 80)->nullable();
            }
            if (! Schema::hasColumn('orcamentos', 'ano')) {
                $table->string('ano', 10)->nullable();
            }
            if (! Schema::hasColumn('orcamentos', 'placa')) {
                $table->string('placa', 15)->nullable();
            }
            if (! Schema::hasColumn('orcamentos', 'km')) {
                $table->unsignedInteger('km')->nullable();
            }
            if (! Schema::hasColumn('orcamentos', 'cor_veiculo')) {
                $table->string('cor_veiculo', 40)->nullable();
            }
            if (! Schema::hasColumn('orcamentos', 'chassi_veiculo')) {
                $table->string('chassi_veiculo', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orcamentos')) {
            return;
        }

        Schema::table('orcamentos', function (Blueprint $table): void {
            if (Schema::hasColumn('orcamentos', 'os_veiculo_id')) {
                $table->dropConstrainedForeignId('os_veiculo_id');
            }

            foreach (['numero_serie', 'descricao', 'descricao2', 'modelo', 'ano', 'placa', 'km', 'cor_veiculo', 'chassi_veiculo'] as $coluna) {
                if (Schema::hasColumn('orcamentos', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
