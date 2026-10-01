<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages\Concerns;

use App\Models\OrdemServico;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Os\OrdemServicoEnvioAnexosService;
use App\Support\Erp\Os\OrdemServicoReportService;
use App\Support\Erp\Os\OrdemServicoTecnicoWhatsApp;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesOrdemServicoEmailModal
{
    use WithFileUploads;

    public bool $emailModalOpen = false;

    public bool $emailAttachmentsLoading = false;

    public bool $sendModalOpen = false;

    public string $emailEnvioModo = 'completo';

    public ?int $emailOrdemId = null;

    public string $emailTo = '';

    public string $whatsAppTo = '';

    public string $emailSubject = '';

    public string $emailMessage = '';

    public ?string $emailSelectedAttachmentId = null;

    /** @var list<array{id: string, name: string, path: string, display: string, owned?: bool}> */
    public array $emailAttachments = [];

    public ?TemporaryUploadedFile $emailExtraUpload = null;

    public function openSendModal(): void
    {
        if (! $this->highlightedRecordIdOrNotify('enviar')) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.print')) {
            return;
        }

        $this->sendModalOpen = true;
    }

    public function closeSendModal(): void
    {
        $this->sendModalOpen = false;
    }

    public function openEmailModalCompleta(): void
    {
        $this->closeSendModal();
        $this->js('window.__erpOsShowEmailModalShell && window.__erpOsShowEmailModalShell()');
        $this->openEmailModalForMode('completo');
    }

    public function openEmailModalTecnica(): void
    {
        $this->closeSendModal();
        $this->js('window.__erpOsShowEmailModalShell && window.__erpOsShowEmailModalShell()');
        $this->openEmailModalForMode('tecnica');
    }

    public function openEmailModal(): void
    {
        $this->openEmailModalForMode('completo');
    }

    protected function openEmailModalForMode(string $modo): void
    {
        if (! $this->highlightedRecordIdOrNotify('enviar')) {
            $this->js('window.__erpOsHideEmailModalShell && window.__erpOsHideEmailModalShell()');

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.print')) {
            $this->js('window.__erpOsHideEmailModalShell && window.__erpOsHideEmailModalShell()');

            return;
        }

        $ordem = OrdemServico::query()
            ->when($modo !== 'tecnica', fn ($query) => $query->with('cliente'))
            ->find($this->highlightedRecordId);

        if (! $ordem) {
            $this->js('window.__erpOsHideEmailModalShell && window.__erpOsHideEmailModalShell()');
            Notification::make()
                ->title('Ordem de serviço não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $this->cleanupEmailAttachments();

        $report = app(OrdemServicoReportService::class);
        $numero = $report->formatNumero($ordem);
        $tecnica = $modo === 'tecnica';
        $this->emailEnvioModo = $tecnica ? 'tecnica' : 'completo';

        $this->emailOrdemId = $ordem->id;
        $this->emailAttachments = [];
        $this->emailSelectedAttachmentId = null;
        $this->emailExtraUpload = null;
        $this->emailAttachmentsLoading = true;
        $this->emailModalOpen = true;

        if ($tecnica) {
            $this->emailTo = '';
            $this->whatsAppTo = OrdemServicoTecnicoWhatsApp::display($ordem);
            $this->emailSubject = $report->defaultTecnicaEmailSubject($numero);
            $this->emailMessage = $report->defaultTecnicaEmailMessage($numero, $ordem);
        } else {
            $cliente = $ordem->cliente;
            $phoneRaw = trim((string) (
                $ordem->fone1
                ?: $ordem->fone2
                ?: ($cliente?->celular1 ?? '')
                ?: ($cliente?->whatsapp ?? '')
                ?: ($cliente?->fone1 ?? '')
            ));

            $this->emailTo = trim((string) ($cliente?->email ?? ''));
            $this->whatsAppTo = WhatsAppPhone::formatDisplay($phoneRaw);
            $this->emailSubject = $report->defaultEmailSubject($numero);
            $this->emailMessage = $report->defaultEmailMessage($numero, [], $ordem);
        }

        $this->dispatch('erp-masks-refresh');
        $this->js('queueMicrotask(() => $wire.carregarAnexosEnvioOs())');
    }

    public function carregarAnexosEnvioOs(): void
    {
        if (! $this->emailModalOpen || ! $this->emailOrdemId) {
            $this->emailAttachmentsLoading = false;

            return;
        }

        $ordem = OrdemServico::query()
            ->when($this->emailEnvioModo !== 'tecnica', fn ($query) => $query->with(['cliente', 'nfse']))
            ->find($this->emailOrdemId);

        if (! $ordem) {
            $this->emailAttachmentsLoading = false;
            Notification::make()
                ->title('Ordem de serviço não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $report = app(OrdemServicoReportService::class);
        $numero = $report->formatNumero($ordem);

        try {
            $anexos = app(OrdemServicoEnvioAnexosService::class)
                ->montar($ordem, $this->emailEnvioModo === 'tecnica');
        } catch (\Throwable $exception) {
            report($exception);
            $anexos = [];
        }

        $this->emailAttachmentsLoading = false;

        if ($anexos === []) {
            Notification::make()
                ->title('Não foi possível gerar os anexos da OS.')
                ->warning()
                ->send();

            return;
        }

        $labels = collect($anexos)
            ->map(fn (array $a): string => (string) ($a['display'] ?? $a['name'] ?? ''))
            ->filter()
            ->values()
            ->all();

        $this->emailAttachments = array_map(
            static function (array $anexo): array {
                $anexo['owned'] = $anexo['owned'] ?? true;

                return $anexo;
            },
            $anexos,
        );
        $this->emailSelectedAttachmentId = $this->emailAttachments[0]['id'] ?? null;

        if ($this->emailEnvioModo !== 'tecnica') {
            $this->emailSubject = $report->defaultEmailSubject($numero, $labels);
        }
    }

    public function closeEmailModal(): void
    {
        $this->emailModalOpen = false;
        $this->emailAttachmentsLoading = false;
        $this->emailEnvioModo = 'completo';
        $this->emailOrdemId = null;
        $this->emailTo = '';
        $this->whatsAppTo = '';
        $this->emailSubject = '';
        $this->emailMessage = '';
        $this->emailExtraUpload = null;
        $this->emailSelectedAttachmentId = null;
        $this->cleanupEmailAttachments();
        $this->js('window.__erpOsHideEmailModalShell && window.__erpOsHideEmailModalShell()');
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
            'owned' => true,
        ];

        $this->emailExtraUpload = null;
    }

    public function removeEmailAttachment(string $attachmentId): void
    {
        $remaining = [];

        foreach ($this->emailAttachments as $attachment) {
            if ($attachment['id'] === $attachmentId) {
                if (($attachment['owned'] ?? true) && is_file($attachment['path'])) {
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

    #[On('send-os-email')]
    public function sendOrdemServicoEmail(): void
    {
        if ($this->emailEnvioModo === 'tecnica') {
            return;
        }

        if ($this->emailAttachmentsLoading) {
            Notification::make()
                ->title('Aguarde a geração dos anexos.')
                ->warning()
                ->send();

            return;
        }

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

        $empresa = app(OrdemServicoReportService::class)->resolveEmpresa();

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
            ->body('Anexos: '.count($this->emailAttachments).'. A tela permanece aberta para WhatsApp, se quiser.')
            ->success()
            ->send();

        $ordem = $this->emailOrdemId
            ? OrdemServico::query()->find($this->emailOrdemId)
            : null;

        if ($ordem instanceof OrdemServico && $this->emailEnvioModo !== 'tecnica') {
            $ordem->registrarEnvioEmail();

            if (method_exists($this, 'resetTable')) {
                $this->resetTable();
            }
        }
    }

    public function sendOrdemServicoWhatsApp(): void
    {
        if ($this->emailAttachmentsLoading) {
            Notification::make()
                ->title('Aguarde a geração dos anexos.')
                ->warning()
                ->send();

            return;
        }

        $this->emailMessage = WhatsAppMessageHelper::stripSystemFooter($this->emailMessage);
        $maxLength = WhatsAppMessageHelper::maxUserMessageLength();

        $this->validate([
            'whatsAppTo' => ['required', 'string', 'max:30', new CelularBrasileiroValido()],
            'emailMessage' => ['required', 'string', 'max:'.$maxLength],
        ], [
            'whatsAppTo.required' => $this->emailEnvioModo === 'tecnica'
                ? 'Informe o WhatsApp do mecânico.'
                : 'Informe o WhatsApp do destinatário.',
            'emailMessage.required' => 'Informe a mensagem.',
        ]);

        $documents = [];

        foreach ($this->emailAttachments as $attachment) {
            $path = (string) ($attachment['path'] ?? '');
            $name = (string) ($attachment['name'] ?? 'documento.pdf');

            if ($path === '' || ! is_file($path)) {
                continue;
            }

            $lower = strtolower($name);
            $mimetype = str_ends_with($lower, '.xml')
                ? 'application/xml'
                : 'application/pdf';

            $documents[] = [
                'path' => $path,
                'name' => $name !== '' ? $name : 'documento.pdf',
                'mimetype' => $mimetype,
            ];
        }

        if ($documents === []) {
            Notification::make()
                ->title('Nenhum anexo válido para WhatsApp.')
                ->body('Feche e abra novamente o envio (F9).')
                ->warning()
                ->send();

            return;
        }

        $empresa = app(OrdemServicoReportService::class)->resolveEmpresa();

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
                tipo: WhatsAppSender::TIPO_ORDEM_SERVICO,
                number: $this->whatsAppTo,
                text: $this->emailMessage,
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

        if (! ($result['ok'] ?? false)) {
            Notification::make()
                ->title('Não foi possível enviar o WhatsApp.')
                ->body((string) ($result['message'] ?? 'Falha no envio.'))
                ->warning()
                ->send();

            return;
        }

        $enviados = (int) ($result['sent'] ?? count($documents));
        $body = $enviados.' anexo(s).';

        if ($this->emailEnvioModo !== 'tecnica') {
            $body .= ' A tela permanece aberta para e-mail, se quiser.';
        }

        Notification::make()
            ->title('WhatsApp enviado.')
            ->body($body)
            ->success()
            ->send();

        $ordem = $this->emailOrdemId
            ? OrdemServico::query()->find($this->emailOrdemId)
            : null;

        if ($ordem instanceof OrdemServico && $this->emailEnvioModo !== 'tecnica') {
            $ordem->registrarEnvioWhatsApp($this->whatsAppTo);

            if (method_exists($this, 'resetTable')) {
                $this->resetTable();
            }
        }
    }

    protected function cleanupEmailAttachments(): void
    {
        foreach ($this->emailAttachments as $attachment) {
            if (! ($attachment['owned'] ?? true)) {
                continue;
            }

            if (isset($attachment['path']) && is_file($attachment['path'])) {
                @unlink($attachment['path']);
            }
        }

        $this->emailAttachments = [];
    }
}
