<?php

namespace App\Console\Commands;

use App\Support\Erp\Database\TolerantMigrateRunner;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseMigrateCommand;

/**
 * Com --force (atualizador / reparo): usa migrate tolerante a schema já existente.
 * Assim o apply antigo (ainda em memória) que chama "migrate --force" após a Copia
 * já beneficia do código novo no disco.
 */
class MigrateCommand extends BaseMigrateCommand
{
    public function handle()
    {
        $plain = getenv('UNITEC_MIGRATE_PLAIN');
        $usePlain = is_string($plain) && in_array(strtolower($plain), ['1', 'true', 'yes'], true);

        if ($this->option('force') && ! $usePlain) {
            $result = app(TolerantMigrateRunner::class)->run(base_path(), function (string $chunk): void {
                $this->output->write($chunk);
            });

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        return parent::handle();
    }
}
