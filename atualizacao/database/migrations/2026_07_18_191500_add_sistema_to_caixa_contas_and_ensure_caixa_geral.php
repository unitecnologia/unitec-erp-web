<?php

use App\Models\CaixaConta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caixa_contas')) {
            return;
        }

        Schema::table('caixa_contas', function (Blueprint $table) {
            if (! Schema::hasColumn('caixa_contas', 'sistema')) {
                $table->boolean('sistema')->default(false)->after('ativo');
                $table->index('sistema');
            }
        });

        // Contas já conhecidas como caixa geral do sistema.
        DB::table('caixa_contas')
            ->whereRaw('UPPER(nome) = ?', ['CAIXA GERAL'])
            ->update([
                'tipo' => 'SUBCAIXA',
                'sistema' => true,
                'situacao' => 'aberto',
                'ativo' => true,
            ]);

        $exists = DB::table('caixa_contas')
            ->whereRaw('UPPER(nome) = ?', ['CAIXA GERAL'])
            ->exists();

        if (! $exists) {
            $codigo = (int) (DB::table('caixa_contas')->max('codigo') ?? 0);

            if ($codigo < 1 || DB::table('caixa_contas')->where('codigo', 1)->doesntExist()) {
                $codigo = 1;
            } else {
                $codigo++;
            }

            DB::table('caixa_contas')->insert([
                'codigo' => $codigo,
                'nome' => CaixaConta::NOME_CAIXA_GERAL,
                'tipo' => 'SUBCAIXA',
                'situacao' => 'aberto',
                'ativo' => true,
                'sistema' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('caixa_contas') || ! Schema::hasColumn('caixa_contas', 'sistema')) {
            return;
        }

        Schema::table('caixa_contas', function (Blueprint $table) {
            $table->dropIndex(['sistema']);
            $table->dropColumn('sistema');
        });
    }
};
