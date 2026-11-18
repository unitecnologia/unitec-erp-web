<?php

namespace App\Http\Controllers\Api\VendasInternas;

use App\Models\CaixaConta;
use App\Models\User;
use App\Models\VendasInternasDevice;
use App\Support\Erp\EmpresaParametros;
use App\Support\VendasInternas\VendasInternasDeviceVinculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function __construct(private readonly VendasInternasDeviceVinculo $vinculo)
    {
    }

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

        if (! $user->podeAcessarApp(User::APP_VENDAS_INTERNAS)) {
            throw ValidationException::withMessages([
                'senha' => 'Usuário sem acesso a este app.',
            ]);
        }

        if (! hash_equals((string) $user->senha_app_forca_vendas, (string) $data['senha'])) {
            throw ValidationException::withMessages([
                'senha' => 'Usuário ou senha do app inválidos.',
            ]);
        }

        $device = $request->attributes->get('vi_device');
        $headerUuid = (string) $request->header('X-VI-Device', '');

        if (! $device instanceof VendasInternasDevice || ! hash_equals($headerUuid, (string) $data['device_uuid'])) {
            return response()->json([
                'message' => 'Aparelho não identificado.',
                'code' => 'device_required',
            ], 403);
        }

        // Vínculo fixo: aparelho livre grava este usuário; vinculado aceita só ele.
        $recusa = $this->vinculo->vincularOuRecusar($device, $user);

        if ($recusa !== null) {
            return response()->json([
                'message' => VendasInternasDeviceVinculo::mensagem($recusa),
                'code' => $recusa,
            ], 403);
        }

        $tokenName = 'vi:'.$data['device_uuid'];

        DB::transaction(function () use ($user, $tokenName): void {
            $user->tokens()->where('name', $tokenName)->delete();
        });

        $token = $user->createToken($tokenName, ['vendas-internas']);

        $device->forceFill([
            'empresa_id' => $user->empresa_id,
            'device_name' => $data['device_name'] ?? $device->device_name,
            'platform' => $data['platform'] ?? $device->platform,
            'app_version' => $data['app_version'] ?? $device->app_version,
            'current_token_id' => $token->accessToken->getKey(),
            'last_seen_at' => now(),
            'revoked_at' => null,
        ])->save();

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
            'device' => [
                'vinculo_user_id' => (int) $device->user_id,
            ],
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
            // Encerra só a sessão (token). O aparelho continua autorizado e vinculado.
            VendasInternasDevice::query()
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
        $user->loadMissing(['vendedor.usuario']);

        $vendedor = $user->vendedor;
        // Mesma regra da Força de Vendas: Permissões → Caixas (empresa);
        // sem vínculo → CAIXA GERAL.
        $empresaId = $user->empresa_id ? (int) $user->empresa_id : null;
        $caixa = $vendedor?->caixaContaDaEmpresa($empresaId)
            ?? CaixaConta::ensureCaixaGeral();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'empresa_id' => $user->empresa_id,
            'vendedor_id' => $user->vendedor_id,
            'vendedor_nome' => $vendedor?->nome,
            'caixa_id' => $caixa->id,
            'caixa_nome' => $caixa->nome,
            'is_admin' => (bool) $user->is_admin,
            'permissions' => $user->effectivePermissionKeys(),
            'vendas_internas' => EmpresaParametros::vendasInternasAppConfig($empresaId),
        ];
    }
}
