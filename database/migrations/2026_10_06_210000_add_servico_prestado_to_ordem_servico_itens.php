<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ordem_servico_itens', 'servico_prestado')) {
            return;
        }

        Schema::table('ordem_servico_itens', function (Blueprint $table): void {
            $table->text('servico_prestado')->nullable()->after('discriminacao');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ordem_servico_itens', 'servico_prestado')) {
            return;
        }

        Schema::table('ordem_servico_itens', function (Blueprint $table): void {
            $table->dropColumn('servico_prestado');
        });
    }
};
