<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfse_codigos_municipais', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('descricao', 150);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        $agora = now();
        $existentes = DB::table('products')
            ->whereNotNull('c_trib_mun')
            ->where('c_trib_mun', '!=', '')
            ->select('c_trib_mun', DB::raw('MIN(descricao) as descricao'))
            ->groupBy('c_trib_mun')
            ->get()
            ->map(fn (object $linha): array => [
                'codigo' => mb_substr(trim((string) $linha->c_trib_mun), 0, 20),
                'descricao' => mb_substr(mb_strtoupper(trim((string) $linha->descricao) ?: (string) $linha->c_trib_mun, 'UTF-8'), 0, 150),
                'ativo' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ])
            ->all();

        if ($existentes !== []) {
            DB::table('nfse_codigos_municipais')->insertOrIgnore($existentes);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nfse_codigos_municipais');
    }
};
