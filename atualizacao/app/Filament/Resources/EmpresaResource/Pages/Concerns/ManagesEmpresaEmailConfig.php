<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\VendasParametro;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use Filament\Notifications\Notification;

trait ManagesEmpresaEmailConfig
{
    /** @var array<string, mixed> */
    public array $emailForm = [];

    public string $emailTestTo = '';

    protected function loadEmpresaEmailConfig(): void
    {
        $empresa = $this->resolveEmpresaRecordForEmailConfig();

        if (! $empresa) {
            $this->emailForm = $this->defaultEmpresaEmailForm();
            $this->emailTestTo = '';

            return;
        }

        $params = VendasParametro::forEmpresa((int) $empresa->id);
        $form = NfeFiscalConfig::toFormArray($params);

        $this->emailForm = [
            'email_modo' => (string) ($form['email_modo'] ?? FiscalMailService::MODO_SMTP),
            'email_host' => (string) ($form['email_host'] ?? ''),
            'email_porta' => (string) ($form['email_porta'] ?? ''),
            'email_user' => (string) ($form['email_user'] ?? ''),
            'email_senha' => (string) ($form['email_senha'] ?? ''),
            'email_assunto' => (string) ($form['email_assunto'] ?? ''),
            'email_ssl' => (bool) ($form['email_ssl'] ?? false),
            'email_tls' => (bool) ($form['email_tls'] ?? false),
            'email_api_provedor' => (string) ($form['email_api_provedor'] ?? FiscalMailService::API_BREVO),
            'email_api_key' => (string) ($form['email_api_key'] ?? ''),
        ];

        $this->emailTestTo = (string) ($empresa->email ?? '');
    }

    public function saveEmpresaEmailConfig(): void
    {
        $empresa = $this->resolveEmpresaRecordForEmailConfig();

        if (! $empresa) {
            Notification::make()
                ->title('E-mail')
                ->body('Salve a empresa antes de gravar as configurações de e-mail.')
                ->warning()
                ->send();

            return;
        }

        $params = VendasParametro::forEmpresa((int) $empresa->id);

        $payload = [
            'email_host' => trim((string) ($this->emailForm['email_host'] ?? '')) ?: null,
            'email_porta' => trim((string) ($this->emailForm['email_porta'] ?? '')) ?: null,
            'email_user' => trim((string) ($this->emailForm['email_user'] ?? '')) ?: null,
            'email_assunto' => trim((string) ($this->emailForm['email_assunto'] ?? '')) ?: null,
            'email_ssl' => ! empty($this->emailForm['email_ssl']) ? 'S' : 'N',
            'email_tls' => ! empty($this->emailForm['email_tls']) ? 'S' : 'N',
            'email_modo' => FiscalMailService::normalizeModo((string) ($this->emailForm['email_modo'] ?? FiscalMailService::MODO_SMTP)),
            'email_api_provedor' => FiscalMailService::normalizeApiProvider(
                (string) ($this->emailForm['email_api_provedor'] ?? FiscalMailService::API_BREVO),
            ),
        ];

        if (filled($this->emailForm['email_senha'] ?? '')) {
            $payload['email_senha'] = (string) $this->emailForm['email_senha'];
        }

        if (filled($this->emailForm['email_api_key'] ?? '')) {
            $payload['email_api_key'] = (string) $this->emailForm['email_api_key'];
        }

        $params->update($payload);
        $this->loadEmpresaEmailConfig();

        Notification::make()
            ->title('E-mail')
            ->body('Configurações de e-mail gravadas.')
            ->success()
            ->send();
    }

    public function testEmpresaEmail(): void
    {
        $empresa = $this->resolveEmpresaRecordForEmailConfig();

        if (! $empresa) {
            Notification::make()
                ->title('E-mail')
                ->body('Salve a empresa antes de testar o envio.')
                ->warning()
                ->send();

            return;
        }

        $params = VendasParametro::forEmpresa((int) $empresa->id);

        $result = FiscalMailService::testEmail(
            $this->emailForm,
            $params,
            $this->emailTestTo,
            $empresa,
        );

        $notification = Notification::make()->title($result['message']);

        if ($result['ok']) {
            $notification->success()->send();
        } else {
            $notification->danger()->send();
        }
    }

    protected function persistEmpresaEmailConfigQuietly(): void
    {
        $empresa = $this->resolveEmpresaRecordForEmailConfig();

        if (! $empresa || $this->emailForm === []) {
            return;
        }

        // Só persiste se o usuário mexeu em algum campo de e-mail (evita UPDATE em todo Gravar).
        $hasMeaningfulEmailConfig = filled($this->emailForm['email_host'] ?? null)
            || filled($this->emailForm['email_user'] ?? null)
            || filled($this->emailForm['email_senha'] ?? null)
            || filled($this->emailForm['email_api_key'] ?? null)
            || filled($this->emailForm['email_assunto'] ?? null)
            || filled($this->emailForm['email_porta'] ?? null);

        if (! $hasMeaningfulEmailConfig) {
            return;
        }

        $params = VendasParametro::forEmpresa((int) $empresa->id);

        $payload = [
            'email_host' => trim((string) ($this->emailForm['email_host'] ?? '')) ?: null,
            'email_porta' => trim((string) ($this->emailForm['email_porta'] ?? '')) ?: null,
            'email_user' => trim((string) ($this->emailForm['email_user'] ?? '')) ?: null,
            'email_assunto' => trim((string) ($this->emailForm['email_assunto'] ?? '')) ?: null,
            'email_ssl' => ! empty($this->emailForm['email_ssl']) ? 'S' : 'N',
            'email_tls' => ! empty($this->emailForm['email_tls']) ? 'S' : 'N',
            'email_modo' => FiscalMailService::normalizeModo((string) ($this->emailForm['email_modo'] ?? FiscalMailService::MODO_SMTP)),
            'email_api_provedor' => FiscalMailService::normalizeApiProvider(
                (string) ($this->emailForm['email_api_provedor'] ?? FiscalMailService::API_BREVO),
            ),
        ];

        if (filled($this->emailForm['email_senha'] ?? '')) {
            $payload['email_senha'] = (string) $this->emailForm['email_senha'];
        }

        if (filled($this->emailForm['email_api_key'] ?? '')) {
            $payload['email_api_key'] = (string) $this->emailForm['email_api_key'];
        }

        $params->update($payload);
    }

    protected function resolveEmpresaRecordForEmailConfig(): ?Empresa
    {
        if (property_exists($this, 'record') && $this->record instanceof Empresa) {
            return $this->record;
        }

        return $this->resolveEmpresaRecordForWhatsApp();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultEmpresaEmailForm(): array
    {
        return [
            'email_modo' => FiscalMailService::MODO_SMTP,
            'email_host' => '',
            'email_porta' => '',
            'email_user' => '',
            'email_senha' => '',
            'email_assunto' => '',
            'email_ssl' => false,
            'email_tls' => false,
            'email_api_provedor' => FiscalMailService::API_BREVO,
            'email_api_key' => '',
        ];
    }
}
