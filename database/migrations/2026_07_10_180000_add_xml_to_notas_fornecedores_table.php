<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_fornecedores', function (Blueprint $table) {
            $table->longText('xml')->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('notas_fornecedores', function (Blueprint $table) {
            $table->dropColumn('xml');
        });
    }
};
