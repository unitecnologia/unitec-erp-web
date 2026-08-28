<?php

namespace App\Filament\Resources\NfceResource\Pages\Concerns;

use App\Models\Contador;
use App\Models\Empresa;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Nfce\NfceContadorPacoteService;
use App\Support\Erp\Reports\NfceRelatorioReportService;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Throwable;

trait ManagesNfceContadorEmail
{
    public bool $nfceContadorEmailModalOpen = false;

    public string $nfceContadorCompetencia = '';

    public string $nfceContadorEmailTo = '';

    public string $nfceContadorWhatsAppTo = '';

    public string $nfceContadorEmailSubject = '';

    public string $nfceContadorEmailMessage = '';

    public function openNfceContadorEmailModal(): void
    {
        $empresa = $this->currentNfceEmpresa();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        $service = app(NfceContadorPacoteService::class);
        $contador = Contador::paraEnvioEmail();

        if (! $contador) {
            Notification::make()
                ->title('Contador não cadastrado.')
                ->body('Cadastre o contador em RH → Contador, com e-mail para o envio do pacote.')
                ->warning()
                ->send();

            return;
        }

        $email = trim((string) ($contador->email ?? ''));
        $phone = trim((string) ($contador->fone ?? ''));
        $phoneDigits = WhatsAppPhone::digitsOnly($phone);

        if ($email === '') {
            Notification::make()
                ->title('E-mail do contador não cadastrado.')
                ->body('Informe o e-mail no cadastro do Contador (RH → Contador) para enviar o pacote por e-mail.')
                ->warning()
                ->send();

            return;
        }

        $competencia = now()->subMonth()->format('Y-m');
        $periodo = NfceRelatorioReportService::competenciaPeriod($competencia);

        $this->nfceContadorCompetencia = $competencia;
        $this->nfceContadorEmailTo = $email;
        $this->nfceContadorWhatsAppTo = $this->formatContadorWhatsAppDisplay($phoneDigits);
        $this->nfceContadorEmailSubject = $service->defaultEmailSubject($empresa, $periodo);
        $this->nfceContadorEmailMessage = $service->defaultEmailMessage($empresa, $periodo, 0, 0);
        $this->nfceContadorEmailModalOpen = true;
    }

    public function closeNfceContadorEmailModal(): void
    {
        $this->nfceContadorEmailModalOpen = false;
        $this->nfceContadorWhatsAppTo = '';
    }

    public function nfceContadorPacoteAnexoLabel(): string
    {
        $empresa = $this->currentNfceEmpresa();

        if (! $empresa || ! preg_match('/^\d{4}-\d{2}$/', $this->nfceContadorCompetencia)) {
            return 'PACOTE NFCE.ZIP';
        }

        return strtoupper(app(NfceContadorPacoteService::class)->expectedZipFileName($empresa, $this->nfceContadorCompetencia));
    }

    public function updatedNfceContadorCompetencia(): void
    {
        $empresa = $this->currentNfceEmpresa();

        if (! $empresa || ! preg_match('/^\d{4}-\d{2}$/', $this->nfceContadorCompetencia)) {
            return;
        }

        $service = app(NfceContadorPacoteService::class);
        $periodo = NfceRelatorioReportService::competenciaPeriod($this->nfceContadorCompetencia);

        $this->nfceContadorEmailSubject = $service->defaultEmailSubject($empresa, $periodo);
        $this->nfceContadorEmailMessage = $service->defaultEmailMessage($empresa, $periodo, 0, 0);
    }

    public function updatedNfceContadorEmailMessage(string $value): void
    {
        $clean = WhatsAppMessageHelper::stripSystemFooter($value);

        if ($clean !== $value) {
            $this->nfceContadorEmailMessage = $clean;
        }
    }

    public function sendNfceContadorEmail(): void
    {
        $this->validate([
            'nfceContadorCompetencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'nfceContadorEmailTo' => ['required', 'email'],
            'nfceContadorEmailSubject' => ['required', 'string', 'max:255'],
            'nfceContadorEmailMessage' => ['required', 'string', 'max:5000'],
        ], [
            'nfceContadorCompetencia.required' => 'Selecione a competência (mês).',
            'nfceContadorCompetencia.regex' => 'Competência inválida.',
            'nfceContadorEmailTo.required' => 'Informe o e-mail do contador.',
            'nfceContadorEmailTo.email' => 'Informe um e-mail válido.',
            'nfceContadorEmailSubject.required' => 'Informe o assunto.',
            'nfceContadorEmailMessage.required' => 'Informe a mensagem.',
        ]);

        $empresa = $this->currentNfceEmpresa();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        $service = app(NfceContadorPacoteService::class);
        $zipPath = null;

        try {
            $pacote = $this->buildNfceContadorPacoteOrNotify($service, $empresa);

            if ($pacote === null) {
                return;
            }

            $zipPath = $pacote['path'];
            $message = $this->nfceContadorMessageWithXmlWarning($pacote);

            FiscalMailService::sendForEmpresa(
                empresaId: (int) $empresa->id,
                to: $this->nfceContadorEmailTo,
                messageBody: $message,
                subjectLine: $this->nfceContadorEmailSubject,
                fileAttachments: [[
                    'path' => $pacote['path'],
                    'name' => $pacote['name'],
                ]],
                fromAddress: $empresa->email ?: null,
                fromName: $empresa->nome ?: null,
            );

            Notification::make()
                ->title('Pacote enviado por e-mail ao contador.')
                ->body(sprintf(
                    '%d nota(s), %d XML(s) — competência %s.',
                    $pacote['totalNotas'],
                    $pacote['totalXml'],
                    $pacote['periodo']['labelShort'],
                ))
                ->success()
                ->send();

            $this->closeNfceContadorEmailModal();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Falha ao enviar o pacote por e-mail.')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->cleanupNfceContadorZip($zipPath);
        }
    }

    public function sendNfceContadorWhatsApp(): void
    {
        $this->nfceContadorEmailMessage = WhatsAppMessageHelper::stripSystemFooter($this->nfceContadorEmailMessage);
        $maxLength = WhatsAppMessageHelper::maxUserMessageLength();

        $this->validate([
            'nfceContadorCompetencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'nfceContadorWhatsAppTo' => ['required', 'string', 'max:30', new CelularBrasileiroValido()],
            'nfceContadorEmailMessage' => ['required', 'string', 'max:'.$maxLength],
        ], [
            'nfceContadorCompetencia.required' => 'Selecione a competência (mês).',
            'nfceContadorCompetencia.regex' => 'Competência inválida.',
            'nfceContadorWhatsAppTo.required' => 'Informe o WhatsApp do contador.',
            'nfceContadorEmailMessage.required' => 'Informe a mensagem.',
        ]);

        $empresa = $this->currentNfceEmpresa();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        $service = app(NfceContadorPacoteService::class);
        $zipPath = null;

        try {
            $pacote = $this->buildNfceContadorPacoteOrNotify($service, $empresa);

            if ($pacote === null) {
                return;
            }

            $zipPath = $pacote['path'];
            $message = $this->nfceContadorMessageWithXmlWarning($pacote);
            $sender = app(WhatsAppSender::class);

            $result = $sender->sendDocumentMessage(
                empresa: $empresa,
                tipo: WhatsAppSender::TIPO_NFCE_CONTADOR,
                number: $this->nfceContadorWhatsAppTo,
                text: $message,
                documentPath: $pacote['path'],
                documentName: $pacote['name'],
                mimetype: 'application/zip',
            );

            if (! $result['ok']) {
                Notification::make()
                    ->title('Não foi possível enviar o WhatsApp.')
                    ->body($result['message'])
                    ->warning()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Pacote enviado por WhatsApp ao contador.')
                ->body(sprintf(
                    '%d nota(s), %d XML(s) — competência %s.',
                    $pacote['totalNotas'],
                    $pacote['totalXml'],
                    $pacote['periodo']['labelShort'],
                ))
                ->success()
                ->send();

            $this->closeNfceContadorEmailModal();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Falha ao enviar o pacote por WhatsApp.')
                ->body('Verifique a conexão em Empresa → Parâmetros → WhatsApp.')
                ->danger()
                ->send();
        } finally {
            $this->cleanupNfceContadorZip($zipPath);
        }
    }

    /**
     * @return array{
     *     path: string,
     *     name: string,
     *     competencia: string,
     *     totalNotas: int,
     *     totalXml: int,
     *     periodo: array{de: string, ate: string, label: string, labelShort: string}
     * }|null
     */
    protected function buildNfceContadorPacoteOrNotify(NfceContadorPacoteService $service, Empresa $empresa): ?array
    {
        $pacote = $service->buildPacoteMensal($empresa, $this->nfceContadorCompetencia);

        if ($pacote['totalNotas'] === 0) {
            Notification::make()
                ->title('Nenhuma NFC-e encontrada no mês selecionado.')
                ->body('O pacote não foi enviado. Verifique a competência ou as notas transmitidas.')
                ->warning()
                ->send();

            if (is_file($pacote['path'])) {
                @unlink($pacote['path']);
            }

            return null;
        }

        return $pacote;
    }

    /**
     * @param  array{totalXml: int, periodo: array{labelShort: string}}  $pacote
     */
    protected function nfceContadorMessageWithXmlWarning(array $pacote): string
    {
        $message = $this->nfceContadorEmailMessage;

        if ($pacote['totalXml'] === 0) {
            $message .= "\n\nAtenção: nenhum XML foi encontrado nas notas do período. O relatório PDF foi incluído no ZIP.";
        }

        return $message;
    }

    protected function formatContadorWhatsAppDisplay(string $phoneDigits): string
    {
        if ($phoneDigits === '') {
            return '';
        }

        if (strlen($phoneDigits) === 11) {
            return WhatsAppPhone::formatDisplay($phoneDigits);
        }

        return WhatsAppPhone::formatDisplay(str_starts_with($phoneDigits, '55') ? $phoneDigits : '55'.$phoneDigits);
    }

    protected function cleanupNfceContadorZip(?string $zipPath): void
    {
        if (is_string($zipPath) && is_file($zipPath)) {
            @unlink($zipPath);
        }
    }

    protected function currentNfceEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }
}
