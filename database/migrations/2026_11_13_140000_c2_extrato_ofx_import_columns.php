<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C2 — Colunas de resumo/retomada da importação OFX e flag de ambiguidade.
 * Escopo: somente extrato_importacoes / extrato_lancamentos (C1).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('extrato_importacoes')) {
            Schema::table('extrato_importacoes', function (Blueprint $table): void {
                if (! Schema::hasColumn('extrato_importacoes', 'total_importados')) {
                    $table->unsignedInteger('total_importados')->default(0)->after('total_linhas');
                }
                if (! Schema::hasColumn('extrato_importacoes', 'total_duplicados')) {
                    $table->unsignedInteger('total_duplicados')->default(0)->after('total_importados');
                }
                if (! Schema::hasColumn('extrato_importacoes', 'total_erros')) {
                    $table->unsignedInteger('total_erros')->default(0)->after('total_duplicados');
                }
                if (! Schema::hasColumn('extrato_importacoes', 'total_ambiguos')) {
                    $table->unsignedInteger('total_ambiguos')->default(0)->after('total_erros');
                }
                if (! Schema::hasColumn('extrato_importacoes', 'banco_id_arquivo')) {
                    $table->string('banco_id_arquivo', 20)->nullable()->after('formato');
                }
                if (! Schema::hasColumn('extrato_importacoes', 'agencia_arquivo')) {
                    $table->string('agencia_arquivo', 20)->nullable()->after('banco_id_arquivo');
                }
                if (! Schema::hasColumn('extrato_importacoes', 'conta_arquivo')) {
                    $table->string('conta_arquivo', 40)->nullable()->after('agencia_arquivo');
                }
                if (! Schema::hasColumn('extrato_importacoes', 'alerta_conta_incompativel')) {
                    $table->boolean('alerta_conta_incompativel')->default(false)->after('conta_arquivo');
                }
                if (! Schema::hasColumn('extrato_importacoes', 'ultimo_lote_ok')) {
                    $table->unsignedInteger('ultimo_lote_ok')->default(0)->after('total_ambiguos');
                }
            });
        }

        if (Schema::hasTable('extrato_lancamentos')) {
            Schema::table('extrato_lancamentos', function (Blueprint $table): void {
                if (! Schema::hasColumn('extrato_lancamentos', 'ref_num')) {
                    $table->string('ref_num', 80)->nullable()->after('check_num');
                }
                if (! Schema::hasColumn('extrato_lancamentos', 'ambiguo')) {
                    $table->boolean('ambiguo')->default(false)->after('situacao');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('extrato_importacoes')) {
            Schema::table('extrato_importacoes', function (Blueprint $table): void {
                foreach ([
                    'total_importados',
                    'total_duplicados',
                    'total_erros',
                    'total_ambiguos',
                    'banco_id_arquivo',
                    'agencia_arquivo',
                    'conta_arquivo',
                    'alerta_conta_incompativel',
                    'ultimo_lote_ok',
                ] as $col) {
                    if (Schema::hasColumn('extrato_importacoes', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('extrato_lancamentos')) {
            Schema::table('extrato_lancamentos', function (Blueprint $table): void {
                foreach (['ref_num', 'ambiguo'] as $col) {
                    if (Schema::hasColumn('extrato_lancamentos', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
