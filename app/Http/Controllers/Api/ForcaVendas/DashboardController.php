<?php

namespace App\Http\Controllers\Api\ForcaVendas;

use App\Support\ForcaVendas\ForcaVendasDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController
{
    public function __invoke(Request $request, ForcaVendasDashboardService $service): JsonResponse
    {
        $user = $request->user();

        return response()->json($service->build($user));
    }
}
