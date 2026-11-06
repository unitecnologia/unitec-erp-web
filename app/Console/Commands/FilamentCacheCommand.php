<?php

namespace App\Console\Commands;

use App\Support\Erp\FilamentComponentCache;
use Illuminate\Console\Command;

class FilamentCacheCommand extends Command
{
    protected $signature = 'unitec:filament-cache
                            {--force : Regenera mesmo se o cache já existir}';

    protected $description = 'Garante/reconstrói o cache de componentes Filament (com lock anti-corrida)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $result = FilamentComponentCache::ensure(force: $force);

        if ($result['ok']) {
            if ($result['skipped']) {
                $this->line($result['message']);
            } else {
                $this->info($result['message']);
            }

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
