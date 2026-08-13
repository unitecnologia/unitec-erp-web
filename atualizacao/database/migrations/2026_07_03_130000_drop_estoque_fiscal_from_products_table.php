<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'estoque_fiscal')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('estoque_fiscal');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'estoque_fiscal')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->decimal('estoque_fiscal', 12, 3)->default(0)->after('estoque');
            });
        }
    }
};
