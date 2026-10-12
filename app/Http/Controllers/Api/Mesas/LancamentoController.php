<?php

namespace App\Http\Controllers\Api\Mesas;

use App\Models\Empresa;
use App\Models\MesasDevice;
use App\Models\PdvMesaLancamento;
use App\Models\User;
use App\Support\Mesas\MesasConflito;
use App\Support\Mesas\MesasLancamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LancamentoController
{
    public function store(Request $request, MesasLancamentoService $service): JsonResponse
    {
        $dados = $request->validate([
            'uuid' => ['required', 'uuid'],
            'numero' => ['required', 'integer', 'min:1', 'max:300'],
            'itens' => ['required', 'array', 'min:1', 'max:100'],
            'itens.*.product_id' => ['required', 'integer', 'min:1'],
            'itens.*.quantidade' => ['required', 'numeric', 'min:0.001', 'max:9999'],
            'itens.*.preco' => ['required', 'numeric', 'min:0'],
            'itens.*.nivel' => ['nullable', 'string', 'in:varejo,atacado,especial'],
            'itens.*.price_table_id' => ['nullable', 'integer', 'min:1'],
            'itens.*.obs' => ['nullable', 'string', 'max:140'],
            'aberta_em_vista' => ['nullable', 'string', 'max:30'],
            'confirmar_mesa' => ['nullable', 'boolean'],
            'aceitar_precos' => ['nullable', 'boolean'],
        ]);

        if (! Schema::hasTable((new PdvMesaLancamento)->getTable())) {
            return response()->json([
                'message' => 'Servidor sem as tabelas do Unitec Mesas. Reinicie o ERP para atualizar o banco.',
                'code' => 'server_not_ready',
                'retry' => true,
            ], 503);
        }

        /** @var User $user */
        $user = $request->user();
        /** @var MesasDevice $device */
        $device = $request->attributes->get('mesas_device');
        $empresa = Empresa::query()->findOrFail((int) $user->empresa_id);

        try {
            return response()->json($service->lancar($empresa, $user, (string) $device->device_uuid, $dados));
        } catch (MesasConflito $conflito) {
            return response()->json($conflito->toArray(), 409);
        }
    }
}
