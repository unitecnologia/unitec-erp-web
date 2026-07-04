<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('estoque_fiscal', 12, 3)->default(0)->after('estoque');
            $table->foreignId('ult_fornecedor_id')
                ->nullable()
                ->after('grupo')
                ->constrained('people')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ult_fornecedor_id');
            $table->dropColumn('estoque_fiscal');
        });
    }
};
