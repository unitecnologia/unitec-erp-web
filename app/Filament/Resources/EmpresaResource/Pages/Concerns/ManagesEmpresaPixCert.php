<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ManagesEmpresaPixCert
{
    /** @var mixed */
    public $pixPfxUpload = null;

    public function updatedPixPfxUpload(): void
    {
        $this->validate([
            'pixPfxUpload' => 'nullable|file|max:8192',
        ]);

        if (! $this->pixPfxUpload) {
            return;
        }

        $ext = strtolower((string) $this->pixPfxUpload->getClientOriginalExtension());
        if (! in_array($ext, ['pfx', 'p12'], true)) {
            Notification::make()
                ->title('Envie um certificado .pfx ou .p12.')
                ->danger()
                ->send();
            $this->pixPfxUpload = null;

            return;
        }

        $previous = trim((string) ($this->data['param_pix_certificado'] ?? ''));
        if ($previous !== '' && ! str_contains($previous, '/') && ! str_contains($previous, '\\')) {
            // ignora base64 antigo
        } elseif ($previous !== '' && Storage::disk('local')->exists($previous)) {
            Storage::disk('local')->delete($previous);
        }

        $empresaId = property_exists($this, 'record') && $this->record
            ? (int) $this->record->getKey()
            : 0;
        $name = 'empresa-'.($empresaId > 0 ? $empresaId : 'nova').'-'.Str::lower(Str::random(8)).'.p12';
        $storedPath = $this->pixPfxUpload->storeAs('ailos', $name, 'local');

        $this->data['param_pix_certificado'] = $storedPath;
        $this->pixPfxUpload = null;

        Notification::make()
            ->title('Certificado PFX carregado. Salve (F5) para gravar na empresa.')
            ->success()
            ->send();
    }

    public function clearEmpresaPixCertificado(): void
    {
        $previous = trim((string) ($this->data['param_pix_certificado'] ?? ''));
        if ($previous !== '' && Storage::disk('local')->exists($previous)) {
            Storage::disk('local')->delete($previous);
        }

        $this->data['param_pix_certificado'] = '';
        $this->pixPfxUpload = null;

        Notification::make()
            ->title('Certificado PFX removido. Salve (F5) para confirmar.')
            ->success()
            ->send();
    }

    public function updatedDataParamPixProvedor(?string $value): void
    {
        if ($value !== 'ailos') {
            return;
        }

        if (blank($this->data['param_pix_ambiente'] ?? null) || ($this->data['param_pix_ambiente'] ?? '') === 'homologacao') {
            $this->data['param_pix_ambiente'] = 'producao';
        }

        if (blank($this->data['param_pix_webhook_url'] ?? null)) {
            $this->data['param_pix_webhook_url'] = \App\Support\Erp\EmpresaParametros::pixAilosWebhookUrl();
        }
    }
}
