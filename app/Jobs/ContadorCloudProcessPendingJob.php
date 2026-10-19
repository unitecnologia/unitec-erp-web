<?php

namespace App\Jobs;

use App\Support\ContadorCloud\ContadorCloudSyncService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Reenvia automaticamente pendentes/falhas do Portal do Contador
 * (ex.: contingência emitida sem internet).
 */
class ContadorCloudProcessPendingJob
{
    use Dispatchable;

    public function __construct(
        public int $empresaId = 0,
        public int $limit = 200,
    ) {}

    public function handle(ContadorCloudSyncService $syncService): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $empresaId = $this->empresaId > 0 ? $this->empresaId : null;
        $syncService->processRetryable($empresaId, $this->limit);
    }
}
