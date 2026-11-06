<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\ForcaVendas\FvProdutoLookupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FvProdutoLookupController extends Controller
{
    public function __invoke(Request $request, FvProdutoLookupService $lookup): JsonResponse
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

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? $user?->empresa_id ?? 0);
        $vendedorId = (int) $request->query('vendedor_id', 0);
        $estoqueId = (int) $request->query('estoque_id', 0);
        $tabelaPrecoId = (int) $request->query('tabela_preco_id', 0);

        $started = microtime(true);
        $results = $term === ''
            ? []
            : $lookup->buscar(
                $term,
                $empresaId > 0 ? $empresaId : null,
                $vendedorId > 0 ? $vendedorId : null,
                $estoqueId > 0 ? $estoqueId : null,
                $tabelaPrecoId > 0 ? $tabelaPrecoId : null,
            );
        $wallMs = (microtime(true) - $started) * 1000;

        $timings = $lookup->lastTimingsMs;
        $svcMs = (float) ($timings['total'] ?? $wallMs);

        return response()
            ->json([
                'term' => $term,
                'results' => $results,
            ])
            ->header('X-Fv-Lookup-Ms', number_format($wallMs, 1, '.', ''))
            ->header('X-Fv-Lookup-Svc-Ms', number_format($svcMs, 1, '.', ''));
    }
}
