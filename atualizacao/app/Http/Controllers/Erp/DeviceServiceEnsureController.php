<?php

namespace App\Http\Controllers\Erp;

use App\Support\Erp\Printing\DeviceServiceLauncher;
use Illuminate\Http\JsonResponse;

class DeviceServiceEnsureController
{
    public function __invoke(): JsonResponse
    {
        $result = DeviceServiceLauncher::ensureRunning();

        return response()->json($result, $result['ok'] || $result['started'] ? 200 : 503);
    }
}
