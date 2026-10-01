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
            if (! Schema::hasColumn('users', 'acesso_app_gestao')) {
                $table->boolean('acesso_app_gestao')->default(false)->after('acesso_app_entregas');
            }
        });

        // Não quebrar quem já entra no /gestor (admin ou com permissões típicas).
        DB::table('users')
            ->where('ativo', true)
            ->where(function ($q): void {
                $q->where('is_admin', true)
                    ->orWhereExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('user_permissions')
                            ->whereColumn('user_permissions.user_id', 'users.id')
                            ->whereIn('permission_key', [
                                'produtos.access',
                                'ajusta_preco.access',
                                'ajuste_estoque.access',
                            ]);
                    })
                    ->orWhereExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('erp_profile_permissions')
                            ->whereColumn('erp_profile_permissions.erp_profile_id', 'users.erp_profile_id')
                            ->whereIn('permission_key', [
                                'produtos.access',
                                'ajusta_preco.access',
                                'ajuste_estoque.access',
                            ]);
                    });
            })
            ->update(['acesso_app_gestao' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'acesso_app_gestao')) {
                $table->dropColumn('acesso_app_gestao');
            }
        });
    }
};
