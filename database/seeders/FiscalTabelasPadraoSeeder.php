<?php

namespace Database\Seeders;

use App\Models\FiscalClassificacaoTributaria;
use App\Models\FiscalIbptItem;
use App\Support\Erp\Fiscal\IbptTabelaPadrao;
use App\Support\Erp\Fiscal\NcmCatalogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tabelas fiscais padrão do sistema (CFOP + cClassTrib + IBPT + NCM + Tabela ICMS).
 * Empregam dados oficiais embutidos em database/data/fiscal.
 * cClassTrib, IBPT e NCM: se já houver registros, o seed preserva (não esvazia).
 * Atualização intencional: botão "Atualizar tabela" / Importar IPBTAX na tela.
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
        if (FiscalClassificacaoTributaria::query()->exists()) {
            $this->command?->info(
                'cClassTrib já presente — preservada ('.FiscalClassificacaoTributaria::query()->count().' registro(s)).'
            );

            return;
        }

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
        // Tabela padrão do sistema: se já houver dados (instalação/importação), nunca apagar.
        if (FiscalIbptItem::query()->exists()) {
            $this->command?->info('IBPT já presente — preservada ('.FiscalIbptItem::query()->count().' registro(s)).');
            $this->ensureNcmsFromIbpt();

            return;
        }

        $total = IbptTabelaPadrao::carregarSeVazia();

        if ($total === 0) {
            $this->command?->warn('Arquivo padrão IBPT não encontrado ou sem linhas válidas em database/data/fiscal.');

            return;
        }

        $this->command?->info('IBPT padrão: '.$total.' registro(s).');
    }

    /**
     * Catálogo ncms padrão a partir da IPBTAX (upsert — nunca apaga NCMs existentes).
     */
    protected function ensureNcmsFromIbpt(): void
    {
        if (! FiscalIbptItem::query()->exists()) {
            return;
        }

        try {
            $result = (new NcmCatalogService)->syncFromIbpt();
            $this->command?->info(
                'NCM padrão (de IBPT): '.$result['synced'].' código(s)'
                .' — criados '.$result['created']
                .', atualizados '.$result['updated'].'.'
            );
        } catch (\Throwable $e) {
            report($e);
            $this->command?->warn('Falha ao sincronizar NCMs a partir da IBPT: '.$e->getMessage());
        }
    }
}
