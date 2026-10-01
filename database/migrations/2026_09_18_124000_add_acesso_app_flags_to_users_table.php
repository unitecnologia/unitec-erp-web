<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'acesso_app_forca_vendas')) {
                $table->boolean('acesso_app_forca_vendas')->default(false)->after('senha_app_forca_vendas');
            }
            if (! Schema::hasColumn('users', 'acesso_app_vendas_internas')) {
                $table->boolean('acesso_app_vendas_internas')->default(false)->after('acesso_app_forca_vendas');
            }
            if (! Schema::hasColumn('users', 'acesso_app_unitec_os')) {
                $table->boolean('acesso_app_unitec_os')->default(false)->after('acesso_app_vendas_internas');
            }
            if (! Schema::hasColumn('users', 'acesso_app_entregas')) {
                $table->boolean('acesso_app_entregas')->default(false)->after('acesso_app_unitec_os');
            }
        });

        DB::table('users')
            ->whereNotNull('senha_app_forca_vendas')
            ->where('senha_app_forca_vendas', '!=', '')
            ->update([
                'acesso_app_forca_vendas' => true,
                'acesso_app_vendas_internas' => true,
                'acesso_app_unitec_os' => true,
                'acesso_app_entregas' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $cols = [
                'acesso_app_forca_vendas',
                'acesso_app_vendas_internas',
                'acesso_app_unitec_os',
                'acesso_app_entregas',
            ];
            $existing = array_values(array_filter($cols, fn (string $c): bool => Schema::hasColumn('users', $c)));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
