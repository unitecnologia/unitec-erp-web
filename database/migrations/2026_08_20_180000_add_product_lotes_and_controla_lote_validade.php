<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'controla_lote_validade')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->boolean('controla_lote_validade')->default(false)->after('lote');
            });
        }

        if (! Schema::hasTable('product_lotes')) {
            Schema::create('product_lotes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('lote', 60);
                $table->date('data_validade');
                $table->decimal('quantidade_atual', 15, 3)->default(0);
                $table->timestamps();

                $table->index(['product_id', 'data_validade']);
                $table->index(['product_id', 'lote', 'data_validade']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_lotes');

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'controla_lote_validade')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('controla_lote_validade');
            });
        }
    }
};
