<?php

namespace App\Http\Controllers\Api\ForcaVendas;

use App\Models\ForcaVendasDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'empresa_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'login' => ['nullable', 'string', 'max:120'],
            'senha' => ['required', 'string', 'max:60'],
            'device_uuid' => ['required', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        $query = User::query()->where('ativo', true);

        if (! empty($data['user_id'])) {
            $query->whereKey($data['user_id']);
        } elseif (! empty($data['login'])) {
            $login = trim($data['login']);
            $query->where(function ($q) use ($login): void {
                $q->where('name', mb_strtoupper($login, 'UTF-8'));
            });
        } else {
            throw ValidationException::withMessages([
                'login' => 'Informe o usuário.',
            ]);
        }

        if (! empty($data['empresa_id'])) {
            $query->where('empresa_id', $data['empresa_id']);
        }

        $user = $query->first();

        if (! $user instanceof User || blank($user->senha_app_forca_vendas)) {
            throw ValidationException::withMessages([
                'senha' => 'Usuário ou senha do app inválidos.',
            ]);
        }

        if (! hash_equals((string) $user->senha_app_forca_vendas, (string) $data['senha'])) {
            throw ValidationException::withMessages([
                'senha' => 'Usuário ou senha do app inválidos.',
            ]);
        }

        $tokenName = 'fv:'.$data['device_uuid'];

        DB::transaction(function () use ($user, $tokenName): void {
            $user->tokens()->where('name', $tokenName)->delete();
        });

        $token = $user->createToken($tokenName, ['forca-vendas']);

        ForcaVendasDevice::query()->updateOrCreate(
            ['device_uuid' => $data['device_uuid']],
            [
                'user_id' => $user->id,
                'empresa_id' => $user->empresa_id,
                'device_name' => $data['device_name'] ?? null,
                'platform' => $data['platform'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'current_token_id' => $token->accessToken->getKey(),
                'last_seen_at' => now(),
                'revoked_at' => null,
            ]
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null) {
            // Encerra só a sessão (token). O aparelho continua autorizado —
            // revogação é ação do admin em Força de Vendas → Aparelhos.
            ForcaVendasDevice::query()
                ->where('current_token_id', $token->getKey())
                ->update(['current_token_id' => null]);

            $token->delete();
        }

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing(['vendedor.estoqueCadastro', 'vendedor.empresas', 'vendedor.tabelaVenda']);

        $vendedor = $user->vendedor;
        $caixa = $vendedor?->caixaContaDaEmpresa($user->empresa_id ? (int) $user->empresa_id : null);
        $estoque = $vendedor?->estoqueCadastro;
        $tabela = $vendedor?->tabelaVenda;
        $empresa = $user->empresa_id
            ? \App\Models\Empresa::query()->find($user->empresa_id)
            : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'empresa_id' => $user->empresa_id,
            'vendedor_id' => $user->vendedor_id,
            'vendedor_nome' => $vendedor?->nome,
            'caixa_id' => $caixa?->id,
            'caixa_nome' => $caixa?->nome,
            'estoque_id' => $estoque?->id ?? $vendedor?->estoque_id,
            'estoque_nome' => $estoque?->nome
                ?? (filled($vendedor?->estoque) ? (string) $vendedor->estoque : null),
            'tabela_venda_id' => $tabela?->id ?? $vendedor?->tabela_venda_id,
            'tabela_venda_codigo' => $tabela?->codigo,
            'tabela_venda_descricao' => $tabela?->descricao,
            'pix_api_habilitada' => (bool) ($empresa?->param_pix_habilitar ?? false),
            'is_admin' => (bool) $user->is_admin,
            'is_supervisor' => (bool) $user->is_supervisor,
            'permissions' => $user->effectivePermissionKeys(),
        ];
    }
}
