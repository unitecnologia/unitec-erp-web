<?php

namespace App\Console\Commands;

use App\Support\Erp\Import\FirebirdProductImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportFirebirdProductsCommand extends Command
{
    protected $signature = 'erp:import-firebird-products
                            {file : Caminho do JSON exportado (array ou {"produtos":[]})}
                            {--update : Atualiza produtos existentes pelo código}
                            {--dry-run : Simula sem gravar}';

    protected $description = 'Importa produtos de um export JSON do Firebird (PRODUTO)';

    public function handle(FirebirdProductImportService $service): int
    {
        $path = (string) $this->argument('file');

        if (! File::exists($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            $this->error('JSON inválido.');

            return self::FAILURE;
        }

        $rows = array_is_list($decoded)
            ? $decoded
            : ($decoded['produtos'] ?? $decoded['PRODUTO'] ?? []);

        if (! is_array($rows) || $rows === []) {
            $this->error('Nenhum produto encontrado no JSON. Use um array ou a chave "produtos".');

            return self::FAILURE;
        }

        $stats = $service->importRows(
            $rows,
            updateExisting: (bool) $this->option('update'),
            dryRun: (bool) $this->option('dry-run'),
        );

        $prefix = $this->option('dry-run') ? '[dry-run] ' : '';

        $this->info("{$prefix}Criados: {$stats['created']} | Atualizados: {$stats['updated']} | Ignorados: {$stats['skipped']}");

        return self::SUCCESS;
    }
}
