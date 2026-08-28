<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\Erp\ErpWarmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ErpWarmUrlsController extends Controller
{
    public function __invoke(ErpWarmService $warm): JsonResponse
    {
        $paths = $warm->collectPrefetchPathsForUser(Auth::user(), limit: 15);

        return response()->json([
            'paths' => $paths,
        ]);
    }
}
