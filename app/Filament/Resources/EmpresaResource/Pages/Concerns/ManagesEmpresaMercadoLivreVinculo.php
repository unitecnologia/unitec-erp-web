<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\MeliHubPair;
use App\Support\Erp\ErpAccess;
use App\Support\MercadoLivre\MeliApiClient;
use App\Support\MercadoLivre\MeliEmpresaConfig;
use App\Support\MercadoLivre\MeliHubService;
use App\Support\MercadoLivre\MeliOAuthService;
use Filament\Notifications\Notification;

trait ManagesEmpresaMercadoLivreVinculo
{
    public ?string $meliHubPairId = null;

    public string $meliHubPairStatus = '';

    public function startMercadoLivreVinculo(): void
    {
        if (! ErpAccess::currentCan('mercado_livre.config') && ! ErpAccess::currentCan('empresa.update')) {
            Notification::make()
                ->title('Mercado Livre')
                ->body('Sem permissão para conectar conta Mercado Livre.')
                ->warning()
                ->send();

            return;
        }

        $empresa = $this->resolveEmpresaRecordForMercadoLivre();

        if (! $empresa) {
            Notification::make()
                ->title('Mercado Livre')
                ->body('Salve a empresa antes de conectar ao Mercado Livre.')
                ->warning()
                ->send();

            return;
        }

        $this->data['param_meli_modo'] = 'hub';
        $this->persistMercadoLivreFields($empresa, ['param_meli_modo' => 'hub']);

        $this->startMercadoLivreHubVinculo($empresa);
    }

    protected function startMercadoLivreHubVinculo(Empresa $empresa): void
    {
        $hub = app(MeliHubService::class);

        if ($hub->isSelfHub($empresa)) {
            $credentials = MeliEmpresaConfig::forEmpresa(MeliEmpresaConfig::hubEmpresa($empresa));

            if (trim((string) ($this->data['param_meli_client_id'] ?? '')) === '') {
                $this->data['param_meli_client_id'] = $credentials['client_id'];
            }
            if (trim((string) ($this->data['param_meli_client_secret'] ?? '')) === '') {
                $this->data['param_meli_client_secret'] = $credentials['client_secret'];
            }

            $this->data['param_meli_redirect_uri'] = MeliEmpresaConfig::redirectUri($empresa);
            if ($this->data['param_meli_redirect_uri'] === '') {
                $this->data['param_meli_redirect_uri'] = rtrim($hub->hubBaseUrl($empresa), '/').'/admin/meli/oauth/callback';
            }

            $this->persistMercadoLivreAppCredentials($empresa);
            $empresa->refresh();

            $userId = (int) auth()->id();
            $result = app(MeliOAuthService::class)->beginAuthorization($empresa, $userId);

            if (! $result['ok'] || ! is_array($result['data'])) {
                Notification::make()
                    ->title('Mercado Livre')
                    ->body($result['message'])
                    ->warning()
                    ->send();

                return;
            }

            $this->redirect((string) $result['data']['authorize_url'], navigate: false);

            return;
        }

        if ($hub->isLocalBrowser()) {
            Notification::make()
                ->title('Mercado Livre')
                ->body('Não dá para autorizar pelo localhost. Abra o ERP em https://unitecnologiasc.com.br (com a atualização publicada) e clique em Conectar.')
                ->warning()
                ->send();

            return;
        }

        $result = $hub->createRemotePair($empresa);

        if (! $result['ok'] || ! is_array($result['data'])) {
            Notification::make()
                ->title('Mercado Livre')
                ->body($result['message'])
                ->warning()
                ->send();

            return;
        }

        $this->meliHubPairId = (string) ($result['data']['uuid'] ?? '');
        $this->meliHubPairStatus = MeliHubPair::STATUS_PENDING;

        $connectUrl = trim((string) ($result['data']['connect_url'] ?? ''));

        if ($this->meliHubPairId === '' || $connectUrl === '') {
            Notification::make()
                ->title('Mercado Livre')
                ->body('Hub Unitec não retornou URL de conexão.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Mercado Livre')
            ->body('Authorize no navegador. Esta tela conclui sozinha após a autorização.')
            ->info()
            ->send();

        $this->js('window.open('.json_encode($connectUrl).', "_blank", "noopener")');
    }

    public function pollMercadoLivreHubPair(): void
    {
        if ($this->meliHubPairId === null || $this->meliHubPairId === '') {
            return;
        }

        if ($this->meliHubPairStatus === MeliHubPair::STATUS_AUTHORIZED) {
            return;
        }

        $empresa = $this->resolveEmpresaRecordForMercadoLivre();

        if (! $empresa) {
            return;
        }

        $result = app(MeliHubService::class)->fetchRemotePairStatus($this->meliHubPairId);

        if (! $result['ok'] || ! is_array($result['data'])) {
            return;
        }

        $status = (string) ($result['data']['status'] ?? '');
        $this->meliHubPairStatus = $status;

        if ($status === MeliHubPair::STATUS_AUTHORIZED) {
            $fields = [
                'param_meli_habilitar' => true,
                'param_meli_modo' => 'hub',
                'param_meli_user_id' => (string) ($result['data']['meli_user_id'] ?? ''),
                'param_meli_nickname' => (string) ($result['data']['nickname'] ?? ''),
                'param_meli_access_token' => (string) ($result['data']['access_token'] ?? ''),
                'param_meli_refresh_token' => (string) ($result['data']['refresh_token'] ?? ''),
                'param_meli_token_expires_at' => filled($result['data']['token_expires_at'] ?? null)
                    ? $result['data']['token_expires_at']
                    : null,
                'param_meli_vinculado_em' => now(),
            ];

            foreach ($fields as $field => $value) {
                $this->data[$field] = $value;
            }

            $this->persistMercadoLivreFields($empresa, $fields);
            $this->meliHubPairId = null;
            $this->meliHubPairStatus = '';

            Notification::make()
                ->title('Mercado Livre')
                ->body('Conta conectada via hub Unitec.')
                ->success()
                ->send();

            return;
        }

        if (in_array($status, [MeliHubPair::STATUS_EXPIRED, MeliHubPair::STATUS_ERROR], true)) {
            $this->meliHubPairId = null;
            Notification::make()
                ->title('Mercado Livre')
                ->body((string) ($result['data']['erro'] ?? 'Falha ou expiração no pareamento. Tente novamente.'))
                ->warning()
                ->send();
        }
    }

    public function desvincularMercadoLivre(): void
    {
        if (! ErpAccess::currentCan('mercado_livre.config') && ! ErpAccess::currentCan('empresa.update')) {
            Notification::make()
                ->title('Mercado Livre')
                ->body('Sem permissão para desvincular conta Mercado Livre.')
                ->warning()
                ->send();

            return;
        }

        $empresa = $this->resolveEmpresaRecordForMercadoLivre();

        if (! $empresa) {
            return;
        }

        $fields = [
            'param_meli_habilitar' => false,
            'param_meli_user_id' => '',
            'param_meli_nickname' => '',
            'param_meli_access_token' => '',
            'param_meli_refresh_token' => '',
            'param_meli_token_expires_at' => null,
            'param_meli_vinculado_em' => null,
        ];

        foreach ($fields as $field => $value) {
            $this->data[$field] = $value;
        }

        $this->persistMercadoLivreFields($empresa, $fields);
        $this->meliHubPairId = null;
        $this->meliHubPairStatus = '';

        Notification::make()
            ->title('Mercado Livre')
            ->body('Conta desvinculada. Conecte novamente para receber pedidos.')
            ->success()
            ->send();
    }

    public function testMercadoLivreConnection(): void
    {
        $empresa = $this->resolveEmpresaRecordForMercadoLivre();

        if (! $empresa) {
            return;
        }

        $empresa->refresh();

        $token = app(MeliApiClient::class)->accessTokenForEmpresa($empresa);

        if ($token === null) {
            Notification::make()
                ->title('Mercado Livre')
                ->body('Conta não conectada ou token inválido.')
                ->warning()
                ->send();

            return;
        }

        $result = app(MeliApiClient::class)->getMe($token);

        if (! $result['ok']) {
            Notification::make()
                ->title('Mercado Livre')
                ->body($result['message'])
                ->warning()
                ->send();

            return;
        }

        $nickname = trim((string) ($result['data']['nickname'] ?? $this->data['param_meli_nickname'] ?? ''));

        Notification::make()
            ->title('Mercado Livre')
            ->body($nickname !== '' ? 'Conexão OK — conta '.$nickname : 'Conexão OK.')
            ->success()
            ->send();
    }

    public function mercadoLivreVinculoResumo(): string
    {
        if (filled($this->data['param_meli_access_token'] ?? null)) {
            $nickname = trim((string) ($this->data['param_meli_nickname'] ?? ''));
            $vinculadoEm = $this->record?->param_meli_vinculado_em;

            if ($nickname !== '' && $vinculadoEm) {
                return 'Conectado como '.$nickname.' em '.$vinculadoEm->format('d/m/Y H:i');
            }

            if ($nickname !== '') {
                return 'Conectado como '.$nickname;
            }

            return 'Conta Mercado Livre conectada.';
        }

        if ($this->meliHubPairId) {
            return 'Aguardando autorização no Mercado Livre…';
        }

        return 'Não conectado — clique em Conectar e autorize a conta do vendedor.';
    }

    public function setMercadoLivreModo(string $modo): void
    {
        $modo = $modo === 'proprio' ? 'proprio' : 'hub';
        $this->data['param_meli_modo'] = $modo;

        $empresa = $this->resolveEmpresaRecordForMercadoLivre();

        if ($empresa) {
            $this->persistMercadoLivreFields($empresa, [
                'param_meli_modo' => $modo,
            ]);
        }
    }

    public function mercadoLivreModoAtual(): string
    {
        $modo = trim((string) ($this->data['param_meli_modo'] ?? ''));

        if ($modo === '') {
            return 'hub';
        }

        return $modo === 'proprio' ? 'proprio' : 'hub';
    }

    protected function notifyMercadoLivreOAuthFlash(): void
    {
        if ($message = session()->pull('meli_oauth_success')) {
            Notification::make()
                ->title('Mercado Livre')
                ->body((string) $message)
                ->success()
                ->send();
        }

        if ($message = session()->pull('meli_oauth_error')) {
            Notification::make()
                ->title('Mercado Livre')
                ->body((string) $message)
                ->warning()
                ->send();
        }
    }

    protected function persistMercadoLivreAppCredentials(Empresa $empresa): void
    {
        $fields = [
            'param_meli_modo' => 'hub',
            'param_meli_client_id' => trim((string) ($this->data['param_meli_client_id'] ?? '')),
            'param_meli_client_secret' => trim((string) ($this->data['param_meli_client_secret'] ?? '')),
            'param_meli_redirect_uri' => trim((string) ($this->data['param_meli_redirect_uri'] ?? '')),
        ];

        foreach ($fields as $field => $value) {
            $this->data[$field] = $value;
        }

        $this->persistMercadoLivreFields($empresa, $fields);
    }

    protected function hydrateMercadoLivreFormDefaults(): void
    {
        $empresa = $this->resolveEmpresaRecordForMercadoLivre();
        $defaults = MeliEmpresaConfig::formDefaults($empresa);

        foreach ($defaults as $field => $value) {
            if (! array_key_exists($field, $this->data) || blank($this->data[$field] ?? null)) {
                $this->data[$field] = $value;
            }
        }

        if (! filled($this->data['param_meli_modo'] ?? null)) {
            $this->data['param_meli_modo'] = 'hub';
        }
    }

    protected function sanitizeMercadoLivreLocalRedirectUri(): void
    {
        $redirectUri = trim((string) ($this->data['param_meli_redirect_uri'] ?? ''));

        if ($redirectUri === '') {
            return;
        }

        $host = strtolower((string) (parse_url($redirectUri, PHP_URL_HOST) ?? ''));

        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            return;
        }

        $empresa = $this->resolveEmpresaRecordForMercadoLivre();
        $replacement = MeliEmpresaConfig::redirectUri($empresa);
        $replacementHost = strtolower((string) (parse_url($replacement, PHP_URL_HOST) ?: ''));

        if ($replacement !== '' && ! in_array($replacementHost, ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->data['param_meli_redirect_uri'] = $replacement;
        } else {
            $this->data['param_meli_redirect_uri'] = '';
        }

        if ($empresa instanceof Empresa && filled($empresa->getKey())) {
            $this->persistMercadoLivreFields($empresa, [
                'param_meli_redirect_uri' => (string) ($this->data['param_meli_redirect_uri'] ?? ''),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function persistMercadoLivreFields(Empresa $empresa, array $fields): void
    {
        Empresa::query()->whereKey($empresa->getKey())->update($fields);
        $this->record?->refresh();
    }

    protected function resolveEmpresaRecordForMercadoLivre(): ?Empresa
    {
        if (! property_exists($this, 'record') || ! $this->record instanceof Empresa) {
            return null;
        }

        if (! filled($this->record->getKey())) {
            return null;
        }

        return $this->record;
    }
}
