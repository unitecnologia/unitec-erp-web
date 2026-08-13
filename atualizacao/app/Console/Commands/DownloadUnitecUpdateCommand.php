<?php

namespace App\Console\Commands;

use App\Support\Erp\ErpUpdateService;
use Illuminate\Console\Command;

class DownloadUnitecUpdateCommand extends Command
{
    protected $signature = 'unitec:download-update
                            {--app-path= : Caminho do ERP}
                            {--force : Baixa mesmo se já houver pacote}';

    protected $description = 'Verifica atualizações e baixa o ZIP para storage/app/private/updates (sem instalar)';

    public function handle(ErpUpdateService $service): int
    {
        $appPath = (string) ($this->option('app-path') ?: base_path());

        try {
            $result = $service->downloadPendingUpdate($appPath, (bool) $this->option('force'));
            $this->info($result['message']);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
