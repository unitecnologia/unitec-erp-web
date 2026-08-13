<?php

use App\Support\Erp\ErpAccess;

if (! function_exists('erp_can')) {
    function erp_can(string $permission): bool
    {
        return ErpAccess::currentCan($permission);
    }
}
