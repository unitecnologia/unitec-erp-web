<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUNAS = ['numero_serie', 'descricao', 'descricao2', 'modelo', 'ano', 'placa', 'km', 'cor_veiculo', 'chassi_veiculo'];

    public function up(): void
    {
        if (! Schema::hasTable('nfses')) {
            return;
        }

        Schema::table('nfses', function (Blueprint $table): void {
            if (Schema::hasTable('os_veiculos') && ! Schema::hasColumn('nfses', 'os_veiculo_id')) {
                $table->foreignId('os_veiculo_id')
                    ->nullable()
                    ->constrained('os_veiculos')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('nfses', 'numero_serie')) {
                $table->string('numero_serie', 60)->nullable();
            }
            if (! Schema::hasColumn('nfses', 'descricao')) {
                $table->string('descricao', 150)->nullable();
            }
            if (! Schema::hasColumn('nfses', 'descricao2')) {
                $table->string('descricao2', 150)->nullable();
            }
            if (! Schema::hasColumn('nfses', 'modelo')) {
                $table->string('modelo', 80)->nullable();
            }
            if (! Schema::hasColumn('nfses', 'ano')) {
                $table->string('ano', 10)->nullable();
            }
            if (! Schema::hasColumn('nfses', 'placa')) {
                $table->string('placa', 15)->nullable();
            }
            if (! Schema::hasColumn('nfses', 'km')) {
                $table->unsignedInteger('km')->nullable();
            }
            if (! Schema::hasColumn('nfses', 'cor_veiculo')) {
                $table->string('cor_veiculo', 40)->nullable();
            }
            if (! Schema::hasColumn('nfses', 'chassi_veiculo')) {
                $table->string('chassi_veiculo', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('nfses')) {
            return;
        }

        Schema::table('nfses', function (Blueprint $table): void {
            if (Schema::hasColumn('nfses', 'os_veiculo_id')) {
                $table->dropConstrainedForeignId('os_veiculo_id');
            }

            foreach (self::COLUNAS as $coluna) {
                if (Schema::hasColumn('nfses', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
