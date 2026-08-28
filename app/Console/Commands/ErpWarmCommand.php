<?php

namespace App\Console\Commands;

use App\Support\Erp\ErpWarmService;
use Illuminate\Console\Command;

class ErpWarmCommand extends Command
{
    protected $signature = 'unitec:warm
                            {--routes-only : Só visita rotas do menu (sem compilar arquivos)}
                            {--compile-only : Só compila OPcache (sem visitar rotas)}
                            {--limit=0 : Limita quantidade de rotas (0 = todas)}';

    protected $description = 'Aquece OPcache e telas do ERP para acelerar o primeiro acesso';

    public function handle(ErpWarmService $warm): int
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $compile = ! (bool) $this->option('routes-only');
        $visitRoutes = ! (bool) $this->option('compile-only');
        $limit = max(0, (int) $this->option('limit'));

        $result = $warm->warm(
            compileFiles: $compile,
            visitRoutes: $visitRoutes,
            routeLimit: $limit,
        );

        if (! $this->output->isQuiet()) {
            if ($result['ok']) {
                $this->info($result['message']);
            } else {
                $this->warn($result['message']);
            }

            $this->line(sprintf(
                'Detalhe: %d arquivos · %d/%d rotas · %d ms',
                $result['compiled'],
                $result['routes_ok'],
                $result['routes_total'],
                $result['elapsed_ms'],
            ));
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
