<?php

namespace App\Filament\Resources\NfceResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Nfce\NfceCupomReportService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Throwable;

trait ManagesNfceClienteEmail
{
    public bool $nfceClienteEmailModalOpen = false;

    public ?int $nfceClienteEmailNfceId = null;

    public string $nfceClienteEmailTo = '';

    public string $nfceClienteEmailSubject = '';

    public string $nfceClienteEmailMessage = '';

    /** @var list<array{id: string, name: string, path: string, display: string}> */
    public array $nfceClienteEmailAttachments = [];

    public ?string $nfceClienteEmailSelectedAttachmentId = null;

    public function openNfceClienteEmailModal(): void
    {
        $id = $this->highlightedRecordIdOrNotify('email');

        if (! $id) {
            return;
        }

        $nfce = PdvVendaNfce::query()->with(['pdvVenda.person', 'pdvVenda.nfce'])->find($id);
        $venda = $nfce?->pdvVenda;
        $empresa = $this->currentNfceEmpresaForEmail();

        if (! $nfce || ! $venda) {
            Notification::make()
                ->title('NFC-e sem venda vinculada.')
                ->warning()
                ->send();

            return;
        }

        $service = app(NfceCupomReportService::class);
        $this->cleanupNfceClienteEmailAttachments();

        try {
            $pdf = $service->storePdfAttachment($venda, $empresa);
            $attachments = [[
                'id' => 'cupom-pdf',
                'name' => $pdf['name'],
                'path' => $pdf['path'],
                'display' => $pdf['display'],
            ]];

            $xml = $service->storeXmlAttachment($nfce);

            if ($xml) {
                $attachments[] = [
                    'id' => 'xml',
                    'name' => $xml['name'],
                    'path' => $xml['path'],
                    'display' => $xml['display'],
                ];
            }

            $this->nfceClienteEmailNfceId = (int) $nfce->id;
            $this->nfceClienteEmailTo = $service->resolveClienteEmail($venda);
            $this->nfceClienteEmailSubject = $service->defaultEmailSubject($nfce, $venda, $empresa);
            $this->nfceClienteEmailMessage = $service->defaultEmailMessage($nfce, $venda, $empresa);
            $this->nfceClienteEmailAttachments = $attachments;
            $this->nfceClienteEmailSelectedAttachmentId = $attachments[0]['id'] ?? null;
            $this->nfceClienteEmailModalOpen = true;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível preparar o e-mail da NFC-e.')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function closeNfceClienteEmailModal(): void
    {
        $this->nfceClienteEmailModalOpen = false;
        $this->nfceClienteEmailNfceId = null;
        $this->nfceClienteEmailTo = '';
        $this->nfceClienteEmailSubject = '';
        $this->nfceClienteEmailMessage = '';
        $this->nfceClienteEmailSelectedAttachmentId = null;
        $this->cleanupNfceClienteEmailAttachments();
    }

    public function selectNfceClienteEmailAttachment(string $attachmentId): void
    {
        $this->nfceClienteEmailSelectedAttachmentId = $attachmentId;
    }

    public function sendNfceClienteEmail(): void
    {
        $this->validate([
            'nfceClienteEmailTo' => ['required', 'email'],
            'nfceClienteEmailSubject' => ['required', 'string', 'max:255'],
            'nfceClienteEmailMessage' => ['required', 'string', 'max:5000'],
        ], [
            'nfceClienteEmailTo.required' => 'Informe o e-mail do cliente.',
            'nfceClienteEmailTo.email' => 'Informe um e-mail válido.',
            'nfceClienteEmailSubject.required' => 'Informe o assunto.',
            'nfceClienteEmailMessage.required' => 'Informe a mensagem.',
        ]);

        if ($this->nfceClienteEmailAttachments === []) {
            Notification::make()
                ->title('Nenhum anexo encontrado para envio.')
                ->warning()
                ->send();

            return;
        }

        $empresa = $this->currentNfceEmpresaForEmail();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        try {
            FiscalMailService::sendForEmpresa(
                empresaId: (int) $empresa->id,
                to: $this->nfceClienteEmailTo,
                messageBody: $this->nfceClienteEmailMessage,
                subjectLine: $this->nfceClienteEmailSubject,
                fileAttachments: collect($this->nfceClienteEmailAttachments)
                    ->map(fn (array $attachment): array => [
                        'path' => $attachment['path'],
                        'name' => $attachment['name'],
                    ])
                    ->all(),
                fromAddress: $empresa->email ?: null,
                fromName: $empresa->nome ?: null,
            );

            Notification::make()
                ->title('NFC-e enviada por e-mail.')
                ->body($this->nfceClienteEmailTo)
                ->success()
                ->send();

            $this->closeNfceClienteEmailModal();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível enviar o e-mail.')
                ->body('Verifique a configuração de e-mail em Empresa → Parâmetros → E-mail.')
                ->danger()
                ->send();
        }
    }

    protected function cleanupNfceClienteEmailAttachments(): void
    {
        foreach ($this->nfceClienteEmailAttachments as $attachment) {
            if (is_file($attachment['path'] ?? '')) {
                @unlink($attachment['path']);
            }
        }

        $this->nfceClienteEmailAttachments = [];
    }

    protected function currentNfceEmpresaForEmail(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }
}
