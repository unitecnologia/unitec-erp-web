<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ncms', function (Blueprint $table) {
            $table->text('descricao')->change();
        });
    }

    public function down(): void
    {
        Schema::table('ncms', function (Blueprint $table) {
            $table->string('descricao')->change();
        });
    }
};
