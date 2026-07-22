<?php

namespace App\Http\Controllers\Api\ForcaVendas;

use App\Models\Estoque;
use App\Models\Product;
use App\Models\User;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\ProductEstoqueSaldoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Consulta saldo do produto por depósito/filial (online).
 */
class ProductEstoqueFiliaisController
{
    public function __invoke(Request $request, Product $product): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $empresaIds = $user->accessibleEmpresaIds();
        if ($empresaIds === []) {
            $empresaId = (int) ($user->empresa_id ?? 0);
            $empresaIds = $empresaId > 0 ? [$empresaId] : [];
        }

        $saldos = new ProductEstoqueSaldoService();
        $reservas = new EstoqueReservaService();

        $filiais = [];

        if ($empresaIds !== [] && Schema::hasTable('estoques')) {
            $estoques = Estoque::query()
                ->with(['empresa:id,codigo,nome'])
                ->whereIn('empresa_id', $empresaIds)
                ->where('ativo', true)
                ->orderBy('empresa_id')
                ->orderByRaw('CAST(codigo AS UNSIGNED)')
                ->orderBy('codigo')
                ->get(['id', 'empresa_id', 'codigo', 'nome']);

            foreach ($estoques as $estoque) {
                $estoqueId = (int) $estoque->id;
                $atual = $saldos->fisico((int) $product->id, $estoqueId);
                $reservado = $reservas->reservadoAtivo((int) $product->id, $estoqueId);
                $disponivel = $atual - $reservado;
                $empresaNome = trim((string) ($estoque->empresa?->nome ?? ''));

                $filiais[] = [
                    'empresa_id' => (int) $estoque->empresa_id,
                    'empresa_nome' => $empresaNome !== '' ? $empresaNome : 'Empresa',
                    'estoque_id' => $estoqueId,
                    'estoque_codigo' => (string) $estoque->codigo,
                    'estoque_nome' => (string) ($estoque->nome ?? $estoque->label()),
                    'atual' => round($atual, 3),
                    'reservado' => round($reservado, 3),
                    'disponivel' => round($disponivel, 3),
                ];
            }
        }

        if ($filiais === []) {
            $atual = (float) ($product->estoque ?? 0);
            $reservado = $reservas->reservadoAtivo((int) $product->id);
            $filiais[] = [
                'empresa_id' => (int) ($user->empresa_id ?? 0),
                'empresa_nome' => 'Loja atual',
                'estoque_id' => null,
                'estoque_codigo' => '',
                'estoque_nome' => 'Estoque geral',
                'atual' => round($atual, 3),
                'reservado' => round($reservado, 3),
                'disponivel' => round($atual - $reservado, 3),
            ];
        }

        return response()->json([
            'produto_id' => (int) $product->id,
            'codigo' => (string) ($product->codigo ?? ''),
            'descricao' => (string) ($product->descricao ?? ''),
            'unidade' => (string) ($product->unidade ?? ''),
            'filiais' => $filiais,
        ]);
    }
}
