<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'vendedor_id')) {
                $table->foreignId('vendedor_id')
                    ->nullable()
                    ->after('erp_profile_id')
                    ->constrained('vendedores')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'senha_app_forca_vendas')) {
                $table->string('senha_app_forca_vendas', 60)
                    ->nullable()
                    ->after('senha');
            }

            if (! Schema::hasColumn('users', 'is_supervisor')) {
                $table->boolean('is_supervisor')
                    ->default(false)
                    ->after('is_admin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'vendedor_id')) {
                $table->dropConstrainedForeignId('vendedor_id');
            }

            if (Schema::hasColumn('users', 'senha_app_forca_vendas')) {
                $table->dropColumn('senha_app_forca_vendas');
            }

            if (Schema::hasColumn('users', 'is_supervisor')) {
                $table->dropColumn('is_supervisor');
            }
        });
    }
};
