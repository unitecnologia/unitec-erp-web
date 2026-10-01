<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Models\Contador;
use App\Models\Empresa;
use App\Support\ContadorCloud\ContadorCloudClient;
use App\Support\ContadorCloud\ContadorCloudConfig;
use App\Support\ContadorCloud\ContadorCloudHttpHelper;
use App\Support\ContadorCloud\ContadorCloudPairingClient;
use App\Support\Erp\EmpresaParametros;
use Filament\Notifications\Notification;

trait ManagesEmpresaPortalContadorVinculo
{
    public string $portalContadorVinculoStatus = '';

    public string $portalContadorVinculoId = '';

    public string $portalContadorVinculoAuthorizeUrl = '';

    public function startPortalContadorVinculo(): void
    {
        $empresa = $this->resolveEmpresaRecordForPortalContador();

        if (! $empresa) {
            Notification::make()
                ->title('Portal do Contador')
                ->body('Salve a empresa antes de conectar ao portal.')
                ->warning()
                ->send();

            return;
        }

        if (blank($empresa->cnpj)) {
            Notification::make()
                ->title('Portal do Contador')
                ->body('Preencha o CNPJ da empresa na aba Dados Básico antes de conectar.')
                ->warning()
                ->send();

            return;
        }

        $portalBaseUrl = $this->ensurePortalContadorUrlPadrao($empresa);
        $client = app(ContadorCloudPairingClient::class);
        $contador = $this->resolveContadorVinculadoParaPortal();

        // Preferência: vínculo automático (CNPJ do contador) — token na hora.
        if ($contador instanceof Contador) {
            if (blank($contador->cnpj_cpf)) {
                Notification::make()
                    ->title('Portal do Contador')
                    ->body('O contador selecionado não tem CNPJ/CPF no cadastro. Preencha o documento ou use o vínculo manual.')
                    ->warning()
                    ->send();
            } else {
                $auto = $client->vincularAutomatico($empresa, $contador, $portalBaseUrl);

                if ($auto['ok'] && is_array($auto['data'])) {
                    $this->portalContadorVinculoId = (string) (
                        $auto['data']['vinculoId']
                        ?? $auto['data']['credenciais']['vinculoId']
                        ?? ''
                    );
                    $this->portalContadorVinculoAuthorizeUrl = '';
                    $this->portalContadorVinculoStatus = 'authorized';

                    // Garante que o contador escolhido no form já esteja gravado na empresa.
                    $this->persistPortalContadorFields($empresa, [
                        'param_portal_contador_contador_id' => (int) $contador->getKey(),
                    ]);
                    $this->data['param_portal_contador_contador_id'] = (int) $contador->getKey();

                    $this->applyPortalContadorCredenciais($empresa, $auto['data']);

                    $probe = app(ContadorCloudClient::class)
                        ->testConnection(ContadorCloudConfig::fromFormData($this->data ?? []));

                    if ($probe['ok']) {
                        Notification::make()
                            ->title('Portal do Contador')
                            ->body('Conectado automaticamente. Token validado — envio liberado.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Portal do Contador')
                            ->body('Credenciais gravadas, mas o token foi rejeitado: '.$probe['message'])
                            ->warning()
                            ->send();
                    }

                    return;
                }

                $autoCode = (string) ($auto['code'] ?? '');

                // Auto existe mas falhou (segredo, contador, payload): não abrir tela manual.
                if ($autoCode !== 'auto_indisponivel') {
                    Notification::make()
                        ->title('Portal do Contador')
                        ->body($auto['message'])
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Portal do Contador')
                    ->body('Vínculo automático indisponível no portal. Abrindo autorização manual…')
                    ->warning()
                    ->send();
            }
        }

        $result = $client->solicitarVinculo($empresa, $portalBaseUrl);

        if (! $result['ok'] || ! is_array($result['data'])) {
            Notification::make()
                ->title('Portal do Contador')
                ->body($result['message'])
                ->warning()
                ->send();

            return;
        }

        $data = $result['data'];
        $this->portalContadorVinculoId = (string) ($data['vinculoId'] ?? '');
        $this->portalContadorVinculoAuthorizeUrl = (string) ($data['authorizeUrl'] ?? '');
        $this->portalContadorVinculoStatus = 'pending';

        // Novo vínculo: limpa credenciais antigas para não parecer "Conectado" com token morto.
        $fields = [
            'param_portal_contador_vinculo_id' => $this->portalContadorVinculoId,
            'param_portal_contador_token' => '',
            'param_portal_contador_empresa_id' => '',
            'param_portal_contador_contador_nome_portal' => '',
            'param_portal_contador_vinculado_em' => null,
            'param_portal_contador_habilitar' => true,
        ];

        foreach ($fields as $field => $value) {
            $this->data[$field] = $value;
        }

        $this->persistPortalContadorFields($empresa, $fields);

        if ($this->portalContadorVinculoAuthorizeUrl !== '') {
            $this->js('window.open('.json_encode($this->portalContadorVinculoAuthorizeUrl).', "_blank", "noopener")');
        }

        Notification::make()
            ->title('Portal do Contador')
            ->body(
                $contador instanceof Contador
                    ? 'Vínculo automático indisponível. Portal aberto — aguarde o contador autorizar.'
                    : 'Selecione o Contador vinculado acima para auto-conexão. Portal aberto — aguarde autorização.'
            )
            ->success()
            ->send();
    }

    protected function resolveContadorVinculadoParaPortal(): ?Contador
    {
        $id = (int) ($this->data['param_portal_contador_contador_id'] ?? 0);

        if ($id <= 0) {
            return null;
        }

        return Contador::query()->find($id);
    }

    public function pollPortalContadorVinculo(): void
    {
        if ($this->portalContadorVinculoStatus !== 'pending' || $this->portalContadorVinculoId === '') {
            return;
        }

        $empresa = $this->resolveEmpresaRecordForPortalContador();

        if (! $empresa) {
            return;
        }

        $portalBaseUrl = ContadorCloudHttpHelper::resolvePortalBaseUrl(
            (string) ($this->data['param_portal_contador_url'] ?? ''),
        );

        $result = app(ContadorCloudPairingClient::class)->consultarStatus(
            $this->portalContadorVinculoId,
            $portalBaseUrl,
        );

        if (! $result['ok'] && ($result['data']['status'] ?? null) === 'expired') {
            $this->portalContadorVinculoStatus = 'expired';

            Notification::make()
                ->title('Portal do Contador')
                ->body($result['message'])
                ->warning()
                ->send();

            return;
        }

        if (! $result['ok'] || ! is_array($result['data'])) {
            return;
        }

        $status = (string) ($result['data']['status'] ?? 'pending');
        $this->portalContadorVinculoStatus = $status;

        if ($status === 'authorized') {
            $this->applyPortalContadorCredenciais($empresa, $result['data']);

            $token = trim((string) ($this->data['param_portal_contador_token'] ?? ''));
            if ($token === '') {
                $this->portalContadorVinculoStatus = 'pending';

                Notification::make()
                    ->title('Portal do Contador')
                    ->body('Portal autorizou, mas não devolveu token. Peça ao contador para autorizar de novo ou use token manual no avançado.')
                    ->warning()
                    ->send();

                return;
            }

            $probe = app(\App\Support\ContadorCloud\ContadorCloudClient::class)
                ->testConnection(\App\Support\ContadorCloud\ContadorCloudConfig::fromFormData($this->data ?? []));

            $this->portalContadorVinculoAuthorizeUrl = '';

            if (! $probe['ok']) {
                Notification::make()
                    ->title('Portal do Contador')
                    ->body('Vínculo gravado, mas o token foi rejeitado pela API: '.$probe['message'])
                    ->warning()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Portal do Contador')
                ->body('Empresa conectada ao portal. O envio de documentos foi habilitado.')
                ->success()
                ->send();

            return;
        }

        if ($status === 'rejected') {
            Notification::make()
                ->title('Portal do Contador')
                ->body('O contador recusou a solicitação de vínculo.')
                ->warning()
                ->send();

            return;
        }

        if ($status === 'expired') {
            Notification::make()
                ->title('Portal do Contador')
                ->body('A solicitação expirou. Clique em Conectar novamente.')
                ->warning()
                ->send();
        }
    }

    public function desvincularPortalContador(): void
    {
        $empresa = $this->resolveEmpresaRecordForPortalContador();

        if (! $empresa) {
            return;
        }

        $fields = [
            'param_portal_contador_habilitar' => false,
            'param_portal_contador_token' => '',
            'param_portal_contador_empresa_id' => '',
            'param_portal_contador_vinculo_id' => '',
            'param_portal_contador_contador_nome_portal' => '',
            'param_portal_contador_vinculado_em' => null,
        ];

        foreach ($fields as $field => $value) {
            $this->data[$field] = $value;
        }

        $this->portalContadorVinculoStatus = '';
        $this->portalContadorVinculoId = '';
        $this->portalContadorVinculoAuthorizeUrl = '';

        $this->persistPortalContadorFields($empresa, $fields);

        Notification::make()
            ->title('Portal do Contador')
            ->body('Vínculo removido neste ERP. Gere um novo vínculo para voltar a enviar documentos.')
            ->success()
            ->send();
    }

    public function portalContadorVinculoResumo(): string
    {
        if (filled($this->data['param_portal_contador_token'] ?? null)) {
            $contador = trim((string) ($this->data['param_portal_contador_contador_nome_portal'] ?? ''));
            $vinculadoEm = $this->record?->param_portal_contador_vinculado_em;

            if ($contador !== '' && $vinculadoEm) {
                return 'Conectado ao portal com '.$contador.' em '.$vinculadoEm->format('d/m/Y H:i');
            }

            if ($contador !== '') {
                return 'Conectado ao portal com '.$contador;
            }

            return 'Conectado ao portal do contador.';
        }

        if ($this->portalContadorVinculoStatus === 'pending') {
            return 'Aguardando autorização do contador no portal…';
        }

        return 'Não conectado — use o botão abaixo para conectar.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function applyPortalContadorCredenciais(Empresa $empresa, array $payload): void
    {
        $credenciais = is_array($payload['credenciais'] ?? null) ? $payload['credenciais'] : [];
        $contador = is_array($credenciais['contador'] ?? null) ? $credenciais['contador'] : [];
        $portalBaseUrl = ContadorCloudHttpHelper::resolvePortalBaseUrl(
            (string) ($credenciais['apiUrl'] ?? $this->data['param_portal_contador_url'] ?? ''),
        );

        if ($portalBaseUrl === '') {
            $portalBaseUrl = EmpresaParametros::defaultPortalContadorUrl();
        }

        $fields = [
            'param_portal_contador_habilitar' => true,
            'param_portal_contador_token' => trim((string) ($credenciais['token'] ?? '')),
            'param_portal_contador_empresa_id' => trim((string) ($credenciais['empresaId'] ?? '')),
            'param_portal_contador_url' => $portalBaseUrl,
            'param_portal_contador_vinculo_id' => $this->portalContadorVinculoId,
            'param_portal_contador_contador_nome_portal' => trim((string) ($contador['nome'] ?? '')),
            'param_portal_contador_vinculado_em' => now(),
        ];

        if (filled($contador['email'] ?? null)) {
            $fields['param_portal_contador_email'] = trim((string) $contador['email']);
        }

        foreach ($fields as $field => $value) {
            $this->data[$field] = $value;
        }

        $this->persistPortalContadorFields($empresa, $fields);
    }

    /**
     * Garante URL base do portal no form/DB antes do vínculo (evita Conectar sem URL).
     */
    protected function ensurePortalContadorUrlPadrao(Empresa $empresa): string
    {
        $current = trim((string) ($this->data['param_portal_contador_url'] ?? ''));

        if ($current === '') {
            $portalBaseUrl = EmpresaParametros::defaultPortalContadorUrl();
            $this->data['param_portal_contador_url'] = $portalBaseUrl;
            $this->persistPortalContadorFields($empresa, [
                'param_portal_contador_url' => $portalBaseUrl,
            ]);

            return $portalBaseUrl;
        }

        return ContadorCloudHttpHelper::resolvePortalBaseUrl($current);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function persistPortalContadorFields(Empresa $empresa, array $fields): void
    {
        Empresa::query()->whereKey($empresa->getKey())->update($fields);
        $this->record?->refresh();
    }
}
