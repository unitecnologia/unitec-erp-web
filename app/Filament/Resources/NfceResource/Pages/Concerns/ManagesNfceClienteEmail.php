<?php

namespace App\Filament\Resources\NfceResource\Pages\Concerns;

use App\Models\Empresa;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Nfce\NfceCupomReportService;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Throwable;

trait ManagesNfceClienteEmail
{
    public bool $nfceEnviarModalOpen = false;

    #[Locked]
    public ?int $nfceEnviarNfceId = null;

    public string $nfceEnviarEmailTo = '';

    public string $nfceEnviarWhatsAppTo = '';

    public string $nfceEnviarSubject = '';

    public string $nfceEnviarMessage = '';

    /** @var list<array{id: string, name: string, path: string, display: string}> */
    #[Locked]
    public array $nfceEnviarAttachments = [];

    public function openNfceEnviarModal(): void
    {
        if (! $this->nfcePodeExecutar()) {
            return;
        }

        $id = $this->highlightedRecordIdOrNotify('enviar');

        if (! $id) {
            return;
        }

        $nfce = $this->findNfceNoEscopo($id, ['pdvVenda.person', 'pdvVenda.nfce']);
        $venda = $nfce?->pdvVenda;
        $service = app(NfceCupomReportService::class);

        if (! $nfce || ! $venda) {
            $this->notifyNfceEnviar('NFC-e sem venda vinculada.');

            return;
        }

        if (($bloqueio = $service->motivoBloqueioEnvio($nfce)) !== null) {
            $this->notifyNfceEnviar($bloqueio);

            return;
        }

        $empresa = $this->currentNfceEmpresaForEnvio();

        if (! $empresa) {
            return;
        }

        $this->cleanupNfceEnviarAttachments();

        try {
            $pdf = $service->storeDanfeEnvioAttachment($venda, $empresa);
            $attachments = [[
                'id' => 'cupom-pdf',
                'name' => $pdf['name'],
                'path' => $pdf['path'],
                'display' => $pdf['a4'] ? 'DANFE NFC-e A4 (PDF)' : 'DANFE NFC-e (PDF)',
            ]];

            $xml = $service->storeXmlAttachment($nfce);

            if ($xml) {
                $attachments[] = [
                    'id' => 'xml',
                    'name' => $xml['name'],
                    'path' => $xml['path'],
                    'display' => 'XML autorizado',
                ];
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->nfceEnviarAttachments = $attachments ?? [];
            $this->cleanupNfceEnviarAttachments();

            Notification::make()
                ->title('Não foi possível preparar o envio da NFC-e.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->nfceEnviarNfceId = (int) $nfce->id;
        $this->nfceEnviarEmailTo = $service->resolveClienteEmail($venda);
        $this->nfceEnviarWhatsAppTo = $service->resolveClienteWhatsApp($venda);
        $this->nfceEnviarSubject = $service->defaultEmailSubject($nfce, $venda, $empresa);
        $this->nfceEnviarMessage = $service->defaultEmailMessage($nfce, $venda, $empresa);
        $this->nfceEnviarAttachments = $attachments;
        $this->nfceEnviarModalOpen = true;
    }

    public function closeNfceEnviarModal(): void
    {
        $this->nfceEnviarModalOpen = false;
        $this->nfceEnviarNfceId = null;
        $this->nfceEnviarEmailTo = '';
        $this->nfceEnviarWhatsAppTo = '';
        $this->nfceEnviarSubject = '';
        $this->nfceEnviarMessage = '';
        $this->cleanupNfceEnviarAttachments();
    }

    public function updatedNfceEnviarMessage(string $value): void
    {
        $clean = WhatsAppMessageHelper::stripSystemFooter($value);

        if ($clean !== $value) {
            $this->nfceEnviarMessage = $clean;
        }
    }

    public function sendNfceEnviarEmail(): void
    {
        if (! $this->nfceEnviarPodeEnviar()) {
            return;
        }

        $this->validate([
            'nfceEnviarEmailTo' => ['required', 'email'],
            'nfceEnviarSubject' => ['required', 'string', 'max:255'],
            'nfceEnviarMessage' => ['required', 'string', 'max:5000'],
        ], [
            'nfceEnviarEmailTo.required' => 'Informe o e-mail do cliente.',
            'nfceEnviarEmailTo.email' => 'Informe um e-mail válido.',
            'nfceEnviarSubject.required' => 'Informe o assunto.',
            'nfceEnviarMessage.required' => 'Informe a mensagem.',
        ]);

        $anexos = $this->nfceEnviarAnexosValidos();
        $empresa = $anexos === [] ? null : $this->currentNfceEmpresaForEnvio();

        if (! $empresa) {
            return;
        }

        try {
            FiscalMailService::sendForEmpresa(
                empresaId: (int) $empresa->id,
                to: $this->nfceEnviarEmailTo,
                messageBody: $this->nfceEnviarMessage,
                subjectLine: $this->nfceEnviarSubject,
                fileAttachments: array_map(
                    fn (array $anexo): array => ['path' => $anexo['path'], 'name' => $anexo['name']],
                    $anexos,
                ),
                fromAddress: $empresa->email ?: null,
                fromName: $empresa->nome ?: null,
            );
        } catch (Throwable $exception) {
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

    public function sendNfceEnviarWhatsApp(): void
    {
        if (! $this->nfceEnviarPodeEnviar()) {
            return;
        }

        $this->nfceEnviarMessage = WhatsAppMessageHelper::stripSystemFooter($this->nfceEnviarMessage);

        $this->validate([
            'nfceEnviarWhatsAppTo' => ['required', 'string', 'max:30', new CelularBrasileiroValido()],
            'nfceEnviarMessage' => ['required', 'string', 'max:'.WhatsAppMessageHelper::maxUserMessageLength()],
        ], [
            'nfceEnviarWhatsAppTo.required' => 'Informe o WhatsApp do cliente.',
            'nfceEnviarMessage.required' => 'Informe a mensagem.',
        ]);

        $anexos = $this->nfceEnviarAnexosValidos();
        $empresa = $anexos === [] ? null : $this->currentNfceEmpresaForEnvio();

        if (! $empresa) {
            return;
        }

        $documents = array_map(fn (array $anexo): array => [
            'path' => $anexo['path'],
            'name' => $anexo['name'],
            'mimetype' => $anexo['xml'] ? 'application/xml' : 'application/pdf',
            'caption' => $anexo['xml'] ? 'XML da NFC-e' : null,
        ], $anexos);

        try {
            $result = app(WhatsAppSender::class)->sendDocumentMessages(
                empresa: $empresa,
                tipo: WhatsAppSender::TIPO_NFCE,
                number: $this->nfceEnviarWhatsAppTo,
                text: $this->nfceEnviarMessage,
                documents: $documents,
            );
        } catch (Throwable $exception) {
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

    /**
     * Revalida permissão, empresa e status fiscal no momento do envio (a nota pode ter sido cancelada).
     */
    protected function nfceEnviarPodeEnviar(): bool
    {
        if (! $this->nfcePodeExecutar()) {
            return false;
        }

        $nfce = $this->findNfceNoEscopo($this->nfceEnviarNfceId);

        if (! $nfce) {
            $this->closeNfceEnviarModal();
            $this->notifyNfceEnviar('NFC-e não encontrada para a empresa atual.');

            return false;
        }

        if (($bloqueio = app(NfceCupomReportService::class)->motivoBloqueioEnvio($nfce)) !== null) {
            $this->closeNfceEnviarModal();
            $this->notifyNfceEnviar($bloqueio);

            return false;
        }

        return true;
    }

    /**
     * @return list<array{path: string, name: string, xml: bool}>
     */
    protected function nfceEnviarAnexosValidos(): array
    {
        $anexos = [];

        foreach ($this->nfceEnviarAttachments as $attachment) {
            $path = $this->nfceEnviarAnexoSeguro($attachment);

            if ($path !== null) {
                $anexos[] = [
                    'path' => $path,
                    'name' => basename((string) ($attachment['name'] ?? basename($path))),
                    'xml' => str_ends_with(strtolower($path), '.xml'),
                ];
            }
        }

        if ($anexos === []) {
            Notification::make()
                ->title('Anexos da NFC-e não encontrados.')
                ->body('Feche e abra novamente o envio da nota.')
                ->warning()
                ->send();
        }

        return $anexos;
    }

    protected function cleanupNfceEnviarAttachments(): void
    {
        foreach ($this->nfceEnviarAttachments as $attachment) {
            $path = $this->nfceEnviarAnexoSeguro($attachment);
            if ($path !== null) {
                @unlink($path);
            }
        }

        $this->nfceEnviarAttachments = [];
    }

    /**
     * Só aceita arquivos temporários gerados pelo NfceCupomReportService.
     *
     * @param  array<string, mixed>  $attachment
     */
    protected function nfceEnviarAnexoSeguro(array $attachment): ?string
    {
        $path = realpath((string) ($attachment['path'] ?? ''));
        if ($path === false || ! is_file($path)) {
            return null;
        }

        $extensao = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $permitidos = [
            'pdf' => storage_path('app/temp/nfce-cupom'),
            'xml' => storage_path('app/temp/nfce-xml'),
        ];

        $diretorio = isset($permitidos[$extensao]) ? realpath($permitidos[$extensao]) : false;
        if ($diretorio === false) {
            return null;
        }

        $prefixo = rtrim($diretorio, '\\/').DIRECTORY_SEPARATOR;

        return str_starts_with(strtolower($path), strtolower($prefixo)) ? $path : null;
    }

    protected function notifyNfceEnviar(string $message): void
    {
        Notification::make()
            ->title('Enviar nota')
            ->body($message)
            ->warning()
            ->send();
    }

    protected function currentNfceEmpresaForEnvio(): ?Empresa
    {
        $empresaId = $this->empresaIdAtiva() ?? session('erp_empresa_id', Auth::user()?->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        if (! $empresa) {
            $this->notifyNfceEnviar('Empresa não identificada na sessão.');
        }

        return $empresa;
    }
}
