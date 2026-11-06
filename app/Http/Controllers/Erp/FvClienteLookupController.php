<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\Erp\ErpAccess;
use App\Support\ForcaVendas\FvClienteLookupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FvClienteLookupController extends Controller
{
    public function __invoke(Request $request, FvClienteLookupService $lookup): JsonResponse
    {
        $user = Auth::user();

        if (
            ! ErpAccess::can($user, 'vendas.access')
            && ! ErpAccess::can($user, 'forca_vendas.access')
        ) {
            throw new AuthorizationException('Sem permissão para a Tela de Venda.');
        }

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) > 80) {
            $term = mb_substr($term, 0, 80);
        }

        $started = microtime(true);
        $results = $term === '' ? [] : $lookup->buscar($term);
        $wallMs = (microtime(true) - $started) * 1000;

        return response()
            ->json([
                'term' => $term,
                'results' => $results,
            ])
            ->header('X-Fv-Cliente-Lookup-Ms', number_format($wallMs, 1, '.', ''));
    }
}
