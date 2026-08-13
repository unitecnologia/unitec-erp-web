<?php

namespace App\Http\Controllers\Api\ForcaVendas;

use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Detalhe de orçamento para o app abrir/converter (online).
 */
class OrcamentoController
{
    public function show(Request $request, Orcamento $orcamento): JsonResponse
    {
        $user = $request->user();
        $vendedorId = (int) ($user?->vendedor_id ?? 0);

        if ($vendedorId > 0 && (int) ($orcamento->vendedor_id ?? 0) !== $vendedorId) {
            return response()->json(['message' => 'Orçamento não pertence a este vendedor.'], 403);
        }

        $orcamento->load(['itens.product:id,descricao', 'forcaVendasOrder:id,uuid,orcamento_id,tipo']);

        $fv = $orcamento->forcaVendasOrder;
        if ($fv && $fv->tipo === ForcaVendasOrder::TIPO_PEDIDO) {
            return response()->json(['message' => 'Este documento já é um pedido.'], 422);
        }

        $itens = $orcamento->itens
            ->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'descricao' => $item->descricao ?: $item->product?->descricao,
                'quantidade' => (float) $item->quantidade,
                'preco_unitario' => (float) $item->preco_unitario,
                'desconto' => (float) ($item->desconto ?? 0),
            ])
            ->values()
            ->all();

        return response()->json([
            'id' => $orcamento->id,
            'uuid' => $fv?->uuid ?: ('erp-orc-'.$orcamento->id),
            'numero' => $orcamento->numero,
            'data' => optional($orcamento->data)->toDateString(),
            'cliente_id' => $orcamento->cliente_id,
            'vendedor_id' => $orcamento->vendedor_id,
            'total' => (float) $orcamento->total,
            'status' => $orcamento->status,
            'tipo' => 'orcamento',
            'situacao' => $orcamento->status,
            'observacoes' => $orcamento->observacoes,
            'desconto_valor' => (float) ($orcamento->desconto_valor ?? 0),
            'percentual_desconto' => (float) ($orcamento->percentual_desconto ?? 0),
            'forma_pagamento' => $orcamento->forma_pagamento,
            'itens' => $itens,
        ]);
    }
}
