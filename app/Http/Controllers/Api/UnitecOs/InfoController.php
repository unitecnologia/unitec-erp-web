<?php

namespace App\Http\Controllers\Api\UnitecOs;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfoController
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'app' => 'unitec-os',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function index(): JsonResponse
    {
        $empresas = Empresa::query()
            ->where('ativo', true)
            ->orderByRaw('COALESCE(NULLIF(fantasia, ""), NULLIF(nome, ""), razao_social) ASC')
            ->get(['id', 'nome', 'fantasia', 'razao_social', 'codigo'])
            ->map(fn (Empresa $e): array => [
                'id' => $e->id,
                'codigo' => $e->codigo,
                'nome' => $e->fantasia ?: ($e->nome ?: $e->razao_social),
            ])
            ->all();

        return response()->json([
            'app' => 'unitec-os',
            'server_time' => now()->toIso8601String(),
            'empresas' => $empresas,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $empresaId = $request->integer('empresa_id');

        $users = User::query()
            ->where('ativo', true)
            ->whereNotNull('senha_app_forca_vendas')
            ->where('senha_app_forca_vendas', '!=', '')
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('name')
            ->get(['id', 'name', 'empresa_id', 'vendedor_id'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'empresa_id' => $u->empresa_id,
                'vendedor_id' => $u->vendedor_id,
            ])
            ->all();

        return response()->json(['users' => $users]);
    }
}
