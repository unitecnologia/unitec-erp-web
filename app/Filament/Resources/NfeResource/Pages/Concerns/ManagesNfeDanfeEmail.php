<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeEvento;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Nfe\NfeDanfeReportService;
use App\Support\Erp\Nfe\NfeEventoLogger;
use Filament\Notifications\Notification;

trait ManagesNfeDanfeEmail
{
    public bool $nfeDanfeEmailModalOpen = false;

    public ?int $nfeDanfeEmailNfeId = null;

    public string $nfeDanfeEmailTo = '';

    public string $nfeDanfeEmailSubject = '';

    public string $nfeDanfeEmailMessage = '';

    /** @var list<array{id: string, name: string, path: string, display: string}> */
    public array $nfeDanfeEmailAttachments = [];

    public function openNfeDanfeEmailModal(): void
    {
        if (! $this->nfeFiscalSucessoNfeId) {
            $this->showNfeFiscalOverlayInfo('E-mail', 'NF-e não encontrada para envio.');

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

        return $this->highlightedRecordIdOrNotify('email');
    }

    protected function prepareNfeDanfeEmailModal(int $nfeId, bool $useFiscalOverlayErrors = false): void
    {
        $nfe = Nfe::query()->with('cliente')->find($nfeId);

        if (! $nfe) {
            $this->notifyNfeDanfeEmailError('NF-e não encontrada para envio.', $useFiscalOverlayErrors);

            return;
        }

        if ($nfe->status !== Nfe::STATUS_TRANSMITIDA) {
            $this->notifyNfeDanfeEmailError('Somente NF-e transmitida pode ser enviada por e-mail.', $useFiscalOverlayErrors);

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
            $pdf = $report->storePdfAttachment($nfe, $empresa);
        } catch (\Throwable $exception) {
            report($exception);

            $this->notifyNfeDanfeEmailError('Não foi possível gerar o PDF da DANFE para envio.', $useFiscalOverlayErrors);

            return;
        }

        $clienteNome = trim((string) ($nfe->cliente?->nome_razao ?? $nfe->cliente?->nome ?? ''));

        $this->nfeDanfeEmailNfeId = (int) $nfe->id;
        $this->nfeDanfeEmailTo = trim((string) ($nfe->cliente?->email ?? ''));
        $this->nfeDanfeEmailSubject = $report->defaultEmailSubject($nfe);
        $this->nfeDanfeEmailMessage = $report->defaultEmailMessage($nfe, $clienteNome);
        $this->nfeDanfeEmailAttachments = [[
            'id' => 'danfe',
            'name' => $pdf['name'],
            'path' => $pdf['path'],
            'display' => $pdf['display'],
        ]];
        $this->nfeDanfeEmailModalOpen = true;

        $this->dispatch('erp-nfe-focus-danfe-email-modal');
    }

    protected function notifyNfeDanfeEmailError(string $message, bool $useFiscalOverlayErrors): void
    {
        if ($useFiscalOverlayErrors) {
            $this->showNfeFiscalOverlayInfo('E-mail', $message);

            return;
        }

        Notification::make()
            ->title('E-mail')
            ->body($message)
            ->warning()
            ->send();
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
                ->title('Anexo da DANFE não encontrado.')
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
                ->body('Verifique a configuração de e-mail em Configurações Fiscais.')
                ->danger()
                ->send();

            return;
        }

        NfeEventoLogger::registrar(
            nfeId: (int) $nfe->id,
            tipo: NfeEvento::TIPO_EMAIL,
            titulo: 'DANFE enviada por e-mail',
            descricao: $this->nfeDanfeEmailSubject,
            destinatario: $this->nfeDanfeEmailTo,
            metadata: [
                'contexto' => 'danfe',
                'anexos' => count($this->nfeDanfeEmailAttachments),
            ],
        );

        Notification::make()
            ->title('E-mail enviado.')
            ->success()
            ->send();

        $this->closeNfeDanfeEmailModal();
    }

    public function closeNfeDanfeEmailModal(): void
    {
        $this->nfeDanfeEmailModalOpen = false;
        $this->nfeDanfeEmailNfeId = null;
        $this->nfeDanfeEmailTo = '';
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
}
