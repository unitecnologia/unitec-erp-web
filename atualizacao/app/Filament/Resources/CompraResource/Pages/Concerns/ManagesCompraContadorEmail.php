<?php

namespace App\Filament\Resources\CompraResource\Pages\Concerns;

use App\Models\Compra;
use App\Models\Contador;
use App\Models\Empresa;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\Compra\CompraContadorPacoteService;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Reports\NfceRelatorioReportService;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Throwable;

trait ManagesCompraContadorEmail
{
    public bool $compraContadorEmailModalOpen = false;

    public string $compraContadorCompetencia = '';

    public string $compraContadorEmailTo = '';

    public string $compraContadorWhatsAppTo = '';

    public string $compraContadorEmailSubject = '';

    public string $compraContadorEmailMessage = '';

    public function openCompraContadorEmailModal(): void
    {
        if (! $this->erpAuthorizeOrNotify('compras.close_month')) {
            return;
        }

        $empresa = $this->currentCompraContadorEmpresa();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->assertCompraContadorSemPendencias($empresa)) {
            return;
        }

        $service = app(CompraContadorPacoteService::class);
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

        $this->compraContadorCompetencia = $competencia;
        $this->compraContadorEmailTo = $email;
        $this->compraContadorWhatsAppTo = $this->formatCompraContadorWhatsAppDisplay($phoneDigits);
        $this->compraContadorEmailSubject = $service->defaultEmailSubject($empresa, $periodo);
        $this->compraContadorEmailMessage = $service->defaultEmailMessage($empresa, $periodo, 0, 0);
        $this->compraContadorEmailModalOpen = true;
    }

    public function closeCompraContadorEmailModal(): void
    {
        $this->compraContadorEmailModalOpen = false;
        $this->compraContadorWhatsAppTo = '';
    }

    public function compraContadorPacoteAnexoLabel(): string
    {
        $empresa = $this->currentCompraContadorEmpresa();

        if (! $empresa || ! preg_match('/^\d{4}-\d{2}$/', $this->compraContadorCompetencia)) {
            return 'PACOTE COMPRAS.ZIP';
        }

        return strtoupper(app(CompraContadorPacoteService::class)->expectedZipFileName($empresa, $this->compraContadorCompetencia));
    }

    public function updatedCompraContadorCompetencia(): void
    {
        $empresa = $this->currentCompraContadorEmpresa();

        if (! $empresa || ! preg_match('/^\d{4}-\d{2}$/', $this->compraContadorCompetencia)) {
            return;
        }

        $service = app(CompraContadorPacoteService::class);
        $periodo = NfceRelatorioReportService::competenciaPeriod($this->compraContadorCompetencia);

        $this->compraContadorEmailSubject = $service->defaultEmailSubject($empresa, $periodo);
        $this->compraContadorEmailMessage = $service->defaultEmailMessage($empresa, $periodo, 0, 0);
    }

    public function updatedCompraContadorEmailMessage(string $value): void
    {
        $clean = WhatsAppMessageHelper::stripSystemFooter($value);

        if ($clean !== $value) {
            $this->compraContadorEmailMessage = $clean;
        }
    }

    public function sendCompraContadorEmail(): void
    {
        $this->validate([
            'compraContadorCompetencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'compraContadorEmailTo' => ['required', 'email'],
            'compraContadorEmailSubject' => ['required', 'string', 'max:255'],
            'compraContadorEmailMessage' => ['required', 'string', 'max:5000'],
        ], [
            'compraContadorCompetencia.required' => 'Selecione a competência (mês).',
            'compraContadorCompetencia.regex' => 'Competência inválida.',
            'compraContadorEmailTo.required' => 'Informe o e-mail do contador.',
            'compraContadorEmailTo.email' => 'Informe um e-mail válido.',
            'compraContadorEmailSubject.required' => 'Informe o assunto.',
            'compraContadorEmailMessage.required' => 'Informe a mensagem.',
        ]);

        $empresa = $this->currentCompraContadorEmpresa();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        $service = app(CompraContadorPacoteService::class);
        $zipPath = null;

        try {
            $pacote = $this->buildCompraContadorPacoteOrNotify($service, $empresa);

            if ($pacote === null) {
                return;
            }

            $zipPath = $pacote['path'];
            $message = $this->compraContadorMessageWithXmlWarning($pacote);

            FiscalMailService::sendForEmpresa(
                empresaId: (int) $empresa->id,
                to: $this->compraContadorEmailTo,
                messageBody: $message,
                subjectLine: $this->compraContadorEmailSubject,
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

            $this->closeCompraContadorEmailModal();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Falha ao enviar o pacote por e-mail.')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->cleanupCompraContadorZip($zipPath);
        }
    }

    public function sendCompraContadorWhatsApp(): void
    {
        $this->compraContadorEmailMessage = WhatsAppMessageHelper::stripSystemFooter($this->compraContadorEmailMessage);
        $maxLength = WhatsAppMessageHelper::maxUserMessageLength();

        $this->validate([
            'compraContadorCompetencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'compraContadorWhatsAppTo' => ['required', 'string', 'max:30', new CelularBrasileiroValido()],
            'compraContadorEmailMessage' => ['required', 'string', 'max:'.$maxLength],
        ], [
            'compraContadorCompetencia.required' => 'Selecione a competência (mês).',
            'compraContadorCompetencia.regex' => 'Competência inválida.',
            'compraContadorWhatsAppTo.required' => 'Informe o WhatsApp do contador.',
            'compraContadorEmailMessage.required' => 'Informe a mensagem.',
        ]);

        $empresa = $this->currentCompraContadorEmpresa();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada na sessão.')
                ->warning()
                ->send();

            return;
        }

        $service = app(CompraContadorPacoteService::class);
        $zipPath = null;

        try {
            $pacote = $this->buildCompraContadorPacoteOrNotify($service, $empresa);

            if ($pacote === null) {
                return;
            }

            $zipPath = $pacote['path'];
            $message = $this->compraContadorMessageWithXmlWarning($pacote);
            $sender = app(WhatsAppSender::class);

            $result = $sender->sendDocumentMessage(
                empresa: $empresa,
                tipo: WhatsAppSender::TIPO_COMPRA_CONTADOR,
                number: $this->compraContadorWhatsAppTo,
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

            $this->closeCompraContadorEmailModal();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Falha ao enviar o pacote por WhatsApp.')
                ->body('Verifique a conexão em Empresa → Parâmetros → WhatsApp.')
                ->danger()
                ->send();
        } finally {
            $this->cleanupCompraContadorZip($zipPath);
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
    protected function buildCompraContadorPacoteOrNotify(CompraContadorPacoteService $service, Empresa $empresa): ?array
    {
        if (! $this->assertCompraContadorSemPendencias($empresa)) {
            return null;
        }

        $pacote = $service->buildPacoteMensal($empresa, $this->compraContadorCompetencia);

        if ($pacote['totalNotas'] === 0) {
            Notification::make()
                ->title('Nenhuma compra encontrada no mês selecionado.')
                ->body('O pacote não foi enviado. Verifique a competência ou as notas finalizadas.')
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
    protected function compraContadorMessageWithXmlWarning(array $pacote): string
    {
        $message = $this->compraContadorEmailMessage;

        if ($pacote['totalXml'] === 0) {
            $message .= "\n\nAtenção: nenhum XML foi encontrado nas notas do período. O relatório PDF foi incluído no ZIP.";
        }

        return $message;
    }

    protected function formatCompraContadorWhatsAppDisplay(string $phoneDigits): string
    {
        if ($phoneDigits === '') {
            return '';
        }

        if (strlen($phoneDigits) === 11) {
            return WhatsAppPhone::formatDisplay($phoneDigits);
        }

        return WhatsAppPhone::formatDisplay(str_starts_with($phoneDigits, '55') ? $phoneDigits : '55'.$phoneDigits);
    }

    protected function cleanupCompraContadorZip(?string $zipPath): void
    {
        if (is_string($zipPath) && is_file($zipPath)) {
            @unlink($zipPath);
        }
    }

    protected function currentCompraContadorEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }

    /**
     * Bloqueia o pacote enquanto houver compras em aberto.
     */
    protected function assertCompraContadorSemPendencias(Empresa $empresa): bool
    {
        $abertas = Compra::query()
            ->where('empresa_id', $empresa->id)
            ->where('status', Compra::STATUS_ABERTA)
            ->count();

        if ($abertas === 0) {
            return true;
        }

        Notification::make()
            ->title('Não é possível fechar o mês de compras.')
            ->body($abertas === 1
                ? 'Existe 1 compra em aberto. Finalize ou cancele antes de enviar o pacote ao contador.'
                : "Existem {$abertas} compras em aberto. Finalize ou cancele antes de enviar o pacote ao contador.")
            ->warning()
            ->send();

        return false;
    }
}
