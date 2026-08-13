<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Contador;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Nfe\NfeContadorPacoteService;
use App\Support\Erp\Reports\NfceRelatorioReportService;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Throwable;

trait ManagesNfeContadorEmail
{
    public bool $nfeContadorEmailModalOpen = false;

    public string $nfeContadorCompetencia = '';

    public string $nfeContadorEmailTo = '';

    public string $nfeContadorWhatsAppTo = '';

    public string $nfeContadorEmailSubject = '';

    public string $nfeContadorEmailMessage = '';

    public function openNfeContadorEmailModal(): void
    {
        if (! $this->erpAuthorizeOrNotify('nfe.access')) {
            return;
        }

        $empresa = $this->currentNfeContadorEmpresa();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->assertNfeContadorSemPendencias($empresa)) {
            return;
        }

        $service = app(NfeContadorPacoteService::class);
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

        $this->nfeContadorCompetencia = $competencia;
        $this->nfeContadorEmailTo = $email;
        $this->nfeContadorWhatsAppTo = $this->formatNfeContadorWhatsAppDisplay($phoneDigits);
        $this->nfeContadorEmailSubject = $service->defaultEmailSubject($empresa, $periodo);
        $this->nfeContadorEmailMessage = $service->defaultEmailMessage($empresa, $periodo, 0, 0);
        $this->nfeContadorEmailModalOpen = true;
    }

    public function closeNfeContadorEmailModal(): void
    {
        $this->nfeContadorEmailModalOpen = false;
        $this->nfeContadorWhatsAppTo = '';
    }

    public function nfeContadorPacoteAnexoLabel(): string
    {
        $empresa = $this->currentNfeContadorEmpresa();

        if (! $empresa || ! preg_match('/^\d{4}-\d{2}$/', $this->nfeContadorCompetencia)) {
            return 'PACOTE NFE.ZIP';
        }

        return strtoupper(app(NfeContadorPacoteService::class)->expectedZipFileName($empresa, $this->nfeContadorCompetencia));
    }

    public function updatedNfeContadorCompetencia(): void
    {
        $empresa = $this->currentNfeContadorEmpresa();

        if (! $empresa || ! preg_match('/^\d{4}-\d{2}$/', $this->nfeContadorCompetencia)) {
            return;
        }

        $service = app(NfeContadorPacoteService::class);
        $periodo = NfceRelatorioReportService::competenciaPeriod($this->nfeContadorCompetencia);

        $this->nfeContadorEmailSubject = $service->defaultEmailSubject($empresa, $periodo);
        $this->nfeContadorEmailMessage = $service->defaultEmailMessage($empresa, $periodo, 0, 0);
    }

    public function updatedNfeContadorEmailMessage(string $value): void
    {
        $clean = WhatsAppMessageHelper::stripSystemFooter($value);

        if ($clean !== $value) {
            $this->nfeContadorEmailMessage = $clean;
        }
    }

    public function sendNfeContadorEmail(): void
    {
        $this->validate([
            'nfeContadorCompetencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'nfeContadorEmailTo' => ['required', 'email'],
            'nfeContadorEmailSubject' => ['required', 'string', 'max:255'],
            'nfeContadorEmailMessage' => ['required', 'string', 'max:5000'],
        ], [
            'nfeContadorCompetencia.required' => 'Selecione a competência (mês).',
            'nfeContadorCompetencia.regex' => 'Competência inválida.',
            'nfeContadorEmailTo.required' => 'Informe o e-mail do contador.',
            'nfeContadorEmailTo.email' => 'Informe um e-mail válido.',
            'nfeContadorEmailSubject.required' => 'Informe o assunto.',
            'nfeContadorEmailMessage.required' => 'Informe a mensagem.',
        ]);

        $empresa = $this->currentNfeContadorEmpresa();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        $service = app(NfeContadorPacoteService::class);
        $zipPath = null;

        try {
            $pacote = $this->buildNfeContadorPacoteOrNotify($service, $empresa);

            if ($pacote === null) {
                return;
            }

            $zipPath = $pacote['path'];
            $message = $this->nfeContadorMessageWithXmlWarning($pacote);

            FiscalMailService::sendForEmpresa(
                empresaId: (int) $empresa->id,
                to: $this->nfeContadorEmailTo,
                messageBody: $message,
                subjectLine: $this->nfeContadorEmailSubject,
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

            $this->closeNfeContadorEmailModal();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Falha ao enviar o pacote por e-mail.')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->cleanupNfeContadorZip($zipPath);
        }
    }

    public function sendNfeContadorWhatsApp(): void
    {
        $this->nfeContadorEmailMessage = WhatsAppMessageHelper::stripSystemFooter($this->nfeContadorEmailMessage);
        $maxLength = WhatsAppMessageHelper::maxUserMessageLength();

        $this->validate([
            'nfeContadorCompetencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'nfeContadorWhatsAppTo' => ['required', 'string', 'max:30', new CelularBrasileiroValido()],
            'nfeContadorEmailMessage' => ['required', 'string', 'max:'.$maxLength],
        ], [
            'nfeContadorCompetencia.required' => 'Selecione a competência (mês).',
            'nfeContadorCompetencia.regex' => 'Competência inválida.',
            'nfeContadorWhatsAppTo.required' => 'Informe o WhatsApp do contador.',
            'nfeContadorEmailMessage.required' => 'Informe a mensagem.',
        ]);

        $empresa = $this->currentNfeContadorEmpresa();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        $service = app(NfeContadorPacoteService::class);
        $zipPath = null;

        try {
            $pacote = $this->buildNfeContadorPacoteOrNotify($service, $empresa);

            if ($pacote === null) {
                return;
            }

            $zipPath = $pacote['path'];
            $message = $this->nfeContadorMessageWithXmlWarning($pacote);
            $sender = app(WhatsAppSender::class);

            $result = $sender->sendDocumentMessage(
                empresa: $empresa,
                tipo: WhatsAppSender::TIPO_NFE_CONTADOR,
                number: $this->nfeContadorWhatsAppTo,
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

            $this->closeNfeContadorEmailModal();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Falha ao enviar o pacote por WhatsApp.')
                ->body('Verifique a conexão em Empresa → Parâmetros → WhatsApp.')
                ->danger()
                ->send();
        } finally {
            $this->cleanupNfeContadorZip($zipPath);
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
    protected function buildNfeContadorPacoteOrNotify(NfeContadorPacoteService $service, Empresa $empresa): ?array
    {
        if (! $this->assertNfeContadorSemPendencias($empresa)) {
            return null;
        }

        $pacote = $service->buildPacoteMensal($empresa, $this->nfeContadorCompetencia);

        if ($pacote['totalNotas'] === 0) {
            Notification::make()
                ->title('Nenhuma NF-e encontrada no mês selecionado.')
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
    protected function nfeContadorMessageWithXmlWarning(array $pacote): string
    {
        $message = $this->nfeContadorEmailMessage;

        if ($pacote['totalXml'] === 0) {
            $message .= "\n\nAtenção: nenhum XML foi encontrado nas notas do período. O relatório PDF foi incluído no ZIP.";
        }

        return $message;
    }

    protected function formatNfeContadorWhatsAppDisplay(string $phoneDigits): string
    {
        if ($phoneDigits === '') {
            return '';
        }

        if (strlen($phoneDigits) === 11) {
            return WhatsAppPhone::formatDisplay($phoneDigits);
        }

        return WhatsAppPhone::formatDisplay(str_starts_with($phoneDigits, '55') ? $phoneDigits : '55'.$phoneDigits);
    }

    protected function cleanupNfeContadorZip(?string $zipPath): void
    {
        if (is_string($zipPath) && is_file($zipPath)) {
            @unlink($zipPath);
        }
    }

    protected function currentNfeContadorEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }

    /**
     * Bloqueia o pacote do contador enquanto houver NF-e em aberto ou em contingência.
     */
    protected function assertNfeContadorSemPendencias(Empresa $empresa): bool
    {
        $abertas = Nfe::query()
            ->where('empresa_id', $empresa->id)
            ->where('status', Nfe::STATUS_ABERTA)
            ->count();

        $contingencia = Nfe::query()
            ->where('empresa_id', $empresa->id)
            ->where('status', Nfe::STATUS_CONTINGENCIA)
            ->count();

        if ($abertas === 0 && $contingencia === 0) {
            return true;
        }

        $partes = [];

        if ($abertas > 0) {
            $partes[] = $abertas === 1
                ? '1 NF-e em aberto'
                : "{$abertas} NF-e em aberto";
        }

        if ($contingencia > 0) {
            $partes[] = $contingencia === 1
                ? '1 NF-e em contingência'
                : "{$contingencia} NF-e em contingência";
        }

        Notification::make()
            ->title('Não é possível gerar o PDF do contador.')
            ->body('Existem pendências: '.implode(' e ', $partes).'. Resolva as notas em aberto ou em contingência antes de gerar o pacote.')
            ->warning()
            ->send();

        return false;
    }
}
