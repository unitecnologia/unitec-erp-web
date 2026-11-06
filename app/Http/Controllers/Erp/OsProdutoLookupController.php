<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Os\OsProdutoLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OsProdutoLookupController extends Controller
{
    public function __invoke(Request $request, OsProdutoLookupService $lookup): JsonResponse
    {
        ErpAccess::authorize(Auth::user(), 'ordens_servico.access');

        $term = mb_strtoupper(trim((string) $request->query('q', '')), 'UTF-8');

        if (mb_strlen($term) > 80) {
            $term = mb_substr($term, 0, 80);
        }

        $tab = $request->query('tab') === 'servicos' ? 'servicos' : 'pecas';
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? Auth::user()?->empresa_id ?? 0);

        $started = microtime(true);
        $results = $term === '' ? [] : $lookup->buscar($term, $tab, $empresaId > 0 ? $empresaId : null);
        $wallMs = (microtime(true) - $started) * 1000;

        $response = response()->json([
            'term' => $term,
            'tab' => $tab,
            'results' => $results,
        ]);

        // Só cabeçalho de medição pontual — o JSON da lista não muda.
        return $response->header('X-Os-Lookup-Ms', number_format($wallMs, 1, '.', ''));
    }
}
