<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'estoque_inicial')) {
                $table->dropColumn('estoque_inicial');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'estoque_inicial')) {
                $table->decimal('estoque_inicial', 12, 3)->default(0)->after('estoque_minimo');
            }
        });
    }
};
