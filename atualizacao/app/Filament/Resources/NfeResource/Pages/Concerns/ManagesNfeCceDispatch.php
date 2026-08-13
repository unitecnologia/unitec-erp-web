<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeCartaCorrecao;
use App\Models\NfeEvento;
use App\Models\Person;
use App\Models\Transportadora;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Nfe\NfeCartaCorrecaoReportService;
use App\Support\Erp\Nfe\NfeEventoLogger;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;

trait ManagesNfeCceDispatch
{
    public bool $nfeCceWhatsAppModalOpen = false;

    public bool $nfeCceEmailModalOpen = false;

    public string $nfeCceDispatchDestinatario = 'transportadora';

    public string $nfeCceWhatsAppTo = '';

    public string $nfeCceWhatsAppMessage = '';

    public ?string $nfeCceWhatsAppPdfPath = null;

    public string $nfeCceWhatsAppPdfName = '';

    public string $nfeCceWhatsAppPdfDisplay = '';

    public string $nfeCceEmailTo = '';

    public string $nfeCceEmailSubject = '';

    public string $nfeCceEmailMessage = '';

    /** @var list<array{id: string, name: string, path: string, display: string}> */
    public array $nfeCceEmailAttachments = [];

    public function openNfeCceWhatsAppModal(): void
    {
        if (! $this->prepareNfeCceDispatchModal('whatsapp')) {
            return;
        }

        $this->nfeCceEmailModalOpen = false;
        $this->nfeCceWhatsAppModalOpen = true;
        $this->dispatch('erp-nfe-focus-cce-whatsapp-modal');
    }

    public function openNfeCceEmailModal(): void
    {
        if (! $this->prepareNfeCceDispatchModal('email')) {
            return;
        }

        $this->nfeCceWhatsAppModalOpen = false;
        $this->nfeCceEmailModalOpen = true;
        $this->dispatch('erp-nfe-focus-cce-email-modal');
    }

    public function updatedNfeCceDispatchDestinatario(string $value): void
    {
        if (! in_array($value, ['cliente', 'transportadora'], true)) {
            $this->nfeCceDispatchDestinatario = 'transportadora';

            return;
        }

        $this->applyNfeCceDispatchDestinatarioContato();
    }

    public function closeNfeCceWhatsAppModal(): void
    {
        $this->nfeCceWhatsAppModalOpen = false;
        $this->nfeCceWhatsAppTo = '';
        $this->nfeCceWhatsAppMessage = '';
        $this->nfeCceWhatsAppPdfName = '';
        $this->nfeCceWhatsAppPdfDisplay = '';
        $this->cleanupNfeCceDispatchPdf();
    }

    public function closeNfeCceEmailModal(): void
    {
        $this->nfeCceEmailModalOpen = false;
        $this->nfeCceEmailTo = '';
        $this->nfeCceEmailSubject = '';
        $this->nfeCceEmailMessage = '';
        $this->cleanupNfeCceDispatchPdf();
        $this->cleanupNfeCceEmailAttachments();
    }

    public function closeNfeCceDispatchModals(): void
    {
        $this->closeNfeCceWhatsAppModal();
        $this->closeNfeCceEmailModal();
        $this->nfeCceDispatchDestinatario = 'transportadora';
    }

    public function updatedNfeCceWhatsAppMessage(string $value): void
    {
        $clean = WhatsAppMessageHelper::stripSystemFooter($value);

        if ($clean !== $value) {
            $this->nfeCceWhatsAppMessage = $clean;
        }
    }

    public function sendNfeCceWhatsApp(): void
    {
        $this->nfeCceWhatsAppMessage = WhatsAppMessageHelper::stripSystemFooter($this->nfeCceWhatsAppMessage);

        $maxLength = WhatsAppMessageHelper::maxUserMessageLength();

        $this->validate([
            'nfeCceDispatchDestinatario' => ['required', 'in:cliente,transportadora'],
            'nfeCceWhatsAppTo' => ['required', 'string', 'max:30', new CelularBrasileiroValido()],
            'nfeCceWhatsAppMessage' => ['required', 'string', 'max:' . $maxLength],
        ], [
            'nfeCceWhatsAppTo.required' => 'Informe o WhatsApp do destinatário.',
            'nfeCceWhatsAppMessage.required' => 'Informe a mensagem.',
        ]);

        if (! is_string($this->nfeCceWhatsAppPdfPath) || ! is_file($this->nfeCceWhatsAppPdfPath)) {
            Notification::make()
                ->title('PDF da CC-e não encontrado.')
                ->body('Feche e abra novamente o envio por WhatsApp.')
                ->warning()
                ->send();

            return;
        }

        $context = $this->resolveNfeCceDispatchContext();

        if ($context === null) {
            return;
        }

        ['carta' => $carta, 'nfe' => $nfe, 'empresa' => $empresa] = $context;

        $sender = app(WhatsAppSender::class);

        try {
            $result = $sender->sendDocumentMessage(
                empresa: $empresa,
                tipo: WhatsAppSender::TIPO_NFE,
                number: $this->nfeCceWhatsAppTo,
                text: $this->nfeCceWhatsAppMessage,
                documentPath: $this->nfeCceWhatsAppPdfPath,
                documentName: $this->nfeCceWhatsAppPdfName !== '' ? $this->nfeCceWhatsAppPdfName : 'CCE-NFE.PDF',
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
            ->body($this->nfeCceDispatchDestinatarioLabel())
            ->success()
            ->send();

        NfeEventoLogger::registrar(
            nfeId: (int) $nfe->id,
            tipo: NfeEvento::TIPO_WHATSAPP,
            titulo: 'CC-e enviada por WhatsApp',
            descricao: $this->nfeCceDispatchDestinatarioLabel() . ' Sequência ' . $carta->sequencia . '.',
            destinatario: WhatsAppPhone::formatDisplay($this->nfeCceWhatsAppTo) ?? $this->nfeCceWhatsAppTo,
            referenciaTipo: NfeCartaCorrecao::class,
            referenciaId: (int) $carta->id,
            metadata: [
                'contexto' => 'cce',
                'destinatario_tipo' => $this->nfeCceDispatchDestinatario,
                'arquivo' => $this->nfeCceWhatsAppPdfName !== '' ? $this->nfeCceWhatsAppPdfName : 'CCE-NFE.PDF',
            ],
        );

        $this->closeNfeCceWhatsAppModal();
    }

    public function sendNfeCceEmail(): void
    {
        $this->validate([
            'nfeCceDispatchDestinatario' => ['required', 'in:cliente,transportadora'],
            'nfeCceEmailTo' => ['required', 'email'],
            'nfeCceEmailSubject' => ['required', 'string', 'max:255'],
            'nfeCceEmailMessage' => ['required', 'string', 'max:5000'],
        ], [
            'nfeCceEmailTo.required' => 'Informe o e-mail do destinatário.',
            'nfeCceEmailTo.email' => 'Informe um e-mail válido.',
            'nfeCceEmailSubject.required' => 'Informe o assunto.',
            'nfeCceEmailMessage.required' => 'Informe a mensagem.',
        ]);

        if ($this->nfeCceEmailAttachments === []) {
            Notification::make()
                ->title('Inclua ao menos um anexo.')
                ->warning()
                ->send();

            return;
        }

        $context = $this->resolveNfeCceDispatchContext();

        if ($context === null) {
            return;
        }

        $empresa = $context['empresa'];
        $carta = $context['carta'];
        $nfe = $context['nfe'];

        try {
            FiscalMailService::sendForEmpresa(
                empresaId: (int) $empresa->id,
                to: $this->nfeCceEmailTo,
                messageBody: $this->nfeCceEmailMessage,
                subjectLine: $this->nfeCceEmailSubject,
                fileAttachments: collect($this->nfeCceEmailAttachments)
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
            ->body($this->nfeCceDispatchDestinatarioLabel())
            ->success()
            ->send();

        NfeEventoLogger::registrar(
            nfeId: (int) $nfe->id,
            tipo: NfeEvento::TIPO_EMAIL,
            titulo: 'CC-e enviada por e-mail',
            descricao: trim($this->nfeCceEmailSubject . '. ' . $this->nfeCceDispatchDestinatarioLabel()),
            destinatario: $this->nfeCceEmailTo,
            referenciaTipo: NfeCartaCorrecao::class,
            referenciaId: (int) $carta->id,
            metadata: [
                'contexto' => 'cce',
                'destinatario_tipo' => $this->nfeCceDispatchDestinatario,
                'anexos' => count($this->nfeCceEmailAttachments),
            ],
        );

        $this->closeNfeCceEmailModal();
    }

    #[Computed]
    public function nfeCceDispatchDestinatarioNome(): string
    {
        $context = $this->getNfeCceDispatchContextSilent();

        if ($context === null) {
            return '';
        }

        $destinatario = $this->resolveNfeCceDispatchDestinatario($context['nfe']);

        if ($destinatario instanceof Transportadora) {
            return trim((string) ($destinatario->proprietario ?: $destinatario->apelido ?: ''));
        }

        return trim((string) ($destinatario?->nome_razao ?? $destinatario?->nome ?? ''));
    }

    #[Computed]
    public function nfeCceDispatchDestinatarioAviso(): ?string
    {
        $context = $this->getNfeCceDispatchContextSilent();

        if ($context === null) {
            return null;
        }

        $destinatario = $this->resolveNfeCceDispatchDestinatario($context['nfe']);

        if ($destinatario instanceof Person || $destinatario instanceof Transportadora) {
            return null;
        }

        return $this->nfeCceDispatchDestinatario === 'transportadora'
            ? 'Esta NF-e não possui transportadora vinculada. Informe o contato manualmente.'
            : 'Esta NF-e não possui cliente vinculado. Informe o contato manualmente.';
    }

    protected function prepareNfeCceDispatchModal(string $channel): bool
    {
        if (! $this->nfeCceSucessoCartaId) {
            Notification::make()
                ->title('CC-e não encontrada para envio.')
                ->warning()
                ->send();

            return false;
        }

        $context = $this->resolveNfeCceDispatchContext();

        if ($context === null) {
            return false;
        }

        $report = app(NfeCartaCorrecaoReportService::class);

        $this->cleanupNfeCceDispatchPdf();
        $this->cleanupNfeCceEmailAttachments();

        try {
            $pdf = $report->storePdfAttachment($context['carta'], $context['empresa']);
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível gerar o PDF da CC-e.')
                ->warning()
                ->send();

            return false;
        }

        if ($channel === 'whatsapp') {
            $this->nfeCceWhatsAppPdfPath = $pdf['path'];
            $this->nfeCceWhatsAppPdfName = $pdf['name'];
            $this->nfeCceWhatsAppPdfDisplay = $pdf['display'];
        } else {
            $this->nfeCceEmailAttachments = [[
                'id' => 'cce-pdf',
                'name' => $pdf['name'],
                'path' => $pdf['path'],
                'display' => $pdf['display'],
            ]];
        }

        $this->applyNfeCceDispatchDestinatarioContato();

        return true;
    }

    protected function applyNfeCceDispatchDestinatarioContato(): void
    {
        $context = $this->resolveNfeCceDispatchContext();

        if ($context === null) {
            return;
        }

        $report = app(NfeCartaCorrecaoReportService::class);
        $contato = $report->resolveDestinatarioContato($context['nfe'], $this->nfeCceDispatchDestinatario);
        $phoneDigits = $contato['phoneDigits'];

        $this->nfeCceWhatsAppTo = strlen($phoneDigits) === 11
            ? WhatsAppPhone::formatDisplay($phoneDigits)
            : ($phoneDigits !== '' ? WhatsAppPhone::formatDisplay('55' . $phoneDigits) : '');
        $this->nfeCceEmailTo = $contato['email'];
        $this->nfeCceWhatsAppMessage = $report->defaultWhatsAppMessage($context['carta']);
        $this->nfeCceEmailSubject = $report->defaultEmailSubject($context['carta']);
        $this->nfeCceEmailMessage = $report->defaultEmailMessage($context['carta'], $contato['nome']);
    }

    /**
     * @return array{carta: NfeCartaCorrecao, nfe: Nfe, empresa: Empresa}|null
     */
    protected function resolveNfeCceDispatchContext(): ?array
    {
        $context = $this->getNfeCceDispatchContextSilent();

        if ($context === null) {
            Notification::make()
                ->title('Não foi possível localizar os dados da CC-e.')
                ->warning()
                ->send();
        }

        return $context;
    }

    /**
     * @return array{carta: NfeCartaCorrecao, nfe: Nfe, empresa: Empresa}|null
     */
    protected function getNfeCceDispatchContextSilent(): ?array
    {
        if (! $this->nfeCceSucessoCartaId) {
            return null;
        }

        $carta = NfeCartaCorrecao::query()
            ->with(['nfe.cliente', 'nfe.transportadora'])
            ->find($this->nfeCceSucessoCartaId);

        $nfe = $carta?->nfe;
        $empresa = $nfe?->empresa_id
            ? Empresa::query()->find($nfe->empresa_id)
            : $this->resolveNfeCceEmpresa();

        if (! $carta || ! $nfe || ! $empresa) {
            return null;
        }

        return [
            'carta' => $carta,
            'nfe' => $nfe,
            'empresa' => $empresa,
        ];
    }

    protected function resolveNfeCceDispatchDestinatario(Nfe $nfe): Person|Transportadora|null
    {
        return $this->nfeCceDispatchDestinatario === 'transportadora'
            ? $nfe->transportadora
            : $nfe->cliente;
    }

    /** @deprecated Use resolveNfeCceDispatchDestinatario() */
    protected function resolveNfeCceDispatchPerson(Nfe $nfe): Person|Transportadora|null
    {
        return $this->resolveNfeCceDispatchDestinatario($nfe);
    }

    protected function nfeCceDispatchDestinatarioLabel(): string
    {
        $nome = $this->nfeCceDispatchDestinatarioNome;

        return match ($this->nfeCceDispatchDestinatario) {
            'transportadora' => filled($nome) ? "Enviado para transportadora: {$nome}" : 'Enviado para transportadora.',
            default => filled($nome) ? "Enviado para cliente: {$nome}" : 'Enviado para cliente.',
        };
    }

    protected function cleanupNfeCceDispatchPdf(): void
    {
        if (is_string($this->nfeCceWhatsAppPdfPath) && is_file($this->nfeCceWhatsAppPdfPath)) {
            @unlink($this->nfeCceWhatsAppPdfPath);
        }

        $this->nfeCceWhatsAppPdfPath = null;
    }

    protected function cleanupNfeCceEmailAttachments(): void
    {
        foreach ($this->nfeCceEmailAttachments as $attachment) {
            if (isset($attachment['path']) && is_file($attachment['path'])) {
                @unlink($attachment['path']);
            }
        }

        $this->nfeCceEmailAttachments = [];
    }
}
