<?php

namespace App\Filament\Resources\OrcamentoResource\Pages\Concerns;

use App\Models\Orcamento;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Orcamento\OrcamentoReportService;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesOrcamentoEmailModal
{
    use WithFileUploads;

    public bool $emailModalOpen = false;

    public ?int $emailOrcamentoId = null;

    public string $emailTo = '';

    public string $whatsAppTo = '';

    public string $emailSubject = '';

    public string $emailMessage = '';

    public ?string $emailSelectedAttachmentId = null;

    /** @var list<array{id: string, name: string, path: string, display: string}> */
    public array $emailAttachments = [];

    public ?TemporaryUploadedFile $emailExtraUpload = null;

    public function openEmailModal(): void
    {
        if (! $this->highlightedRecordIdOrNotify('enviar')) {
            return;
        }

        $orcamento = Orcamento::query()
            ->with('cliente')
            ->find($this->highlightedRecordId);

        if (! $orcamento) {
            Notification::make()
                ->title('Orçamento não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->cleanupEmailAttachments();

        $report = app(OrcamentoReportService::class);
        $orcamento = $report->loadOrcamento($orcamento);
        $numero = $report->formatNumero($orcamento->numero);
        $pdf = $report->storePdfAttachment($orcamento);
        $cliente = $orcamento->cliente;
        $phoneRaw = $cliente?->celular1 ?: ($cliente?->whatsapp ?: '');

        $this->emailOrcamentoId = $orcamento->id;
        $this->emailTo = trim((string) ($cliente?->email ?? ''));
        $this->whatsAppTo = WhatsAppPhone::formatDisplay($phoneRaw);
        $this->emailSubject = $report->defaultEmailSubject($numero);
        $this->emailMessage = $report->defaultEmailMessage($numero);
        $this->emailAttachments = [[
            'id' => 'orcamento-pdf',
            'name' => $pdf['name'],
            'path' => $pdf['path'],
            'display' => $pdf['display'],
        ]];
        $this->emailSelectedAttachmentId = 'orcamento-pdf';
        $this->emailExtraUpload = null;
        $this->emailModalOpen = true;

        $this->dispatch('erp-orc-focus-envio-modal');
        $this->dispatch('erp-masks-refresh');
    }

    public function closeEmailModal(): void
    {
        $this->emailModalOpen = false;
        $this->emailOrcamentoId = null;
        $this->emailTo = '';
        $this->whatsAppTo = '';
        $this->emailSubject = '';
        $this->emailMessage = '';
        $this->emailExtraUpload = null;
        $this->emailSelectedAttachmentId = null;
        $this->cleanupEmailAttachments();
    }

    public function updatedEmailMessage(string $value): void
    {
        $clean = WhatsAppMessageHelper::stripSystemFooter($value);

        if ($clean !== $value) {
            $this->emailMessage = $clean;
        }
    }

    public function selectEmailAttachment(string $attachmentId): void
    {
        $this->emailSelectedAttachmentId = $attachmentId;
    }

    public function removeSelectedEmailAttachment(): void
    {
        if (blank($this->emailSelectedAttachmentId)) {
            return;
        }

        $this->removeEmailAttachment($this->emailSelectedAttachmentId);
        $this->emailSelectedAttachmentId = $this->emailAttachments[0]['id'] ?? null;
    }

    public function updatedEmailExtraUpload(): void
    {
        if (! $this->emailExtraUpload instanceof TemporaryUploadedFile) {
            return;
        }

        $storedPath = $this->emailExtraUpload->store('temp/email-attachments', 'local');
        $fullPath = storage_path('app/'.$storedPath);

        $this->emailAttachments[] = [
            'id' => uniqid('extra-', true),
            'name' => $this->emailExtraUpload->getClientOriginalName(),
            'path' => $fullPath,
            'display' => $this->emailExtraUpload->getClientOriginalName(),
        ];

        $this->emailExtraUpload = null;
    }

    public function removeEmailAttachment(string $attachmentId): void
    {
        $remaining = [];

        foreach ($this->emailAttachments as $attachment) {
            if ($attachment['id'] === $attachmentId) {
                if (is_file($attachment['path'])) {
                    @unlink($attachment['path']);
                }

                continue;
            }

            $remaining[] = $attachment;
        }

        $this->emailAttachments = $remaining;

        if ($this->emailSelectedAttachmentId === $attachmentId) {
            $this->emailSelectedAttachmentId = $this->emailAttachments[0]['id'] ?? null;
        }
    }

    #[On('send-orcamento-email')]
    public function sendOrcamentoEmail(): void
    {
        $this->validate([
            'emailTo' => ['required', 'email'],
            'emailSubject' => ['required', 'string', 'max:255'],
            'emailMessage' => ['required', 'string', 'max:5000'],
        ], [
            'emailTo.required' => 'Informe o e-mail do destinatário.',
            'emailTo.email' => 'Informe um e-mail válido.',
            'emailSubject.required' => 'Informe o assunto.',
            'emailMessage.required' => 'Informe a mensagem.',
        ]);

        if ($this->emailAttachments === []) {
            Notification::make()
                ->title('Inclua ao menos um anexo.')
                ->warning()
                ->send();

            return;
        }

        $empresa = app(OrcamentoReportService::class)->resolveEmpresa();

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
                to: $this->emailTo,
                messageBody: $this->emailMessage,
                subjectLine: $this->emailSubject,
                fileAttachments: collect($this->emailAttachments)
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

        Notification::make()
            ->title('E-mail enviado.')
            ->body('A tela permanece aberta para enviar também por WhatsApp, se quiser.')
            ->success()
            ->send();
    }

    public function sendOrcamentoWhatsApp(): void
    {
        $this->emailMessage = WhatsAppMessageHelper::stripSystemFooter($this->emailMessage);
        $maxLength = WhatsAppMessageHelper::maxUserMessageLength();

        $this->validate([
            'whatsAppTo' => ['required', 'string', 'max:30', new CelularBrasileiroValido()],
            'emailMessage' => ['required', 'string', 'max:'.$maxLength],
        ], [
            'whatsAppTo.required' => 'Informe o WhatsApp do destinatário.',
            'emailMessage.required' => 'Informe a mensagem.',
        ]);

        $attachment = $this->emailAttachments[0] ?? null;
        $pdfPath = is_array($attachment) ? ($attachment['path'] ?? null) : null;
        $pdfName = is_array($attachment) ? (string) ($attachment['name'] ?? 'ORCAMENTO.PDF') : 'ORCAMENTO.PDF';

        if (! is_string($pdfPath) || ! is_file($pdfPath)) {
            Notification::make()
                ->title('PDF do orçamento não encontrado.')
                ->body('Feche e abra novamente o envio (F9).')
                ->warning()
                ->send();

            return;
        }

        $empresa = app(OrcamentoReportService::class)->resolveEmpresa();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada.')
                ->warning()
                ->send();

            return;
        }

        $sender = app(WhatsAppSender::class);

        try {
            $result = $sender->sendDocumentMessage(
                empresa: $empresa,
                tipo: WhatsAppSender::TIPO_ORCAMENTO,
                number: $this->whatsAppTo,
                text: $this->emailMessage,
                documentPath: $pdfPath,
                documentName: $pdfName !== '' ? $pdfName : 'ORCAMENTO.PDF',
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

        Notification::make()
            ->title('WhatsApp enviado.')
            ->body('A tela permanece aberta para enviar também por e-mail, se quiser.')
            ->success()
            ->send();
    }

    protected function cleanupEmailAttachments(): void
    {
        foreach ($this->emailAttachments as $attachment) {
            if (isset($attachment['path']) && is_file($attachment['path'])) {
                @unlink($attachment['path']);
            }
        }

        $this->emailAttachments = [];
    }
}
