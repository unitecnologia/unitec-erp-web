<?php

namespace App\Http\Controllers\Api\Pdv;

use App\Support\Pdv\PdvRetornoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetornoController
{
    public function __construct(private readonly PdvRetornoService $service)
    {
    }

    /**
     * Recebe um lote de vendas do mini-PDV offline e as importa no ERP de forma
     * idempotente (guardadas pelo uuid). Retorna o resultado por venda.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'vendas' => ['required', 'array', 'min:1'],
            'vendas.*.uuid' => ['required', 'string', 'max:64'],
            'vendas.*.total' => ['required', 'numeric'],
            'vendas.*.cliente_central_id' => ['nullable', 'integer'],
            'vendas.*.crediario_dias' => ['nullable', 'string', 'max:255'],
            'vendas.*.itens' => ['required', 'array', 'min:1'],
            'vendas.*.itens.*.descricao' => ['required', 'string'],
            'vendas.*.itens.*.quantidade' => ['required', 'numeric'],
        ]);

        $empresaId = (int) ($request->input('empresa_id')
            ?: config('pdv_carga.default_empresa_id')
            ?: 1);

        $terminal = trim((string) $request->input('terminal', ''));
        $terminal = $terminal !== '' ? $terminal : null;

        $resultados = $this->service->importar(
            (array) $request->input('vendas', []),
            $empresaId,
            $terminal,
        );

        return response()->json([
            'results' => $resultados,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
