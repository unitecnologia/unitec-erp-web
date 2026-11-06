<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\Erp\ErpAccess;
use App\Support\ForcaVendas\FvClienteLookupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lookup de clientes no F4 da OS (GET + DOM).
 * Reutiliza a mesma query da Tela de Venda; permissão própria da OS.
 */
class OsClienteLookupController extends Controller
{
    public function __invoke(Request $request, FvClienteLookupService $lookup): JsonResponse
    {
        $user = Auth::user();

        if (! ErpAccess::can($user, 'ordens_servico.access')) {
            throw new AuthorizationException('Sem permissão para Ordens de Serviço.');
        }

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) > 80) {
            $term = mb_substr($term, 0, 80);
        }

        $results = $term === '' ? [] : $lookup->buscar($term);

        return response()->json([
            'term' => $term,
            'results' => $results,
        ]);
    }
}
