<?php

namespace App\Http\Controllers\Api\ForcaVendas;

use App\Support\ForcaVendas\ForcaVendasComissaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ComissaoController
{
    public function __invoke(Request $request, ForcaVendasComissaoService $service): JsonResponse
    {
        $hoje = Carbon::today();
        $de = $this->parseDate($request->query('de'), $hoje->copy()->startOfMonth());
        $ate = $this->parseDate($request->query('ate'), $hoje->copy());

        return response()->json($service->build($request->user(), $de, $ate));
    }

    private function parseDate(mixed $value, Carbon $default): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return $default;
        }
    }
}
