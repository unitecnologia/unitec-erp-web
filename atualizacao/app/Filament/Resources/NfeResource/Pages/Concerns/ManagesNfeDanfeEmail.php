<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeEvento;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Nfe\NfeDanfeReportService;
use App\Support\Erp\Nfe\NfeEventoLogger;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;

trait ManagesNfeDanfeEmail
{
    public bool $nfeDanfeEmailModalOpen = false;

    public ?int $nfeDanfeEmailNfeId = null;

    public string $nfeDanfeEmailTo = '';

    public string $nfeDanfeWhatsAppTo = '';

    public string $nfeDanfeEmailSubject = '';

    public string $nfeDanfeEmailMessage = '';

    /** @var list<array{id: string, name: string, path: string, display: string}> */
    public array $nfeDanfeEmailAttachments = [];

    public function openNfeDanfeEmailModal(): void
    {
        if (! $this->nfeFiscalSucessoNfeId) {
            $this->showNfeFiscalOverlayInfo('Enviar nota', 'NF-e não encontrada para envio.');

            return;
        }

        $this->prepareNfeDanfeEmailModal((int) $this->nfeFiscalSucessoNfeId, true);
    }

    public function openNfeDanfeEmailFromList(): void
    {
        $nfeId = $this->resolveNfeDanfeEmailTargetId();

        if (! $nfeId) {
            return;
        }

        $this->prepareNfeDanfeEmailModal(
            $nfeId,
            $this->nfeModalOpen && filled($this->nfeFiscalSucessoDetalhe ?? null),
        );
    }

    protected function resolveNfeDanfeEmailTargetId(): ?int
    {
        if ($this->nfeFiscalSucessoNfeId) {
            return (int) $this->nfeFiscalSucessoNfeId;
        }

        if ($this->nfeModalOpen && $this->nfeModalRecordId) {
            return (int) $this->nfeModalRecordId;
        }

        return $this->highlightedRecordIdOrNotify('enviar');
    }

    protected function prepareNfeDanfeEmailModal(int $nfeId, bool $useFiscalOverlayErrors = false): void
    {
        $nfe = Nfe::query()->with('cliente')->find($nfeId);

        if (! $nfe) {
            $this->notifyNfeDanfeEmailError('NF-e não encontrada para envio.', $useFiscalOverlayErrors);

            return;
        }

        if ($nfe->status !== Nfe::STATUS_TRANSMITIDA) {
            $this->notifyNfeDanfeEmailError('Somente NF-e transmitida pode ser enviada.', $useFiscalOverlayErrors);

            return;
        }

        $empresa = Empresa::query()->find($nfe->empresa_id);

        if (! $empresa) {
            $this->notifyNfeDanfeEmailError('Empresa não identificada.', $useFiscalOverlayErrors);

            return;
        }

        $this->cleanupNfeDanfeEmailAttachments();

        try {
            $report = app(NfeDanfeReportService::class);
            $attachments = $report->buildDispatchAttachments($nfe, $empresa);
        } catch (\Throwable $exception) {
            report($exception);

            $this->notifyNfeDanfeEmailError('Não foi possível gerar os anexos da NF-e para envio.', $useFiscalOverlayErrors);

            return;
        }

        $cliente = $nfe->cliente;
        $clienteNome = trim((string) ($cliente?->nome_razao ?? $cliente?->nome ?? ''));
        $phoneRaw = $cliente?->celular1 ?: ($cliente?->whatsapp ?: ($cliente?->fone1 ?: ''));

        $this->nfeDanfeEmailNfeId = (int) $nfe->id;
        $this->nfeDanfeEmailTo = trim((string) ($cliente?->email ?? ''));
        $this->nfeDanfeWhatsAppTo = WhatsAppPhone::formatDisplay($phoneRaw);
        $this->nfeDanfeEmailSubject = $report->defaultEmailSubject($nfe);
        $this->nfeDanfeEmailMessage = $report->defaultEmailMessage($nfe, $clienteNome);
        $this->nfeDanfeEmailAttachments = $attachments;
        $this->nfeDanfeEmailModalOpen = true;

        $this->dispatch('erp-nfe-focus-danfe-email-modal');
    }

    protected function notifyNfeDanfeEmailError(string $message, bool $useFiscalOverlayErrors): void
    {
        if ($useFiscalOverlayErrors) {
            $this->showNfeFiscalOverlayInfo('Enviar nota', $message);

            return;
        }

        Notification::make()
            ->title('Enviar nota')
            ->body($message)
            ->warning()
            ->send();
    }

    public function updatedNfeDanfeEmailMessage(string $value): void
    {
        $clean = WhatsAppMessageHelper::stripSystemFooter($value);

        if ($clean !== $value) {
            $this->nfeDanfeEmailMessage = $clean;
        }
    }

    public function sendNfeDanfeEmail(): void
    {
        $this->validate([
            'nfeDanfeEmailTo' => ['required', 'email'],
            'nfeDanfeEmailSubject' => ['required', 'string', 'max:255'],
            'nfeDanfeEmailMessage' => ['required', 'string', 'max:5000'],
        ], [
            'nfeDanfeEmailTo.required' => 'Informe o e-mail do destinatário.',
            'nfeDanfeEmailTo.email' => 'Informe um e-mail válido.',
            'nfeDanfeEmailSubject.required' => 'Informe o assunto.',
            'nfeDanfeEmailMessage.required' => 'Informe a mensagem.',
        ]);

        if ($this->nfeDanfeEmailAttachments === []) {
            Notification::make()
                ->title('Anexos da NF-e não encontrados.')
                ->warning()
                ->send();

            return;
        }

        $nfe = Nfe::query()->find($this->nfeDanfeEmailNfeId);

        if (! $nfe) {
            Notification::make()
                ->title('NF-e não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $empresa = Empresa::query()->find($nfe->empresa_id);

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada.')
                ->warning()
                ->send();

            return;
        }

        try {
            FiscalMailService::sendForEmpresa(
                empresaId: (int) $empresa->id,
                to: $this->nfeDanfeEmailTo,
                messageBody: $this->nfeDanfeEmailMessage,
                subjectLine: $this->nfeDanfeEmailSubject,
                fileAttachments: collect($this->nfeDanfeEmailAttachments)
                    ->map(fn (array $attachment): array => [
                        'path' => $attachment['path'],
                        'name' => $attachment['name'],
                    ])
                    ->all(),
                fromAddress: $empresa->email ?: null,
                fromName: $empresa->nome ?: null,
            );
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível enviar o e-mail.')
                ->body('Verifique a configuração de e-mail em Empresa → Parâmetros → E-mail.')
                ->danger()
                ->send();

            return;
        }

        NfeEventoLogger::registrar(
            nfeId: (int) $nfe->id,
            tipo: NfeEvento::TIPO_EMAIL,
            titulo: 'NF-e enviada por e-mail',
            descricao: $this->nfeDanfeEmailSubject,
            destinatario: $this->nfeDanfeEmailTo,
            metadata: [
                'contexto' => 'danfe',
                'anexos' => count($this->nfeDanfeEmailAttachments),
            ],
        );

        Notification::make()
            ->title('E-mail enviado.')
            ->body('A tela permanece aberta para enviar também por WhatsApp, se quiser.')
            ->success()
            ->send();
    }

    public function sendNfeDanfeWhatsApp(): void
    {
        $this->nfeDanfeEmailMessage = WhatsAppMessageHelper::stripSystemFooter($this->nfeDanfeEmailMessage);
        $maxLength = WhatsAppMessageHelper::maxUserMessageLength();

        $this->validate([
            'nfeDanfeWhatsAppTo' => ['required', 'string', 'max:30', new CelularBrasileiroValido()],
            'nfeDanfeEmailMessage' => ['required', 'string', 'max:'.$maxLength],
        ], [
            'nfeDanfeWhatsAppTo.required' => 'Informe o WhatsApp do destinatário.',
            'nfeDanfeEmailMessage.required' => 'Informe a mensagem.',
        ]);

        $documents = $this->buildNfeDanfeWhatsAppDocuments();

        if ($documents === []) {
            Notification::make()
                ->title('Anexos da NF-e não encontrados.')
                ->body('Feche e abra novamente o envio da nota.')
                ->warning()
                ->send();

            return;
        }

        $nfe = Nfe::query()->find($this->nfeDanfeEmailNfeId);

        if (! $nfe) {
            Notification::make()
                ->title('NF-e não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $empresa = Empresa::query()->find($nfe->empresa_id);

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada.')
                ->warning()
                ->send();

            return;
        }

        $sender = app(WhatsAppSender::class);

        try {
            $result = $sender->sendDocumentMessages(
                empresa: $empresa,
                tipo: WhatsAppSender::TIPO_NFE,
                number: $this->nfeDanfeWhatsAppTo,
                text: $this->nfeDanfeEmailMessage,
                documents: $documents,
            );
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível enviar o WhatsApp.')
                ->body('Verifique a conexão em Empresa → Parâmetros → WhatsApp.')
                ->danger()
                ->send();

            return;
        }

        if (! $result['ok']) {
            Notification::make()
                ->title('Não foi possível enviar o WhatsApp.')
                ->body($result['message'])
                ->warning()
                ->send();

            return;
        }

        NfeEventoLogger::registrar(
            nfeId: (int) $nfe->id,
            tipo: NfeEvento::TIPO_WHATSAPP,
            titulo: 'NF-e enviada por WhatsApp',
            descricao: 'DANFE e XML enviados ao destinatário.',
            destinatario: WhatsAppPhone::formatDisplay($this->nfeDanfeWhatsAppTo) ?? $this->nfeDanfeWhatsAppTo,
            metadata: [
                'contexto' => 'danfe',
                'destinatario_tipo' => 'cliente',
                'anexos' => collect($this->nfeDanfeEmailAttachments)
                    ->pluck('name')
                    ->filter()
                    ->values()
                    ->all(),
            ],
        );

        Notification::make()
            ->title('WhatsApp enviado.')
            ->body('A tela permanece aberta para enviar também por e-mail, se quiser.')
            ->success()
            ->send();
    }

    public function closeNfeDanfeEmailModal(): void
    {
        $this->nfeDanfeEmailModalOpen = false;
        $this->nfeDanfeEmailNfeId = null;
        $this->nfeDanfeEmailTo = '';
        $this->nfeDanfeWhatsAppTo = '';
        $this->nfeDanfeEmailSubject = '';
        $this->nfeDanfeEmailMessage = '';
        $this->cleanupNfeDanfeEmailAttachments();
    }

    protected function cleanupNfeDanfeEmailAttachments(): void
    {
        foreach ($this->nfeDanfeEmailAttachments as $attachment) {
            $path = $attachment['path'] ?? null;

            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }

        $this->nfeDanfeEmailAttachments = [];
    }

    /**
     * @return list<array{path: string, name: string, mimetype: string, caption?: string}>
     */
    protected function buildNfeDanfeWhatsAppDocuments(): array
    {
        $documents = [];

        foreach ($this->nfeDanfeEmailAttachments as $attachment) {
            $path = $attachment['path'] ?? null;

            if (! is_string($path) || ! is_file($path)) {
                continue;
            }

            $name = (string) ($attachment['name'] ?? 'documento');
            $isXml = ($attachment['id'] ?? '') === 'xml' || str_ends_with(strtolower($name), '.xml');

            $documents[] = [
                'path' => $path,
                'name' => $name !== '' ? $name : ($isXml ? 'NFE.xml' : 'DANFE-NFE.PDF'),
                'mimetype' => $isXml ? 'application/xml' : 'application/pdf',
                'caption' => $isXml ? 'XML da NF-e' : null,
            ];
        }

        return $documents;
    }
}
