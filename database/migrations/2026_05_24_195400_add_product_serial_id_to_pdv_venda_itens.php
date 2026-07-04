<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_venda_itens', function (Blueprint $table) {
            $table->foreignId('product_serial_id')
                ->nullable()
                ->after('product_grade_id')
                ->constrained('product_serials')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdv_venda_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_serial_id');
        });
    }
};
