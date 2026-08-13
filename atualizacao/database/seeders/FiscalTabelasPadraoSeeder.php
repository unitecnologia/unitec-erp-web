<?php

namespace Database\Seeders;

use App\Models\FiscalClassificacaoTributaria;
use App\Models\FiscalIbptItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tabelas fiscais padrão do sistema (CFOP + cClassTrib + IBPT + Tabela ICMS).
 * Empregam dados oficiais embutidos em database/data/fiscal.
 */
class FiscalTabelasPadraoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CfopSeeder::class);
        $this->seedCclassTrib();
        $this->seedIbpt();
        $this->call(IcmsAliquotasSeeder::class);
    }

    protected function seedCclassTrib(): void
    {
        $path = database_path('data/fiscal/cclass_trib.json');

        if (! is_file($path)) {
            $this->command?->warn('Arquivo padrão cClassTrib não encontrado: '.$path);

            return;
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows) || $rows === []) {
            $this->command?->warn('Arquivo padrão cClassTrib inválido ou vazio.');

            return;
        }

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            if (! is_array($row) || blank($row['codigo'] ?? null)) {
                continue;
            }

            $payload[] = [
                'codigo' => (string) $row['codigo'],
                'cst_ibs_cbs' => $row['cst_ibs_cbs'] ?? null,
                'cst_descricao' => $row['cst_descricao'] ?? null,
                'descricao' => $row['descricao'] ?? null,
                'nome_reduzido' => $row['nome_reduzido'] ?? null,
                'ind_nfe' => array_key_exists('ind_nfe', $row) ? (bool) $row['ind_nfe'] : null,
                'ind_nfce' => array_key_exists('ind_nfce', $row) ? (bool) $row['ind_nfce'] : null,
                'ind_nfse' => array_key_exists('ind_nfse', $row) ? (bool) $row['ind_nfse'] : null,
                'ind_cte' => array_key_exists('ind_cte', $row) ? (bool) $row['ind_cte'] : null,
                'vigencia_inicio' => $row['vigencia_inicio'] ?? null,
                'vigencia_fim' => $row['vigencia_fim'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($payload): void {
            FiscalClassificacaoTributaria::query()->delete();

            foreach (array_chunk($payload, 500) as $chunk) {
                FiscalClassificacaoTributaria::query()->insert($chunk);
            }
        });

        $this->command?->info('cClassTrib padrão: '.count($payload).' registro(s).');
    }

    protected function seedIbpt(): void
    {
        $gzPath = database_path('data/fiscal/ibpt_itens.jsonl.gz');
        $plainPath = database_path('data/fiscal/ibpt_itens.jsonl');

        $contents = null;

        if (is_file($gzPath)) {
            $raw = file_get_contents($gzPath);
            $contents = $raw === false ? null : @gzdecode($raw);
        } elseif (is_file($plainPath)) {
            $contents = file_get_contents($plainPath);
        }

        if (! is_string($contents) || trim($contents) === '') {
            $this->command?->warn('Arquivo padrão IBPT não encontrado em database/data/fiscal.');

            return;
        }

        $now = now();
        $payload = [];
        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);

            if (! is_array($row) || blank($row['ncm'] ?? null)) {
                continue;
            }

            $payload[] = [
                'ncm' => (string) $row['ncm'],
                'ex_tipi' => $row['ex_tipi'] ?? null,
                'tipo' => $row['tipo'] ?? null,
                'descricao' => isset($row['descricao']) ? mb_substr((string) $row['descricao'], 0, 500) : null,
                'aliq_nacional' => (float) ($row['aliq_nacional'] ?? 0),
                'aliq_importado' => (float) ($row['aliq_importado'] ?? 0),
                'aliq_estadual' => (float) ($row['aliq_estadual'] ?? 0),
                'aliq_municipal' => (float) ($row['aliq_municipal'] ?? 0),
                'vigencia_inicio' => $row['vigencia_inicio'] ?? null,
                'vigencia_fim' => $row['vigencia_fim'] ?? null,
                'chave' => $row['chave'] ?? null,
                'versao' => $row['versao'] ?? null,
                'fonte' => $row['fonte'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($payload === []) {
            $this->command?->warn('Arquivo padrão IBPT sem linhas válidas.');

            return;
        }

        DB::transaction(function () use ($payload): void {
            FiscalIbptItem::query()->delete();

            foreach (array_chunk($payload, 500) as $chunk) {
                FiscalIbptItem::query()->insert($chunk);
            }
        });

        $this->command?->info('IBPT padrão: '.count($payload).' registro(s).');
    }
}
