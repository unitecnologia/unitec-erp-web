<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forca_vendas_devices', function (Blueprint $table): void {
            if (! Schema::hasColumn('forca_vendas_devices', 'status')) {
                $table->string('status', 20)->default('pendente')->after('app_version');
            }
            if (! Schema::hasColumn('forca_vendas_devices', 'pairing_code')) {
                $table->string('pairing_code', 12)->nullable()->after('status');
            }
            if (! Schema::hasColumn('forca_vendas_devices', 'registered_at')) {
                $table->timestamp('registered_at')->nullable()->after('last_pull_at');
            }
            if (! Schema::hasColumn('forca_vendas_devices', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('registered_at');
            }
            if (! Schema::hasColumn('forca_vendas_devices', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            }
        });

        // Aparelhos que ja existiam (pareados pelo fluxo antigo de QR/segredo)
        // continuam validos: marca como aprovado para nao quebrar o acesso atual.
        DB::table('forca_vendas_devices')
            ->whereNull('revoked_at')
            ->whereNotNull('current_token_id')
            ->where(function ($q): void {
                $q->whereNull('status')->orWhere('status', '')->orWhere('status', 'pendente');
            })
            ->update(['status' => 'aprovado', 'approved_at' => now()]);

        // Passamos a controlar o acesso pela autorizacao do aparelho, entao o
        // segredo compartilhado deixa de ser obrigatorio.
        if (Schema::hasTable('forca_vendas_settings')) {
            DB::table('forca_vendas_settings')->update(['pairing_required' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('forca_vendas_devices', function (Blueprint $table): void {
            foreach (['status', 'pairing_code', 'registered_at', 'approved_at', 'approved_by'] as $col) {
                if (Schema::hasColumn('forca_vendas_devices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
