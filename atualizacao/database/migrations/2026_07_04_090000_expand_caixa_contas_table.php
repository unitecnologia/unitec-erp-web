<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixa_contas', function (Blueprint $table) {
            $table->unsignedInteger('codigo')->nullable()->after('id');
            $table->string('tipo', 1)->default('S')->after('nome');
            $table->string('situacao', 20)->default('aberto')->after('tipo');
            $table->foreignId('ultimo_usuario_id')->nullable()->after('ativo')->constrained('users')->nullOnDelete();
        });

        $rows = DB::table('caixa_contas')->orderBy('id')->get();

        foreach ($rows as $index => $row) {
            DB::table('caixa_contas')->where('id', $row->id)->update([
                'codigo' => $index + 1,
                'tipo' => strtoupper((string) $row->nome) === 'CAIXA GERAL' ? 'X' : 'S',
                'situacao' => ($row->ativo ?? true) ? 'aberto' : 'fechado',
            ]);
        }

        Schema::table('caixa_contas', function (Blueprint $table) {
            $table->unique('codigo');
            $table->index('tipo');
            $table->index('situacao');
        });
    }

    public function down(): void
    {
        Schema::table('caixa_contas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ultimo_usuario_id');
            $table->dropUnique(['codigo']);
            $table->dropIndex(['tipo']);
            $table->dropIndex(['situacao']);
            $table->dropColumn(['codigo', 'tipo', 'situacao']);
        });
    }
};
