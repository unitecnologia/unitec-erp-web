<?php

namespace App\Console\Commands;

use App\Support\Logistica\ExpedicaoService;
use Illuminate\Console\Command;

/**
 * Gera entregas faltantes para vendas já fechadas (ex.: faturadas com expedição desligada).
 */
final class ExpedicaoBackfillCommand extends Command
{
    protected $signature = 'expedicao:backfill {--limit= : Máximo de vendas a processar}';

    protected $description = 'Cria registros de expedição para vendas fechadas sem entrega';

    public function handle(ExpedicaoService $service): int
    {
        $limitOpt = $this->option('limit');
        $limit = filled($limitOpt) ? max(1, (int) $limitOpt) : null;

        $result = $service->backfillEntregasFaltantes($limit);

        $this->info(sprintf(
            'Expedição backfill: criadas=%d ja_existiam=%d ignoradas=%d',
            $result['criadas'],
            $result['ja_existiam'],
            $result['ignoradas'],
        ));

        return self::SUCCESS;
    }
}
