<?php

namespace App\Jobs;

use App\Support\ContadorCloud\ContadorCloudSyncService;
use Illuminate\Foundation\Bus\Dispatchable;

class ContadorCloudProcessPendingJob
{
    use Dispatchable;

    public function __construct(
        public int $empresaId,
        public int $limit = 200,
    ) {}

    public function handle(ContadorCloudSyncService $syncService): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $syncService->processPending($this->empresaId, $this->limit);
    }
}
