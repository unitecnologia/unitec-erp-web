<?php

namespace App\Http\Controllers\Api\Mesas;

use App\Models\Empresa;
use App\Models\MesasDevice;
use App\Models\User;
use App\Support\Erp\Pdv\PdvMesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Login do garçom — Sanctum + senha do app (users.senha_app_forca_vendas) + aparelho aprovado.
 */
class AuthController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'empresa_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'usuario' => ['nullable', 'string', 'max:120'],
            'senha' => ['required', 'string', 'max:60'],
            'device_uuid' => ['required', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        if ((string) $request->header('X-MES-Device', '') !== $data['device_uuid']) {
            throw ValidationException::withMessages(['device_uuid' => 'Aparelho inválido.']);
        }

        if (! Schema::hasColumn('users', 'acesso_app_mesas')) {
            throw ValidationException::withMessages([
                'usuario' => 'Servidor desatualizado. Reinicie o ERP para atualizar o banco.',
            ]);
        }

        $query = User::query()->where('ativo', true);

        if (! empty($data['user_id'])) {
            $query->whereKey($data['user_id']);
        } else {
            $login = trim((string) ($data['usuario'] ?? ''));
            if ($login === '') {
                throw ValidationException::withMessages(['usuario' => 'Informe o usuário.']);
            }
            $query->where(function ($q) use ($login): void {
                $q->where('name', mb_strtoupper($login, 'UTF-8'))
                    ->orWhere('name', $login);
            });
        }

        $user = $query->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages(['usuario' => 'Usuário não encontrado ou inativo.']);
        }

        $empresaUsuario = (int) ($user->empresa_id ?? 0);
        $empresaInformada = ! empty($data['empresa_id']) ? (int) $data['empresa_id'] : 0;

        if ($empresaUsuario <= 0) {
            throw ValidationException::withMessages([
                'empresa_id' => 'Usuário sem empresa padrão cadastrada. Login bloqueado.',
            ]);
        }

        if ($empresaInformada > 0 && $empresaInformada !== $empresaUsuario) {
            throw ValidationException::withMessages([
                'empresa_id' => 'Empresa incorreta para este usuário. Use a empresa padrão: '.$this->nomeEmpresa(Empresa::query()->find($empresaUsuario)),
            ]);
        }

        if (blank($user->senha_app_forca_vendas)) {
            throw ValidationException::withMessages([
                'senha' => 'Usuário sem senha do app. Cadastre em Permissões → Cadastro → Senha do app.',
            ]);
        }

        if (! $user->podeAcessarApp(User::APP_MESAS)) {
            throw ValidationException::withMessages(['senha' => 'Usuário sem acesso ao Unitec Mesas.']);
        }

        if (! hash_equals((string) $user->senha_app_forca_vendas, (string) $data['senha'])) {
            throw ValidationException::withMessages(['senha' => 'Senha do app inválida (não é a senha do ERP).']);
        }

        $tokenName = 'mesas:'.$data['device_uuid'];

        DB::transaction(function () use ($user, $tokenName): void {
            $user->tokens()->where('name', $tokenName)->delete();
        });

        $token = $user->createToken($tokenName, ['mesas']);

        MesasDevice::query()->updateOrCreate(
            ['device_uuid' => $data['device_uuid']],
            [
                'user_id' => $user->id,
                'empresa_id' => $empresaUsuario,
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
        ] + $this->sessaoPayload($user));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->sessaoPayload($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null && method_exists($token, 'delete')) {
            MesasDevice::query()
                ->where('current_token_id', $token->getKey())
                ->update(['current_token_id' => null]);

            $token->delete();
        }

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessaoPayload(User $user): array
    {
        $empresa = Empresa::query()->find((int) $user->empresa_id);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'empresa_id' => (int) $user->empresa_id,
            ],
            'empresa' => [
                'id' => (int) ($empresa?->id ?? 0),
                'nome' => $this->nomeEmpresa($empresa),
                'qtd_mesas' => PdvMesaService::quantidade($empresa),
            ],
        ];
    }

    private function nomeEmpresa(?Empresa $empresa): string
    {
        if ($empresa === null) {
            return '';
        }

        return (string) ($empresa->fantasia ?: ($empresa->nome ?: $empresa->razao_social));
    }
}
