<?php

namespace App\Http\Controllers\Api\Mesas;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class InfoController
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'app' => 'unitec-mesas',
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
            'app' => 'unitec-mesas',
            'server_time' => now()->toIso8601String(),
            'empresas' => $empresas,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        if (! Schema::hasColumn('users', 'acesso_app_mesas')) {
            return response()->json(['users' => []]);
        }

        $empresaId = $request->integer('empresa_id');

        $users = User::query()
            ->comAcessoApp(User::APP_MESAS)
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('name')
            ->get(['id', 'name', 'empresa_id'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'empresa_id' => $u->empresa_id,
            ])
            ->all();

        return response()->json(['users' => $users]);
    }
}
