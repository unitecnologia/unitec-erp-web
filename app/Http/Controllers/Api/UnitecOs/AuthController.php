<?php

namespace App\Http\Controllers\Api\UnitecOs;

use App\Models\UnitecOsDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Login Unitec OS — Sanctum + senha_app_forca_vendas + aparelho aprovado.
 * Empresa é obrigatória após autenticação (nunca emite token sem empresa_id).
 */
class AuthController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'empresa_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'usuario' => ['nullable', 'string', 'max:120'],
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
        } else {
            $login = trim((string) ($data['usuario'] ?? $data['login'] ?? ''));
            if ($login === '') {
                throw ValidationException::withMessages([
                    'usuario' => 'Informe o usuário.',
                ]);
            }
            $query->where(function ($q) use ($login): void {
                $q->where('name', mb_strtoupper($login, 'UTF-8'))
                    ->orWhere('name', $login);
            });
        }

        $user = $query->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'usuario' => 'Usuário não encontrado ou inativo.',
            ]);
        }

        $empresaInformada = ! empty($data['empresa_id']) ? (int) $data['empresa_id'] : 0;
        $empresaUsuario = (int) ($user->empresa_id ?? 0);

        if ($empresaUsuario <= 0) {
            throw ValidationException::withMessages([
                'empresa_id' => 'Usuário sem empresa padrão cadastrada. Login bloqueado.',
            ]);
        }

        if ($empresaInformada > 0 && $empresaInformada !== $empresaUsuario) {
            $nomeEmpresa = \App\Models\Empresa::query()->whereKey($empresaUsuario)->value('fantasia')
                ?: \App\Models\Empresa::query()->whereKey($empresaUsuario)->value('nome')
                ?: ('#'.$empresaUsuario);

            throw ValidationException::withMessages([
                'empresa_id' => 'Empresa incorreta para este usuário. Use a empresa padrão: '.$nomeEmpresa,
            ]);
        }

        if (blank($user->senha_app_forca_vendas)) {
            throw ValidationException::withMessages([
                'senha' => 'Usuário sem senha do app. Cadastre em Permissões → Cadastro → Senha do app.',
            ]);
        }

        if (! hash_equals((string) $user->senha_app_forca_vendas, (string) $data['senha'])) {
            throw ValidationException::withMessages([
                'senha' => 'Senha do app inválida (não é a senha do ERP).',
            ]);
        }

        $empresaId = $empresaUsuario;

        $tokenName = 'unitec-os:'.$data['device_uuid'];

        DB::transaction(function () use ($user, $tokenName): void {
            $user->tokens()->where('name', $tokenName)->delete();
        });

        $token = $user->createToken($tokenName, ['unitec-os']);

        UnitecOsDevice::query()->updateOrCreate(
            ['device_uuid' => $data['device_uuid']],
            [
                'user_id' => $user->id,
                'empresa_id' => $empresaId,
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

        if (! $user->empresa_id) {
            return response()->json([
                'message' => 'Usuário sem empresa. Sessão inválida.',
                'code' => 'empresa_required',
            ], 403);
        }

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null) {
            UnitecOsDevice::query()
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
        $user->loadMissing('vendedor');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'empresa_id' => (int) $user->empresa_id,
            'vendedor_id' => $user->vendedor_id,
            'tecnico' => $user->vendedor?->nome ?? $user->name,
        ];
    }
}
