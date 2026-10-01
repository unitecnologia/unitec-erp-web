<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Contador;
use App\Models\Empresa;
use App\Models\Nfse;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Nfse\NfseContadorPacoteService;
use App\Support\Erp\Reports\NfceRelatorioReportService;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Throwable;

trait ManagesNfseContadorEmail
{
    public bool $nfseContadorEmailModalOpen = false;

    public bool $nfseContadorPendenciaAvisoOpen = false;

    /** @var list<string> */
    public array $nfseContadorPendenciaAvisoLines = [];

    public string $nfseContadorCompetencia = '';

    public string $nfseContadorEmailTo = '';

    public string $nfseContadorWhatsAppTo = '';

    public string $nfseContadorEmailSubject = '';

    public string $nfseContadorEmailMessage = '';

    public function openNfseContadorEmailModal(): void
    {
        $empresa = $this->currentNfseEmpresaForContador();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        $service = app(NfseContadorPacoteService::class);
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

        if (! $this->assertNfseContadorSemPendencias($empresa, $competencia)) {
            return;
        }

        $periodo = NfceRelatorioReportService::competenciaPeriod($competencia);

        $this->nfseContadorCompetencia = $competencia;
        $this->nfseContadorEmailTo = $email;
        $this->nfseContadorWhatsAppTo = $this->formatNfseContadorWhatsAppDisplay($phoneDigits);
        $this->nfseContadorEmailSubject = $service->defaultEmailSubject($empresa, $periodo);
        $this->nfseContadorEmailMessage = $service->defaultEmailMessage($empresa, $periodo, 0, 0);
        $this->nfseContadorEmailModalOpen = true;
    }

    public function closeNfseContadorEmailModal(): void
    {
        $this->nfseContadorEmailModalOpen = false;
        $this->nfseContadorWhatsAppTo = '';
    }

    public function closeNfseContadorPendenciaAviso(): void
    {
        $this->nfseContadorPendenciaAvisoOpen = false;
        $this->nfseContadorPendenciaAvisoLines = [];
    }

    public function nfseContadorPacoteAnexoLabel(): string
    {
        $empresa = $this->currentNfseEmpresaForContador();

        if (! $empresa || ! preg_match('/^\d{4}-\d{2}$/', $this->nfseContadorCompetencia)) {
            return 'PACOTE NFSE.ZIP';
        }

        return strtoupper(app(NfseContadorPacoteService::class)->expectedZipFileName($empresa, $this->nfseContadorCompetencia));
    }

    public function updatedNfseContadorCompetencia(): void
    {
        $empresa = $this->currentNfseEmpresaForContador();

        if (! $empresa || ! preg_match('/^\d{4}-\d{2}$/', $this->nfseContadorCompetencia)) {
            return;
        }

        $service = app(NfseContadorPacoteService::class);
        $periodo = NfceRelatorioReportService::competenciaPeriod($this->nfseContadorCompetencia);
        $this->nfseContadorEmailSubject = $service->defaultEmailSubject($empresa, $periodo);
        $this->nfseContadorEmailMessage = $service->defaultEmailMessage($empresa, $periodo, 0, 0);
    }

    public function updatedNfseContadorEmailMessage(string $value): void
    {
        $clean = WhatsAppMessageHelper::stripSystemFooter($value);

        if ($clean !== $value) {
            $this->nfseContadorEmailMessage = $clean;
        }
    }

    public function sendNfseContadorEmail(): void
    {
        $this->validate([
            'nfseContadorCompetencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'nfseContadorEmailTo' => ['required', 'email'],
            'nfseContadorEmailSubject' => ['required', 'string', 'max:255'],
            'nfseContadorEmailMessage' => ['required', 'string', 'max:5000'],
        ], [
            'nfseContadorCompetencia.required' => 'Selecione a competência (mês).',
            'nfseContadorCompetencia.regex' => 'Competência inválida.',
            'nfseContadorEmailTo.required' => 'Informe o e-mail do contador.',
            'nfseContadorEmailTo.email' => 'Informe um e-mail válido.',
            'nfseContadorEmailSubject.required' => 'Informe o assunto.',
            'nfseContadorEmailMessage.required' => 'Informe a mensagem.',
        ]);

        $empresa = $this->currentNfseEmpresaForContador();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        $service = app(NfseContadorPacoteService::class);
        $zipPath = null;

        try {
            $pacote = $this->buildNfseContadorPacoteOrNotify($service, $empresa);

            if ($pacote === null) {
                return;
            }

            $zipPath = $pacote['path'];
            $message = $this->nfseContadorMessageWithXmlWarning($pacote);

            FiscalMailService::sendForEmpresa(
                empresaId: (int) $empresa->id,
                to: $this->nfseContadorEmailTo,
                messageBody: $message,
                subjectLine: $this->nfseContadorEmailSubject,
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

            $this->closeNfseContadorEmailModal();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Falha ao enviar o pacote por e-mail.')
                ->body('Verifique a configuração de e-mail em Empresa → Parâmetros → E-mail.')
                ->danger()
                ->send();
        } finally {
            $this->cleanupNfseContadorZip($zipPath);
        }
    }

    public function sendNfseContadorWhatsApp(): void
    {
        $this->nfseContadorEmailMessage = WhatsAppMessageHelper::stripSystemFooter($this->nfseContadorEmailMessage);
        $maxLength = WhatsAppMessageHelper::maxUserMessageLength();

        $this->validate([
            'nfseContadorCompetencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'nfseContadorWhatsAppTo' => ['required', 'string', 'max:30', new CelularBrasileiroValido()],
            'nfseContadorEmailMessage' => ['required', 'string', 'max:'.$maxLength],
        ], [
            'nfseContadorCompetencia.required' => 'Selecione a competência (mês).',
            'nfseContadorCompetencia.regex' => 'Competência inválida.',
            'nfseContadorWhatsAppTo.required' => 'Informe o WhatsApp do contador.',
            'nfseContadorEmailMessage.required' => 'Informe a mensagem.',
        ]);

        $empresa = $this->currentNfseEmpresaForContador();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        $service = app(NfseContadorPacoteService::class);
        $zipPath = null;

        try {
            $pacote = $this->buildNfseContadorPacoteOrNotify($service, $empresa);

            if ($pacote === null) {
                return;
            }

            $zipPath = $pacote['path'];
            $message = $this->nfseContadorMessageWithXmlWarning($pacote);
            $sender = app(WhatsAppSender::class);

            $result = $sender->sendDocumentMessage(
                empresa: $empresa,
                tipo: WhatsAppSender::TIPO_NFSE_CONTADOR,
                number: $this->nfseContadorWhatsAppTo,
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

            $this->closeNfseContadorEmailModal();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Falha ao enviar o pacote por WhatsApp.')
                ->body('Verifique a conexão em Empresa → Parâmetros → WhatsApp.')
                ->danger()
                ->send();
        } finally {
            $this->cleanupNfseContadorZip($zipPath);
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
    protected function buildNfseContadorPacoteOrNotify(NfseContadorPacoteService $service, Empresa $empresa): ?array
    {
        if (! $this->assertNfseContadorSemPendencias($empresa, $this->nfseContadorCompetencia)) {
            return null;
        }

        $pacote = $service->buildPacoteMensal($empresa, $this->nfseContadorCompetencia);

        if ($pacote['totalNotas'] === 0) {
            Notification::make()
                ->title('Nenhuma NFS-e encontrada no mês selecionado.')
                ->body('O pacote não foi enviado. Verifique a competência ou as notas autorizadas.')
                ->warning()
                ->send();

            if (is_file($pacote['path'])) {
                @unlink($pacote['path']);
            }

            return null;
        }

        return $pacote;
    }

    protected function assertNfseContadorSemPendencias(Empresa $empresa, string $competencia): bool
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $competencia)) {
            return true;
        }

        $periodo = NfceRelatorioReportService::competenciaPeriod($competencia);
        $pendentes = (int) Nfse::query()
            ->where('empresa_id', (int) $empresa->id)
            ->whereIn('status', [
                Nfse::STATUS_ABERTA,
                Nfse::STATUS_REJEITADA,
                Nfse::STATUS_CONTINGENCIA,
            ])
            ->where(function ($query) use ($periodo): void {
                $query->whereBetween('data_emissao', [$periodo['de'], $periodo['ate']])
                    ->orWhere(function ($fallback) use ($periodo): void {
                        $fallback->whereNull('data_emissao')
                            ->whereBetween('competencia', [$periodo['de'], $periodo['ate']]);
                    });
            })
            ->count();

        if ($pendentes === 0) {
            return true;
        }

        $competenciaLabel = Carbon::createFromFormat('Y-m', $competencia)->format('m/Y');
        $this->nfseContadorPendenciaAvisoLines = [
            "Na competência <strong>{$competenciaLabel}</strong> há <strong>{$pendentes} NFS-e pendente(s)</strong> (aberta, rejeitada ou em contingência).",
            'Resolva essas notas antes de enviar o pacote ao contador.',
        ];
        $this->nfseContadorPendenciaAvisoOpen = true;

        return false;
    }

    /**
     * @param  array{totalXml: int, periodo: array{labelShort: string}}  $pacote
     */
    protected function nfseContadorMessageWithXmlWarning(array $pacote): string
    {
        $message = $this->nfseContadorEmailMessage;

        if ($pacote['totalXml'] === 0) {
            $message .= "\n\nAtenção: nenhum XML foi encontrado nas notas do período. O relatório PDF foi incluído no ZIP.";
        }

        return $message;
    }

    protected function formatNfseContadorWhatsAppDisplay(string $phoneDigits): string
    {
        if ($phoneDigits === '') {
            return '';
        }

        if (strlen($phoneDigits) === 11) {
            return WhatsAppPhone::formatDisplay($phoneDigits);
        }

        return WhatsAppPhone::formatDisplay(str_starts_with($phoneDigits, '55') ? $phoneDigits : '55'.$phoneDigits);
    }

    protected function cleanupNfseContadorZip(?string $zipPath): void
    {
        if (is_string($zipPath) && is_file($zipPath)) {
            @unlink($zipPath);
        }
    }

    protected function currentNfseEmpresaForContador(): ?Empresa
    {
        return ErpContext::currentEmpresa();
    }
}
