<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendedores', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendedores', 'entregador')) {
                $table->boolean('entregador')->default(false)->after('ajudante');
            }
        });

        // Usuário padrão do ERP (admin) já liberado como entregador.
        if (Schema::hasColumn('users', 'vendedor_id')) {
            DB::table('vendedores')
                ->whereIn('id', function ($q): void {
                    $q->select('vendedor_id')
                        ->from('users')
                        ->where('is_admin', true)
                        ->whereNotNull('vendedor_id');
                })
                ->update(['entregador' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('vendedores', function (Blueprint $table): void {
            if (Schema::hasColumn('vendedores', 'entregador')) {
                $table->dropColumn('entregador');
            }
        });
    }
};
