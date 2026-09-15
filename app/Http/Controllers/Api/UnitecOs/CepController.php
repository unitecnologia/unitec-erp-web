<?php

namespace App\Http\Controllers\Api\UnitecOs;

use App\Support\Erp\CepLookupService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CepController
{
    public function show(string $cep, CepLookupService $lookup): JsonResponse
    {
        try {
            $fields = $lookup->lookup($cep);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => $fields,
        ]);
    }
}
