<?php

namespace App\Http\Controllers\Api\UnitecOs;

use App\Models\User;
use App\Support\UnitecOs\CatalogSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController
{
    public function pull(Request $request, CatalogSyncService $catalog): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $catalog->pull($user);

        return response()->json([
            'data' => $data,
            'pulled_at' => now()->toIso8601String(),
        ]);
    }
}
