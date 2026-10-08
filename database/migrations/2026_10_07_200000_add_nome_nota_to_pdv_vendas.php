<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_vendas') || Schema::hasColumn('pdv_vendas', 'nome_nota')) {
            return;
        }

        Schema::table('pdv_vendas', function (Blueprint $table): void {
            $table->string('nome_nota', 60)->nullable()->after('cpf_nota');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('pdv_vendas') && Schema::hasColumn('pdv_vendas', 'nome_nota')) {
            Schema::table('pdv_vendas', function (Blueprint $table): void {
                $table->dropColumn('nome_nota');
            });
        }
    }
};
