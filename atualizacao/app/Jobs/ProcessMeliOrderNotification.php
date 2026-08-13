<?php

namespace App\Jobs;

use App\Support\MercadoLivre\MeliOrderIngestService;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessMeliOrderNotification
{
    use Dispatchable;

    public function __construct(
        public string $topic,
        public string $resource,
        public string|int $meliUserId,
    ) {}

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function handle(MeliOrderIngestService $ingestService): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        return $ingestService->ingestNotification(
            (string) $this->topic,
            (string) $this->resource,
            (string) $this->meliUserId,
        );
    }
}
