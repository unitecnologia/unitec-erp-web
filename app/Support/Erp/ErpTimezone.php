<?php

namespace App\Support\Erp;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class ErpTimezone
{
    public const DEFAULT = 'America/Sao_Paulo';

    public static function toLocal(CarbonInterface|string|null $moment = null): Carbon
    {
        if ($moment === null) {
            return Carbon::now(self::DEFAULT);
        }

        return Carbon::parse($moment)->timezone(self::DEFAULT);
    }
}
