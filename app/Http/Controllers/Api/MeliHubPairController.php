<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MercadoLivre\MeliHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeliHubPairController extends Controller
{
    public function store(Request $request, MeliHubService $hub): JsonResponse
    {
        $result = $hub->createPair(
            $request->filled('empresa_id') ? (int) $request->input('empresa_id') : null,
            trim((string) $request->input('client_label', '')),
        );

        return response()->json($result, $result['ok'] ? 201 : 422);
    }

    public function show(string $uuid, MeliHubService $hub): JsonResponse
    {
        $result = $hub->pairStatus($uuid);

        return response()->json($result, $result['ok'] ? 200 : 404);
    }
}
