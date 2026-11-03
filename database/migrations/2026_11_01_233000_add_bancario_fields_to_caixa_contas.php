<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplia caixa_contas para cadastro bancário por empresa.
 * Campos bancários e empresa_id são nullable: contas existentes (CAIXA GERAL/PDV) permanecem globais.
 * Não altera nem transforma registros existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caixa_contas')) {
            return;
        }

        Schema::table('caixa_contas', function (Blueprint $table): void {
            if (! Schema::hasColumn('caixa_contas', 'empresa_id')) {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('empresas')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('caixa_contas', 'banco_codigo')) {
                $table->string('banco_codigo', 10)->nullable()->after('sistema');
            }
            if (! Schema::hasColumn('caixa_contas', 'banco_nome')) {
                $table->string('banco_nome', 80)->nullable()->after('banco_codigo');
            }
            if (! Schema::hasColumn('caixa_contas', 'agencia')) {
                $table->string('agencia', 20)->nullable()->after('banco_nome');
            }
            if (! Schema::hasColumn('caixa_contas', 'agencia_dv')) {
                $table->string('agencia_dv', 4)->nullable()->after('agencia');
            }
            if (! Schema::hasColumn('caixa_contas', 'conta_numero')) {
                $table->string('conta_numero', 40)->nullable()->after('agencia_dv');
            }
            if (! Schema::hasColumn('caixa_contas', 'conta_dv')) {
                $table->string('conta_dv', 4)->nullable()->after('conta_numero');
            }
            if (! Schema::hasColumn('caixa_contas', 'conta_tipo_bancaria')) {
                $table->string('conta_tipo_bancaria', 20)->nullable()->after('conta_dv');
            }
            if (! Schema::hasColumn('caixa_contas', 'titular_nome')) {
                $table->string('titular_nome', 120)->nullable()->after('conta_tipo_bancaria');
            }
            if (! Schema::hasColumn('caixa_contas', 'titular_documento')) {
                $table->string('titular_documento', 20)->nullable()->after('titular_nome');
            }
        });

        if (Schema::hasColumn('caixa_contas', 'empresa_id')
            && Schema::hasColumn('caixa_contas', 'tipo')) {
            try {
                Schema::table('caixa_contas', function (Blueprint $table): void {
                    $table->index(['empresa_id', 'tipo'], 'caixa_contas_empresa_tipo_index');
                });
            } catch (\Throwable) {
                // Índice já existente em reexecução parcial.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('caixa_contas')) {
            return;
        }

        Schema::table('caixa_contas', function (Blueprint $table): void {
            if (Schema::hasColumn('caixa_contas', 'empresa_id')) {
                try {
                    $table->dropIndex('caixa_contas_empresa_tipo_index');
                } catch (\Throwable) {
                    // índice pode não existir em ambientes parciais
                }
            }

            $cols = [
                'titular_documento',
                'titular_nome',
                'conta_tipo_bancaria',
                'conta_dv',
                'conta_numero',
                'agencia_dv',
                'agencia',
                'banco_nome',
                'banco_codigo',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('caixa_contas', $col)) {
                    $table->dropColumn($col);
                }
            }

            if (Schema::hasColumn('caixa_contas', 'empresa_id')) {
                $table->dropConstrainedForeignId('empresa_id');
            }
        });
    }
};
