<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pdv_vendas', 'nfce_operacao')) {
            Schema::table('pdv_vendas', function (Blueprint $table): void {
                $table->string('nfce_operacao', 32)->nullable()->after('fiscal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pdv_vendas', 'nfce_operacao')) {
            Schema::table('pdv_vendas', function (Blueprint $table): void {
                $table->dropColumn('nfce_operacao');
            });
        }
    }
};
