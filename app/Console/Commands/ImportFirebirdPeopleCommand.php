<?php

namespace App\Console\Commands;

use App\Support\Erp\Import\FirebirdPersonImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportFirebirdPeopleCommand extends Command
{
    protected $signature = 'erp:import-firebird-pessoas
                            {file : Caminho do JSON exportado (array ou {"pessoas":[]})}
                            {--update : Atualiza pessoas existentes pelo código}
                            {--dry-run : Simula sem gravar}';

    protected $description = 'Importa pessoas de um export JSON do Firebird (PESSOA)';

    public function handle(FirebirdPersonImportService $service): int
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
            : ($decoded['pessoas'] ?? $decoded['PESSOA'] ?? []);

        if (! is_array($rows) || $rows === []) {
            $this->error('Nenhuma pessoa encontrada no JSON. Use um array ou a chave "pessoas".');

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
