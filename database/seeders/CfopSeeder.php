<?php

namespace Database\Seeders;

use App\Models\Cfop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CFOPs padrão do sistema (catálogo fiscal brasileiro usado pelo ERP).
 * Dados embutidos em database/data/fiscal/cfops.json.
 */
class CfopSeeder extends Seeder
{
    public function run(): void
    {
        $count = static::seedFromJson();

        if ($count === null) {
            $this->command?->warn('Arquivo padrão CFOP não encontrado ou inválido.');

            return;
        }

        $this->command?->info("CFOP padrão: {$count} registro(s).");
    }

    /**
     * Garante CFOPs padrão na base web (idempotente: só cria códigos ausentes).
     *
     * @return int|null null se arquivo inválido; senão quantidade processada do JSON
     */
    public static function seedFromJson(): ?int
    {
        $path = database_path('data/fiscal/cfops.json');

        if (! is_file($path)) {
            return null;
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $now = now();
        $created = 0;

        DB::transaction(function () use ($rows, $now, &$created): void {
            foreach ($rows as $row) {
                if (! is_array($row) || blank($row['codigo'] ?? null)) {
                    continue;
                }

                $codigo = (int) $row['codigo'];

                if ($codigo < 1) {
                    continue;
                }

                $tipo = strtoupper(trim((string) ($row['tipo'] ?? Cfop::TIPO_ENTRADA)));
                $operacao = strtoupper(trim((string) ($row['operacao'] ?? Cfop::OPERACAO_INTERNA)));

                $payload = [
                    'descricao' => mb_strtoupper(trim((string) ($row['descricao'] ?? '')), 'UTF-8'),
                    'tipo' => in_array($tipo, [Cfop::TIPO_ENTRADA, Cfop::TIPO_SAIDA], true)
                        ? $tipo
                        : Cfop::TIPO_ENTRADA,
                    'operacao' => in_array($operacao, [Cfop::OPERACAO_INTERNA, Cfop::OPERACAO_EXTERNA], true)
                        ? $operacao
                        : Cfop::OPERACAO_INTERNA,
                    'movimenta_estoque' => (bool) ($row['movimenta_estoque'] ?? true),
                    'ativo' => (bool) ($row['ativo'] ?? true),
                    'updated_at' => $now,
                ];

                if ($payload['descricao'] === '') {
                    continue;
                }

                $existing = Cfop::query()->where('codigo', $codigo)->exists();

                if ($existing) {
                    continue;
                }

                Cfop::query()->insert([
                    'codigo' => $codigo,
                    ...$payload,
                    'created_at' => $now,
                ]);
                $created++;
            }
        });

        return count($rows);
    }
}
