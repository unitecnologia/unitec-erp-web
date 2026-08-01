<?php

namespace App\Console\Commands;

use App\Support\Erp\ErpUpdateService;
use Illuminate\Console\Command;

class ApplyUnitecUpdateCommand extends Command
{
    protected $signature = 'unitec:apply-update {--app-path=}';

    protected $description = 'Aplica o pacote Unitec-ERP-Update.zip (preferindo o ZIP local em storage/app/private/updates)';

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
