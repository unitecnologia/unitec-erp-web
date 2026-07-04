<?php

namespace App\Filament\Resources\OrcamentoResource\Pages\Concerns;

use App\Mail\OrcamentoEmail;
use App\Models\Orcamento;
use App\Support\Erp\Orcamento\OrcamentoReportService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesOrcamentoEmailModal
{
    use WithFileUploads;

    public bool $emailModalOpen = false;

    public ?int $emailOrcamentoId = null;

    public string $emailTo = '';

    public string $emailSubject = '';

    public string $emailMessage = '';

    public ?string $emailSelectedAttachmentId = null;

    /** @var list<array{id: string, name: string, path: string, display: string}> */
    public array $emailAttachments = [];

    public ?TemporaryUploadedFile $emailExtraUpload = null;

    public function openEmailModal(): void
    {
        if (! $this->highlightedRecordIdOrNotify('email')) {
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
        $numero = $report->formatNumero($orcamento->numero);
        $pdf = $report->storePdfAttachment($orcamento);

        $this->emailOrcamentoId = $orcamento->id;
        $this->emailTo = trim((string) ($orcamento->cliente?->email ?? ''));
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
    }

    public function closeEmailModal(): void
    {
        $this->emailModalOpen = false;
        $this->emailOrcamentoId = null;
        $this->emailTo = '';
        $this->emailSubject = '';
        $this->emailMessage = '';
        $this->emailExtraUpload = null;
        $this->emailSelectedAttachmentId = null;
        $this->cleanupEmailAttachments();
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
        $fullPath = storage_path('app/' . $storedPath);

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

        try {
            Mail::to($this->emailTo)->send(new OrcamentoEmail(
                messageBody: $this->emailMessage,
                subjectLine: $this->emailSubject,
                fileAttachments: collect($this->emailAttachments)
                    ->map(fn (array $attachment): array => [
                        'path' => $attachment['path'],
                        'name' => $attachment['name'],
                    ])
                    ->all(),
                fromAddress: $empresa?->email ?: config('mail.from.address'),
                fromName: $empresa?->nome ?: config('mail.from.name'),
            ));
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível enviar o e-mail.')
                ->body('Verifique a configuração de e-mail do servidor.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('E-mail enviado.')
            ->success()
            ->send();

        $this->closeEmailModal();
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
