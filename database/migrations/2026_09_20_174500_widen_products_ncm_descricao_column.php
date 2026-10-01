<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descrições longas de NCM (ex.: 22029900) estouravam varchar(191) em products.ncm_descricao.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'ncm_descricao')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->text('ncm_descricao')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'ncm_descricao')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('ncm_descricao')->nullable()->change();
        });
    }
};
