<?php

namespace App\Http\Controllers\OAuth;

use App\Filament\Resources\EmpresaResource;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Support\Erp\ErpAccess;
use App\Support\MercadoLivre\MeliOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MeliOAuthCallbackController extends Controller
{
    public function __invoke(Request $request, MeliOAuthService $oauth): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('filament.admin.auth.login');
        }

        if (filled($request->query('error'))) {
            return $this->redirectWithError(
                null,
                (string) $request->query('error_description', 'Autorização recusada no Mercado Livre.'),
            );
        }

        $code = trim((string) $request->query('code', ''));
        $state = trim((string) $request->query('state', ''));

        if ($code === '' || $state === '') {
            return $this->redirectWithError(null, 'Retorno inválido do Mercado Livre.');
        }

        $result = $oauth->completeAuthorization($code, $state, (int) $user->getKey());

        if (! $result['ok'] || ! is_array($result['data'])) {
            return $this->redirectWithError(
                isset($result['data']['empresa_id']) ? (int) $result['data']['empresa_id'] : null,
                $result['message'],
            );
        }

        $empresaId = (int) ($result['data']['empresa_id'] ?? 0);
        $empresa = Empresa::query()->find($empresaId);

        if (! $empresa) {
            return $this->redirectWithError(null, 'Empresa não encontrada para salvar o vínculo.');
        }

        if (! ErpAccess::can($user, 'mercado_livre.config') && ! ErpAccess::can($user, 'empresa.update')) {
            return $this->redirectWithError($empresaId, 'Sem permissão para conectar Mercado Livre nesta empresa.');
        }

        $empresa->update([
            'param_meli_habilitar' => true,
            'param_meli_user_id' => (string) ($result['data']['user_id'] ?? ''),
            'param_meli_nickname' => (string) ($result['data']['nickname'] ?? ''),
            'param_meli_access_token' => (string) ($result['data']['access_token'] ?? ''),
            'param_meli_refresh_token' => (string) ($result['data']['refresh_token'] ?? ''),
            'param_meli_token_expires_at' => $result['data']['expires_at'] ?? null,
            'param_meli_vinculado_em' => now(),
        ]);

        return redirect()->to(
            EmpresaResource::getUrl('edit', [
                'record' => $empresa,
                'tab' => 'parametros',
                'subtab' => 'mercado_livre',
            ])
        )->with('meli_oauth_success', $result['message']);
    }

    private function redirectWithError(?int $empresaId, string $message): RedirectResponse
    {
        if ($empresaId) {
            return redirect()->to(
                EmpresaResource::getUrl('edit', [
                    'record' => $empresaId,
                    'tab' => 'parametros',
                    'subtab' => 'mercado_livre',
                ])
            )->with('meli_oauth_error', $message);
        }

        return redirect()
            ->route('filament.admin.resources.empresas.index')
            ->with('meli_oauth_error', $message);
    }
}
