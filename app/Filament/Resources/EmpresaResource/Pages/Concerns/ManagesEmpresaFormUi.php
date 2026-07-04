<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Support\ContadorCloud\ContadorCloudClient;
use App\Support\ContadorCloud\ContadorCloudConfig;
use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\WhatsApp\WhatsAppClient;
use App\Support\Erp\WhatsApp\WhatsAppConfig;
use App\Support\Erp\WhatsApp\WhatsAppGatewayManager;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use Filament\Notifications\Notification;

trait ManagesEmpresaFormUi
{
    public string $activeFormTab = 'dados';

    public string $activeParametrosSubTab = 'permissoes';

    public ?string $whatsAppQr = null;

    public function setActiveFormTab(string $tab): void
    {
        $allowed = ['dados', 'parametros', 'obs_fisco', 'obs_carne', 'obs_nfce', 'msg_cobranca'];

        if (! in_array($tab, $allowed, true)) {
            return;
        }

        $this->activeFormTab = $tab;
    }

    public function setActiveParametrosSubTab(string $tab): void
    {
        $allowed = array_keys(\App\Support\Erp\EmpresaParametros::parametrosSubTabs());

        if (! in_array($tab, $allowed, true)) {
            return;
        }

        $this->activeParametrosSubTab = $tab;
    }

    public function modulePending(string $module): void
    {
        Notification::make()
            ->title($module)
            ->body('Em implementação.')
            ->info()
            ->send();
    }

    public function cycleTriStatePermission(string $field): void
    {
        $fields = EmpresaParametros::permissionFields();

        if (! array_key_exists($field, $fields) || ($fields[$field]['tri'] ?? false) !== true) {
            return;
        }

        $value = $this->data[$field] ?? '';

        if ($value === '' || $value === null) {
            $this->data[$field] = '1';
        } elseif ($value === '1' || $value === true || $value === 1) {
            $this->data[$field] = '0';
        } else {
            $this->data[$field] = '';
        }
    }

    public function testPortalContadorConnection(): void
    {
        $config = ContadorCloudConfig::fromFormData($this->data ?? []);
        $result = app(ContadorCloudClient::class)->testConnection($config);

        $notification = Notification::make()
            ->title('Portal do Contador')
            ->body($result['message']);

        if ($result['ok']) {
            $notification->success()->send();

            return;
        }

        $notification->warning()->send();
    }

    public function testWhatsAppConnection(): void
    {
        $empresa = $this->resolveEmpresaRecordForWhatsApp();

        if (! $empresa) {
            Notification::make()
                ->title('WhatsApp')
                ->body('Salve a empresa antes de testar a conexão.')
                ->warning()
                ->send();

            return;
        }

        $this->persistWhatsAppFormFields($empresa);

        $config = WhatsAppConfig::fromEmpresa($empresa->fresh());
        $result = app(WhatsAppClient::class)->testConnection($config, $empresa->fresh());

        $notification = Notification::make()
            ->title('WhatsApp')
            ->body($result['message']);

        if ($result['ok']) {
            $notification->success()->send();

            return;
        }

        $notification->warning()->send();
    }

    public function refreshWhatsAppStatus(): void
    {
        if ($this->activeParametrosSubTab !== 'whatsapp') {
            return;
        }

        $empresa = $this->resolveEmpresaRecordForWhatsApp();

        if (! $empresa) {
            return;
        }

        $result = app(WhatsAppClient::class)->fetchSessionStatus($empresa->fresh(), lightweight: true);
        $this->applyWhatsAppGatewayResult($empresa, $result, notify: false, persist: true);
    }

    public function startWhatsAppConnection(): void
    {
        $empresa = $this->resolveEmpresaRecordForWhatsApp();

        if (! $empresa) {
            Notification::make()
                ->title('WhatsApp')
                ->body('Salve a empresa antes de conectar.')
                ->warning()
                ->send();

            return;
        }

        $this->persistWhatsAppFormFields($empresa);

        $result = app(WhatsAppClient::class)->startSession($empresa->fresh());
        $this->applyWhatsAppGatewayResult($empresa, $result);
    }

    public function disconnectWhatsApp(): void
    {
        $empresa = $this->resolveEmpresaRecordForWhatsApp();

        if (! $empresa) {
            return;
        }

        $result = app(WhatsAppClient::class)->disconnectSession($empresa->fresh());

        if (! $result['ok']) {
            Notification::make()
                ->title('WhatsApp')
                ->body($result['message'])
                ->warning()
                ->send();

            return;
        }

        $this->whatsAppQr = null;
        $this->syncWhatsAppFieldsOnRecord($empresa, [
            'param_whatsapp_status' => WhatsAppConfig::STATUS_DESCONECTADO,
            'param_whatsapp_numero' => '',
        ], persist: true);

        Notification::make()
            ->title('WhatsApp')
            ->body($result['message'])
            ->success()
            ->send();
    }

    /**
     * @param  array{ok: bool, message: string, status?: string, number?: string, qr?: string|null}  $result
     */
    protected function applyWhatsAppGatewayResult(Empresa $empresa, array $result, bool $notify = true, bool $persist = false): void
    {
        if (! $result['ok']) {
            if ($notify) {
                Notification::make()
                    ->title('WhatsApp')
                    ->body($result['message'])
                    ->warning()
                    ->send();
            }

            return;
        }

        $fields = [];

        if (isset($result['status'])) {
            $fields['param_whatsapp_status'] = WhatsAppConfig::normalizeStatus($result['status']);
        }

        if (isset($result['number'])) {
            $fields['param_whatsapp_numero'] = WhatsAppPhone::normalize((string) $result['number'])
                ?? preg_replace('/\D/', '', (string) $result['number']);
        }

        if (($result['status'] ?? null) === WhatsAppConfig::STATUS_DESCONECTADO) {
            $fields['param_whatsapp_numero'] = '';
        }

        if ($fields !== []) {
            $this->syncWhatsAppFieldsOnRecord($empresa, $fields, persist: $persist);
        }

        $this->whatsAppQr = $result['qr'] ?? null;

        if (($result['status'] ?? null) === WhatsAppConfig::STATUS_CONECTADO) {
            $this->whatsAppQr = null;
        }

        if ($notify) {
            Notification::make()
                ->title('WhatsApp')
                ->body($result['message'])
                ->success()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function syncWhatsAppFieldsOnRecord(Empresa $empresa, array $fields, bool $persist = true): void
    {
        foreach ($fields as $field => $value) {
            $this->data[$field] = $value;
        }

        if (! $persist) {
            return;
        }

        Empresa::query()->whereKey($empresa->getKey())->update($fields);
        $this->record?->refresh();
    }

    protected function persistWhatsAppFormFields(Empresa $empresa): void
    {
        app(WhatsAppGatewayManager::class)->writeRuntimeConfig($empresa);

        $empresa->forceFill([
            'param_whatsapp_habilitar' => (bool) ($this->data['param_whatsapp_habilitar'] ?? false),
            'param_whatsapp_gateway_port' => max(1024, (int) ($this->data['param_whatsapp_gateway_port'] ?? 8091)),
            'param_whatsapp_timeout' => max(1, (int) ($this->data['param_whatsapp_timeout'] ?? 30)),
            'param_whatsapp_limite_dia' => max(1, (int) ($this->data['param_whatsapp_limite_dia'] ?? 100)),
            'param_whatsapp_enviar_orcamento' => (bool) ($this->data['param_whatsapp_enviar_orcamento'] ?? true),
            'param_whatsapp_enviar_cobranca' => (bool) ($this->data['param_whatsapp_enviar_cobranca'] ?? true),
            'param_whatsapp_enviar_nfe' => (bool) ($this->data['param_whatsapp_enviar_nfe'] ?? false),
        ])->save();

        app(WhatsAppGatewayManager::class)->resolveOrCreateKey($empresa->fresh());
    }

    protected function resolveEmpresaRecordForWhatsApp(): ?Empresa
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
