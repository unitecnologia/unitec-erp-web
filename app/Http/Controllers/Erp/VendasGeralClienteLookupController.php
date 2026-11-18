<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\Erp\ClienteLookupService;
use App\Support\Erp\ErpAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendasGeralClienteLookupController extends Controller
{
    public function __invoke(Request $request, ClienteLookupService $lookup): JsonResponse
    {
        abort_unless(ErpAccess::currentCan('vendas.print'), 403);

        $term = trim((string) $request->query('q', ''));
        $results = $lookup->buscar($term);

        return response()->json([
            'term' => $term,
            'results' => $results,
        ]);
    }
}
