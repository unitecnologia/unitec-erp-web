<?php

namespace App\Console\Commands;

use App\Support\Erp\ErpUpdateService;
use Illuminate\Console\Command;

class ApplyUnitecUpdateCommand extends Command
{
    protected $signature = 'unitec:apply-update {--app-path=}';

    protected $description = 'Baixa e aplica o pacote Unitec-ERP-Update.zip em segundo plano';

    public function handle(ErpUpdateService $service): int
    {
        $appPath = (string) ($this->option('app-path') ?: base_path());

        try {
            $service->run($appPath);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
