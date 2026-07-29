<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Models\Empresa;
use App\Support\ContadorCloud\ContadorCloudHttpHelper;
use App\Support\ContadorCloud\ContadorCloudPairingClient;
use Filament\Notifications\Notification;

trait ManagesEmpresaPortalContadorVinculo
{
    public bool $portalContadorVinculoModalOpen = false;

    public string $portalContadorVinculoStatus = '';

    public string $portalContadorVinculoId = '';

    public string $portalContadorVinculoCodigo = '';

    public string $portalContadorVinculoAuthorizeUrl = '';

    public string $portalContadorVinculoMessage = '';

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

        $portalBaseUrl = ContadorCloudHttpHelper::resolvePortalBaseUrl(
            (string) ($this->data['param_portal_contador_url'] ?? ''),
        );

        $result = app(ContadorCloudPairingClient::class)->solicitarVinculo($empresa, $portalBaseUrl);

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
        $this->portalContadorVinculoCodigo = (string) ($data['codigo'] ?? '');
        $this->portalContadorVinculoAuthorizeUrl = (string) ($data['authorizeUrl'] ?? '');
        $this->portalContadorVinculoStatus = 'pending';
        $this->portalContadorVinculoMessage = 'Aguardando o contador autorizar no portal.';
        $this->portalContadorVinculoModalOpen = true;

        $this->data['param_portal_contador_vinculo_id'] = $this->portalContadorVinculoId;
        $this->persistPortalContadorFields($empresa, [
            'param_portal_contador_vinculo_id' => $this->portalContadorVinculoId,
        ]);
    }

    public function pollPortalContadorVinculo(): void
    {
        if (! $this->portalContadorVinculoModalOpen || $this->portalContadorVinculoStatus !== 'pending') {
            return;
        }

        if ($this->portalContadorVinculoId === '') {
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
            $this->portalContadorVinculoMessage = $result['message'];

            return;
        }

        if (! $result['ok'] || ! is_array($result['data'])) {
            return;
        }

        $status = (string) ($result['data']['status'] ?? 'pending');
        $this->portalContadorVinculoStatus = $status;

        if ($status === 'authorized') {
            $this->applyPortalContadorCredenciais($empresa, $result['data']);
            $this->portalContadorVinculoMessage = 'Vínculo autorizado com sucesso.';

            Notification::make()
                ->title('Portal do Contador')
                ->body('Empresa conectada ao portal. O envio de documentos foi habilitado.')
                ->success()
                ->send();

            return;
        }

        if ($status === 'rejected') {
            $this->portalContadorVinculoMessage = 'O contador recusou a solicitação de vínculo.';

            return;
        }

        if ($status === 'expired') {
            $this->portalContadorVinculoMessage = 'A solicitação expirou. Clique em Conectar novamente.';

            return;
        }

        $this->portalContadorVinculoMessage = 'Aguardando o contador autorizar no portal.';
    }

    public function closePortalContadorVinculoModal(): void
    {
        $this->portalContadorVinculoModalOpen = false;
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

        return 'Não conectado — use o botão abaixo para autorizar no portal.';
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

        $fields = [
            'param_portal_contador_habilitar' => true,
            'param_portal_contador_token' => trim((string) ($credenciais['token'] ?? '')),
            'param_portal_contador_empresa_id' => trim((string) ($credenciais['empresaId'] ?? '')),
            'param_portal_contador_url' => filled($credenciais['apiUrl'] ?? null)
                ? ContadorCloudHttpHelper::normalizeUrl((string) $credenciais['apiUrl'])
                : ContadorCloudHttpHelper::resolveSyncUrl($portalBaseUrl),
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
     * @param  array<string, mixed>  $fields
     */
    protected function persistPortalContadorFields(Empresa $empresa, array $fields): void
    {
        Empresa::query()->whereKey($empresa->getKey())->update($fields);
        $this->record?->refresh();
    }
}
