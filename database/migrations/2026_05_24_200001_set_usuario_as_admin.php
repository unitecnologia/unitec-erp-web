<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('name', 'USUARIO')
            ->update([
                'is_admin' => true,
                'ativo' => true,
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('name', 'USUARIO')
            ->update([
                'is_admin' => false,
            ]);
    }
};
