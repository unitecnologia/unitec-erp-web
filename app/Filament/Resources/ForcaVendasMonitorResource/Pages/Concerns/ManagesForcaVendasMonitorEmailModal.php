<?php

namespace App\Filament\Resources\ForcaVendasMonitorResource\Pages\Concerns;

use App\Models\ForcaVendasOrder;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use App\Support\ForcaVendas\ForcaVendasMonitorEnvioAnexosService;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesForcaVendasMonitorEmailModal
{
    use WithFileUploads;

    public bool $emailModalOpen = false;

    public bool $emailAttachmentsLoading = false;

    public ?int $emailOrderId = null;

    public string $emailTo = '';

    public string $whatsAppTo = '';

    public string $emailSubject = '';

    public string $emailMessage = '';

    public ?string $emailSelectedAttachmentId = null;

    /** @var list<array{id: string, name: string, path: string, display: string, owned?: bool}> */
    public array $emailAttachments = [];

    public ?TemporaryUploadedFile $emailExtraUpload = null;

    public function openEmailModal(): void
    {
        if (count($this->selecionados) > 1) {
            Notification::make()
                ->title('Envie apenas um pedido por vez. Desmarque a seleção em lote.')
                ->warning()
                ->send();

            return;
        }

        $orderId = $this->resolvePedidoEnvioId();

        if ($orderId === null) {
            return;
        }

        $order = ForcaVendasOrder::query()
            ->with(['cliente', 'pedido'])
            ->find($orderId);

        if (! $order) {
            Notification::make()
                ->title('Pedido não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->cleanupEmailAttachments();

        $envio = app(ForcaVendasMonitorEnvioAnexosService::class);
        $numero = $envio->formatNumeroDav($order);
        $cliente = $order->cliente;
        $phoneRaw = trim((string) (
            ($cliente?->celular1 ?? '')
            ?: ($cliente?->whatsapp ?? '')
            ?: ($cliente?->fone1 ?? '')
        ));

        $this->emailOrderId = (int) $order->id;
        $this->emailTo = trim((string) ($cliente?->email ?? ''));
        $this->whatsAppTo = WhatsAppPhone::formatDisplay($phoneRaw);
        $this->emailSubject = $envio->defaultEmailSubject($numero);
        $this->emailMessage = $envio->defaultEmailMessage($numero);
        $this->emailAttachments = [];
        $this->emailSelectedAttachmentId = null;
        $this->emailExtraUpload = null;
        $this->emailAttachmentsLoading = true;
        $this->emailModalOpen = true;

        $this->dispatch('erp-masks-refresh');
        $this->js('queueMicrotask(() => $wire.carregarAnexosEnvioMonitor())');
    }

    public function carregarAnexosEnvioMonitor(): void
    {
        if (! $this->emailModalOpen || ! $this->emailOrderId) {
            $this->emailAttachmentsLoading = false;

            return;
        }

        $order = ForcaVendasOrder::query()
            ->with(['cliente', 'pedido', 'venda.nfes', 'venda.pdvVenda.nfce'])
            ->find($this->emailOrderId);

        if (! $order) {
            $this->emailAttachmentsLoading = false;
            Notification::make()
                ->title('Pedido não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $envio = app(ForcaVendasMonitorEnvioAnexosService::class);
        $numero = $envio->formatNumeroDav($order);

        try {
            $anexos = $envio->montar($order);
        } catch (\Throwable $exception) {
            report($exception);
            $anexos = [];
        }

        $this->emailAttachmentsLoading = false;

        if ($anexos === []) {
            Notification::make()
                ->title('Não foi possível gerar os anexos do pedido.')
                ->warning()
                ->send();

            return;
        }

        $this->emailSubject = $envio->defaultEmailSubject($numero);
        $this->emailMessage = $envio->defaultEmailMessage($numero);
        $this->emailAttachments = array_map(
            static function (array $anexo): array {
                $anexo['owned'] = $anexo['owned'] ?? true;

                return $anexo;
            },
            $anexos,
        );
        $this->emailSelectedAttachmentId = $this->emailAttachments[0]['id'] ?? null;
    }

    public function closeEmailModal(): void
    {
        $this->emailModalOpen = false;
        $this->emailAttachmentsLoading = false;
        $this->emailOrderId = null;
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

        $this->emailAttachments[] = [
            'id' => 'extra-'.uniqid('', true),
            'name' => $this->emailExtraUpload->getClientOriginalName(),
            'path' => storage_path('app/'.$storedPath),
            'display' => $this->emailExtraUpload->getClientOriginalName(),
            'owned' => true,
        ];

        $this->emailExtraUpload = null;
    }

    public function removeEmailAttachment(string $attachmentId): void
    {
        $remaining = [];

        foreach ($this->emailAttachments as $attachment) {
            if (($attachment['id'] ?? '') === $attachmentId) {
                if (($attachment['owned'] ?? true) && is_string($attachment['path'] ?? null) && is_file($attachment['path'])) {
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

    #[On('send-fv-monitor-email')]
    public function sendMonitorPedidoEmail(): void
    {
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

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        if ($empresaId <= 0) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        $empresa = \App\Models\Empresa::query()->find($empresaId);

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não encontrada.')
                ->warning()
                ->send();

            return;
        }

        try {
            FiscalMailService::sendForEmpresa(
                empresaId: $empresaId,
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

        $order = $this->emailOrderId
            ? ForcaVendasOrder::query()->find($this->emailOrderId)
            : null;

        if ($order instanceof ForcaVendasOrder) {
            $order->registrarEnvioEmail();
            $this->resetTable();
        }
    }

    public function sendMonitorPedidoWhatsApp(): void
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
            'whatsAppTo.required' => 'Informe o WhatsApp do destinatário.',
            'emailMessage.required' => 'Informe a mensagem.',
        ]);

        $documents = [];

        foreach ($this->emailAttachments as $attachment) {
            if (! is_string($attachment['path'] ?? null) || ! is_file($attachment['path'])) {
                continue;
            }

            $documents[] = [
                'path' => $attachment['path'],
                'name' => (string) ($attachment['name'] ?? 'anexo.pdf'),
            ];
        }

        if ($documents === []) {
            Notification::make()
                ->title('Nenhum anexo válido.')
                ->body('Feche e abra novamente o envio (F9).')
                ->warning()
                ->send();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $empresa = $empresaId > 0 ? \App\Models\Empresa::query()->find($empresaId) : null;

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        try {
            $result = app(WhatsAppSender::class)->sendDocumentMessages(
                empresa: $empresa,
                tipo: WhatsAppSender::TIPO_ORCAMENTO,
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

        Notification::make()
            ->title('WhatsApp enviado.')
            ->body(($result['sent'] ?? count($documents)).' anexo(s). A tela permanece aberta para e-mail, se quiser.')
            ->success()
            ->send();

        $order = $this->emailOrderId
            ? ForcaVendasOrder::query()->find($this->emailOrderId)
            : null;

        if ($order instanceof ForcaVendasOrder) {
            $order->registrarEnvioWhatsApp($this->whatsAppTo);
            $this->resetTable();
        }
    }

    protected function cleanupEmailAttachments(): void
    {
        foreach ($this->emailAttachments as $attachment) {
            if (($attachment['owned'] ?? true) && is_string($attachment['path'] ?? null) && is_file($attachment['path'])) {
                @unlink($attachment['path']);
            }
        }

        $this->emailAttachments = [];
    }

    protected function resolvePedidoEnvioId(): ?int
    {
        if ($this->highlightedRecordId) {
            return (int) $this->highlightedRecordId;
        }

        $selecionado = (int) ($this->selecionados[count($this->selecionados) - 1] ?? 0);

        if ($selecionado > 0) {
            return $selecionado;
        }

        Notification::make()
            ->title('Selecione um pedido na lista para enviar.')
            ->warning()
            ->send();

        return null;
    }
}
