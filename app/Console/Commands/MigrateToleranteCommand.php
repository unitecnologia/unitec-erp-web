<?php

namespace App\Console\Commands;

use App\Support\Erp\Database\TolerantMigrateRunner;
use Illuminate\Console\Command;

class MigrateToleranteCommand extends Command
{
    protected $signature = 'unitec:migrate-tolerante';

    protected $description = 'migrate --force tolerante a tabela/coluna ja existente (marca Ran e continua)';

    public function handle(TolerantMigrateRunner $runner): int
    {
        $result = $runner->run(base_path(), function (string $chunk): void {
            $this->output->write($chunk);
        });

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
