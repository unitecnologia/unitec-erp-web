<?php

namespace App\Console\Commands;

use App\Support\Erp\Import\MonacoSensumXlsxImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportMonacoSensumXlsxCommand extends Command
{
    protected $signature = 'erp:importar-monaco-xlsx
        {produtos : Caminho do XLSX de produtos (NCM)}
        {tabela-preco : Caminho do XLSX TabelaPreco}
        {pessoas : Caminho do XLSX de pessoas}
        {--force : Executa a importação (obrigatório fora de dry-run)}
        {--dry-run : Apenas analisa, sem gravar}';

    protected $description = 'DEV: zera produtos e importa catálogo Monaco (TabelaPreco + NCM + pessoas)';

    public function handle(MonacoSensumXlsxImportService $service): int
    {
        if (app()->environment('production')) {
            $this->error('Bloqueado: APP_ENV=production.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->error('Use --dry-run para analisar ou --force para importar.');

            return self::FAILURE;
        }

        $dbName = (string) config('database.connections.'.config('database.default').'.database');
        $this->info('Banco: '.$dbName.' | APP_ENV='.app()->environment());
        $this->info($dryRun ? 'Modo: dry-run' : 'Modo: IMPORTAÇÃO (zera produtos)');

        try {
            $stats = $service->import(
                (string) $this->argument('produtos'),
                (string) $this->argument('tabela-preco'),
                (string) $this->argument('pessoas'),
                $dryRun,
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Produtos ativos (planilha)', $stats['produtos_ativos_planilha']],
                ['Desativados pulados', $stats['produtos_desativados_pulados']],
                ['Produtos criados', $stats['produtos_criados']],
                ['NCM aplicados', $stats['ncm_aplicados']],
                ['NCM sem produto ativo', $stats['ncm_sem_produto']],
                ['Itens tabela preço 3', $stats['tabela_itens']],
                ['Pessoas criadas', $stats['pessoas_criadas']],
                ['Pessoas atualizadas', $stats['pessoas_atualizadas']],
                ['Pessoas puladas', $stats['pessoas_puladas']],
            ],
        );

        $this->info($dryRun ? 'Dry-run concluído (nada gravado).' : 'Importação concluída.');

        return self::SUCCESS;
    }
}
