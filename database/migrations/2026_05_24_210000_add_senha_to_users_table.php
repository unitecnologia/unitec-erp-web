<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'senha')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('senha', 60)->nullable()->after('password');
            });
        }

        DB::table('users')
            ->where('name', 'USUARIO')
            ->whereNull('senha')
            ->update(['senha' => '01']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'senha')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('senha');
            });
        }
    }
};
