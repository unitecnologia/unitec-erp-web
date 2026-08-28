<?php

namespace App\Console\Commands;

use App\Support\Erp\Atualizacao\AtualizacaoApplyService;
use Illuminate\Console\Command;

class ApplyAtualizacaoCommand extends Command
{
    protected $signature = 'unitec:apply-atualizacao {--app-path=}';

    protected $description = 'Aplica arquivos da pasta atualizacao/ (sem ZIP) e roda migrate';

    public function handle(AtualizacaoApplyService $service): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $appPath = (string) ($this->option('app-path') ?: base_path());

        try {
            $version = $service->apply($appPath);
            $this->info('Atualização aplicada: '.$version);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
