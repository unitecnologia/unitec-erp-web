<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordem_servico_imagens', function (Blueprint $table): void {
            if (! Schema::hasColumn('ordem_servico_imagens', 'tipo')) {
                $table->string('tipo', 20)->default('foto')->after('ordem_servico_id');
            }
            if (! Schema::hasColumn('ordem_servico_imagens', 'empresa_id')) {
                $table->foreignId('empresa_id')->nullable()->after('tipo')
                    ->constrained('empresas')->nullOnDelete();
            }
            if (! Schema::hasColumn('ordem_servico_imagens', 'usuario_id')) {
                $table->foreignId('usuario_id')->nullable()->after('empresa_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('ordem_servico_imagens', 'atendente_id')) {
                $table->unsignedBigInteger('atendente_id')->nullable()->after('usuario_id');
            }
            if (! Schema::hasColumn('ordem_servico_imagens', 'mime')) {
                $table->string('mime', 120)->nullable()->after('caminho');
            }
            if (! Schema::hasColumn('ordem_servico_imagens', 'tamanho')) {
                $table->unsignedInteger('tamanho')->nullable()->after('mime');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordem_servico_imagens', function (Blueprint $table): void {
            if (Schema::hasColumn('ordem_servico_imagens', 'empresa_id')) {
                $table->dropConstrainedForeignId('empresa_id');
            }
            if (Schema::hasColumn('ordem_servico_imagens', 'usuario_id')) {
                $table->dropConstrainedForeignId('usuario_id');
            }
            foreach (['tipo', 'atendente_id', 'mime', 'tamanho'] as $col) {
                if (Schema::hasColumn('ordem_servico_imagens', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
