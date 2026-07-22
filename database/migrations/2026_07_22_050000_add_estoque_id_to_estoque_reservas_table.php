<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estoque_reservas')) {
            return;
        }

        if (! Schema::hasColumn('estoque_reservas', 'estoque_id')) {
            Schema::table('estoque_reservas', function (Blueprint $table): void {
                $table->unsignedBigInteger('estoque_id')->nullable()->after('product_id');
                $table->index(['estoque_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('estoque_reservas') || ! Schema::hasColumn('estoque_reservas', 'estoque_id')) {
            return;
        }

        Schema::table('estoque_reservas', function (Blueprint $table): void {
            $table->dropIndex(['estoque_id', 'status']);
            $table->dropColumn('estoque_id');
        });
    }
};
