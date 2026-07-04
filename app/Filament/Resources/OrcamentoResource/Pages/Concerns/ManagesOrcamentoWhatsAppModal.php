<?php

namespace App\Filament\Resources\OrcamentoResource\Pages\Concerns;

use App\Models\Orcamento;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\Orcamento\OrcamentoReportService;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;

trait ManagesOrcamentoWhatsAppModal
{
    public bool $whatsAppModalOpen = false;

    public ?int $whatsAppOrcamentoId = null;

    public string $whatsAppTo = '';

    public string $whatsAppMessage = '';

    public ?string $whatsAppPdfPath = null;

    public string $whatsAppPdfName = '';

    public string $whatsAppPdfDisplay = '';

    public function openWhatsAppModal(): void
    {
        if (! $this->highlightedRecordIdOrNotify('whatsapp')) {
            return;
        }

        $report = app(OrcamentoReportService::class);
        $orcamento = Orcamento::query()->find($this->highlightedRecordId);

        if (! $orcamento) {
            Notification::make()
                ->title('Orçamento não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->cleanupWhatsAppPdf();

        $orcamento = $report->loadOrcamento($orcamento);
        $numero = $report->formatNumero($orcamento->numero);
        $pdf = $report->storePdfAttachment($orcamento);
        $cliente = $orcamento->cliente;
        $phoneDigits = WhatsAppPhone::digitsOnly($cliente?->celular1 ?: ($cliente?->whatsapp ?: ''));

        $this->whatsAppOrcamentoId = $orcamento->id;
        $this->whatsAppTo = strlen($phoneDigits) === 11
            ? WhatsAppPhone::formatDisplay($phoneDigits)
            : ($phoneDigits !== '' ? WhatsAppPhone::formatDisplay('55' . $phoneDigits) : '');
        $this->whatsAppMessage = $report->defaultWhatsAppMessage($numero);
        $this->whatsAppPdfPath = $pdf['path'];
        $this->whatsAppPdfName = $pdf['name'];
        $this->whatsAppPdfDisplay = $pdf['display'];
        $this->whatsAppModalOpen = true;
    }

    public function closeWhatsAppModal(): void
    {
        $this->whatsAppModalOpen = false;
        $this->whatsAppOrcamentoId = null;
        $this->whatsAppTo = '';
        $this->whatsAppMessage = '';
        $this->whatsAppPdfName = '';
        $this->whatsAppPdfDisplay = '';
        $this->cleanupWhatsAppPdf();
    }

    public function sendOrcamentoWhatsApp(): void
    {
        $this->validate([
            'whatsAppTo' => ['required', 'string', 'max:30', new CelularBrasileiroValido()],
            'whatsAppMessage' => ['required', 'string', 'max:1000'],
        ], [
            'whatsAppTo.required' => 'Informe o WhatsApp do destinatário.',
            'whatsAppMessage.required' => 'Informe a mensagem.',
        ]);

        if (! is_string($this->whatsAppPdfPath) || ! is_file($this->whatsAppPdfPath)) {
            Notification::make()
                ->title('PDF do orçamento não encontrado.')
                ->body('Feche e abra novamente o envio por WhatsApp (F10).')
                ->warning()
                ->send();

            return;
        }

        $report = app(OrcamentoReportService::class);
        $empresa = $report->resolveEmpresa();

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
                text: $this->whatsAppMessage,
                documentPath: $this->whatsAppPdfPath,
                documentName: $this->whatsAppPdfName !== '' ? $this->whatsAppPdfName : 'ORCAMENTO.PDF',
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
            ->success()
            ->send();

        $this->closeWhatsAppModal();
    }

    protected function cleanupWhatsAppPdf(): void
    {
        if (is_string($this->whatsAppPdfPath) && is_file($this->whatsAppPdfPath)) {
            @unlink($this->whatsAppPdfPath);
        }

        $this->whatsAppPdfPath = null;
    }
}
