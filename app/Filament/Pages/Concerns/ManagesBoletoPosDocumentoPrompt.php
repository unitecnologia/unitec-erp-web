<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Boleto;
use App\Models\BoletoContaApi;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\Boleto\BoletoContaApiResolver;
use App\Support\Erp\Boleto\BoletoEnvioService;
use App\Support\Erp\Boleto\BoletoPosDocumentoEmissionService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;
use Illuminate\Support\Js;

/**
 * Após faturar/finalizar documento com CR boleto: escolhe conta e emite.
 * Overlay de sucesso = mesmo padrão de Contas a Receber.
 */
trait ManagesBoletoPosDocumentoPrompt
{
    public bool $boletoPosDocumentoPromptOpen = false;

    /** @var list<int> */
    public array $boletoPosDocumentoContaIds = [];

    public ?string $boletoPosDocumentoRedirectUrl = null;

    public bool $boletoPosDocumentoNavigate = false;

    public bool $boletoContaPickOpen = false;

    public ?int $boletoContaPickSelectedId = null;

    /** @var list<array{id: int, rotulo: string, padrao: bool}> */
    public array $boletoContaPickOptions = [];

    public ?int $boletoSucessoId = null;

    public string $boletoSucessoDetalhe = '';

    public bool $boletoEnviarModalOpen = false;

    public ?int $boletoEnviarId = null;

    public string $boletoEnviarEmail = '';

    public string $boletoEnviarWhatsApp = '';

    public string $boletoEnviarAssunto = '';

    public string $boletoEnviarMensagem = '';

    /** @var list<array{path: string, name: string, display?: string, mimetype?: string, owned?: bool}> */
    public array $boletoEnviarAttachments = [];

    /**
     * Abre direto a escolha de conta (Gerar / Cancelar). Sem pergunta Sim/Não.
     *
     * @param  list<ContaReceber|int>  $contas
     */
    protected function offerEmitirBoletosPosDocumento(
        array $contas,
        ?string $redirectUrl = null,
        bool $navigate = false,
    ): bool {
        $boletos = app(BoletoPosDocumentoEmissionService::class)->filtrarBoletos($contas);

        if ($boletos === []) {
            return false;
        }

        $empresa = $boletos[0]->empresa_id
            ? Empresa::query()->find($boletos[0]->empresa_id)
            : ErpContext::currentEmpresa();

        if (! $empresa instanceof Empresa) {
            return false;
        }

        if (! filter_var($empresa->param_boleto_habilitar ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $ativas = app(BoletoContaApiResolver::class)->ativas($empresa);

        if ($ativas->isEmpty()) {
            return false;
        }

        if (! ErpAccess::currentCan('boletos.create')) {
            Notification::make()
                ->title('Sem permissão para gerar boletos.')
                ->warning()
                ->send();

            return false;
        }

        $this->boletoPosDocumentoContaIds = array_map(
            fn (ContaReceber $c): int => (int) $c->id,
            $boletos,
        );
        $this->boletoPosDocumentoRedirectUrl = $redirectUrl;
        $this->boletoPosDocumentoNavigate = $navigate;
        $this->boletoPosDocumentoPromptOpen = false;

        $padrao = $ativas->firstWhere('padrao', true) ?? $ativas->first();
        $this->boletoContaPickSelectedId = (int) $padrao->id;
        $this->boletoContaPickOptions = $ativas
            ->map(fn (BoletoContaApi $c): array => [
                'id' => (int) $c->id,
                'rotulo' => $c->rotulo(),
                'padrao' => (bool) $c->padrao,
            ])
            ->values()
            ->all();
        $this->boletoContaPickOpen = true;

        return true;
    }

    public function closeBoletoContaPickModal(): void
    {
        $this->boletoContaPickOpen = false;
        $this->boletoContaPickSelectedId = null;
        $this->boletoContaPickOptions = [];
        $this->finalizarBoletoPosDocumentoFluxo();
    }

    public function confirmarGerarBoletoComConta(): void
    {
        $contaApiId = (int) ($this->boletoContaPickSelectedId ?? 0);

        if ($contaApiId <= 0) {
            Notification::make()->title('Selecione a conta de cobrança.')->warning()->send();

            return;
        }

        $this->boletoContaPickOpen = false;
        $this->emitirBoletosPosDocumentoComConta($contaApiId);
    }

    protected function emitirBoletosPosDocumentoComConta(int $contaApiId): void
    {
        $primeira = ContaReceber::query()->find($this->boletoPosDocumentoContaIds[0] ?? 0);
        $empresa = $primeira?->empresa_id
            ? Empresa::query()->find($primeira->empresa_id)
            : ErpContext::currentEmpresa();

        if (! $empresa instanceof Empresa) {
            Notification::make()->title('Empresa não encontrada.')->warning()->send();
            $this->finalizarBoletoPosDocumentoFluxo();

            return;
        }

        try {
            $contaApi = app(BoletoContaApiResolver::class)->findAtiva($empresa, $contaApiId);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Conta de cobrança inválida')
                ->body($e->getMessage())
                ->warning()
                ->send();
            $this->finalizarBoletoPosDocumentoFluxo();

            return;
        }

        Notification::make()
            ->title('Gerando boleto(s)…')
            ->body('Aguarde a autenticação e o registro no banco.')
            ->info()
            ->send();

        $resultado = app(BoletoPosDocumentoEmissionService::class)
            ->emitirVarias($this->boletoPosDocumentoContaIds, $contaApi, $empresa);

        foreach ($resultado['erros'] as $erro) {
            Notification::make()
                ->title('Falha em uma parcela')
                ->body($erro)
                ->danger()
                ->send();
        }

        $boletos = $resultado['boletos'];

        if ($boletos === []) {
            Notification::make()
                ->title('Nenhum boleto foi gerado.')
                ->warning()
                ->send();
            $this->finalizarBoletoPosDocumentoFluxo();

            return;
        }

        $primeiro = $boletos[0];
        $this->boletoSucessoId = (int) $primeiro->id;
        $this->boletoSucessoDetalhe = app(BoletoEnvioService::class)->sucessoDetalhe($primeiro)
            .(count($boletos) > 1 ? ' ('.count($boletos).' boletos)' : '');
        $this->boletoEnviarModalOpen = false;

        Notification::make()
            ->title(count($boletos) === 1 ? 'Boleto gerado.' : count($boletos).' boletos gerados.')
            ->success()
            ->send();
    }

    public function acknowledgeBoletoSucessoOverlay(): void
    {
        $this->boletoSucessoId = null;
        $this->boletoSucessoDetalhe = '';
        $this->closeBoletoEnviarModal();
        $this->finalizarBoletoPosDocumentoFluxo();
    }

    public function printBoletoSucesso(): void
    {
        $boletoId = (int) ($this->boletoSucessoId ?? 0);
        if ($boletoId <= 0) {
            Notification::make()->title('Boleto não encontrado para impressão.')->warning()->send();

            return;
        }

        $url = route('erp.reports.boleto-pdf', ['boleto' => $boletoId]);
        $this->js('window.ErpBoletoPrint?.openPdf('.Js::from($url).')');
    }

    public function openBoletoEnviarModal(): void
    {
        $boletoId = (int) ($this->boletoSucessoId ?? $this->boletoEnviarId ?? 0);
        if ($boletoId <= 0) {
            Notification::make()->title('Boleto não encontrado para envio.')->warning()->send();

            return;
        }

        $boleto = Boleto::query()->with(['contaReceber.cliente', 'person'])->find($boletoId);
        if (! $boleto) {
            Notification::make()->title('Boleto não encontrado.')->warning()->send();

            return;
        }

        $empresa = $this->empresaOverlayParaBoletoPosDocumento($boleto);
        if (! $empresa instanceof Empresa) {
            Notification::make()->title('Empresa não encontrada.')->warning()->send();

            return;
        }

        $this->cleanupBoletoEnviarAttachmentsPosDocumento();

        try {
            $envio = app(BoletoEnvioService::class);
            $attachments = $envio->buildDispatchAttachments($boleto, $empresa);
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('Não foi possível preparar os anexos do boleto.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $conta = $boleto->contaReceber;
        $cliente = $conta?->cliente ?? $boleto->person;
        $clienteNome = trim((string) ($cliente?->nome_razao ?? $cliente?->nome ?? ''));
        $phoneRaw = $cliente?->celular1 ?: ($cliente?->fone1 ?: '');

        $this->boletoEnviarId = (int) $boleto->id;
        $this->boletoEnviarEmail = trim((string) ($cliente?->email ?? ''));
        $this->boletoEnviarWhatsApp = WhatsAppPhone::formatDisplay($phoneRaw);
        $this->boletoEnviarAssunto = $envio->defaultEmailSubject($boleto);
        $this->boletoEnviarMensagem = $conta instanceof ContaReceber
            ? $envio->defaultEmailMessage($boleto, $conta, $clienteNome)
            : ('Olá'.($clienteNome !== '' ? ', '.$clienteNome : '')."!\n\nSegue em anexo o boleto para pagamento.");
        $this->boletoEnviarAttachments = $attachments;
        $this->boletoEnviarModalOpen = true;

        $this->dispatch('erp-masks-refresh');
    }

    public function closeBoletoEnviarModal(): void
    {
        $this->boletoEnviarModalOpen = false;
        $this->boletoEnviarId = null;
        $this->boletoEnviarEmail = '';
        $this->boletoEnviarWhatsApp = '';
        $this->boletoEnviarAssunto = '';
        $this->boletoEnviarMensagem = '';
        $this->cleanupBoletoEnviarAttachmentsPosDocumento();
    }

    public function updatedBoletoEnviarMensagem(string $value): void
    {
        $clean = WhatsAppMessageHelper::stripSystemFooter($value);
        if ($clean !== $value) {
            $this->boletoEnviarMensagem = $clean;
        }
    }

    public function sendBoletoEmail(): void
    {
        $this->validate([
            'boletoEnviarEmail' => ['required', 'email'],
            'boletoEnviarAssunto' => ['required', 'string', 'max:255'],
            'boletoEnviarMensagem' => ['required', 'string', 'max:5000'],
        ], [
            'boletoEnviarEmail.required' => 'Informe o e-mail do destinatário.',
            'boletoEnviarEmail.email' => 'Informe um e-mail válido.',
            'boletoEnviarAssunto.required' => 'Informe o assunto.',
            'boletoEnviarMensagem.required' => 'Informe a mensagem.',
        ]);

        if ($this->boletoEnviarAttachments === []) {
            Notification::make()->title('Anexos do boleto não encontrados.')->warning()->send();

            return;
        }

        $boleto = Boleto::query()->find($this->boletoEnviarId);
        if (! $boleto) {
            Notification::make()->title('Boleto não encontrado.')->warning()->send();

            return;
        }

        $empresa = $this->empresaOverlayParaBoletoPosDocumento($boleto);
        if (! $empresa instanceof Empresa) {
            Notification::make()->title('Empresa não identificada.')->warning()->send();

            return;
        }

        try {
            FiscalMailService::sendForEmpresa(
                empresaId: (int) ($boleto->empresa_id ?: $empresa->id),
                to: $this->boletoEnviarEmail,
                messageBody: $this->boletoEnviarMensagem,
                subjectLine: $this->boletoEnviarAssunto,
                fileAttachments: collect($this->boletoEnviarAttachments)
                    ->map(fn (array $a): array => [
                        'path' => $a['path'],
                        'name' => $a['name'],
                    ])
                    ->all(),
                fromAddress: $empresa->email ?: null,
                fromName: $empresa->nome ?: null,
            );
        } catch (\Throwable $e) {
            report($e);
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

    public function sendBoletoWhatsApp(): void
    {
        $this->boletoEnviarMensagem = WhatsAppMessageHelper::stripSystemFooter($this->boletoEnviarMensagem);
        $maxLength = WhatsAppMessageHelper::maxUserMessageLength();

        $this->validate([
            'boletoEnviarWhatsApp' => ['required', 'string', 'max:30', new CelularBrasileiroValido],
            'boletoEnviarMensagem' => ['required', 'string', 'max:'.$maxLength],
        ], [
            'boletoEnviarWhatsApp.required' => 'Informe o WhatsApp do destinatário.',
            'boletoEnviarMensagem.required' => 'Informe a mensagem.',
        ]);

        $documents = [];
        foreach ($this->boletoEnviarAttachments as $attachment) {
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || ! is_file($path)) {
                continue;
            }
            $documents[] = [
                'path' => $path,
                'name' => (string) ($attachment['name'] ?? 'documento'),
                'mimetype' => (string) ($attachment['mimetype'] ?? 'application/pdf'),
            ];
        }

        if ($documents === []) {
            Notification::make()
                ->title('Anexos do boleto não encontrados.')
                ->body('Feche e abra novamente o envio.')
                ->warning()
                ->send();

            return;
        }

        $boleto = Boleto::query()->find($this->boletoEnviarId);
        if (! $boleto) {
            Notification::make()->title('Boleto não encontrado.')->warning()->send();

            return;
        }

        $empresaBase = $boleto->empresa_id
            ? Empresa::query()->find($boleto->empresa_id)
            : ErpContext::currentEmpresa();

        if (! $empresaBase instanceof Empresa) {
            Notification::make()->title('Empresa não identificada.')->warning()->send();

            return;
        }

        try {
            $result = app(WhatsAppSender::class)->sendDocumentMessages(
                empresa: $empresaBase,
                tipo: WhatsAppSender::TIPO_COBRANCA,
                number: $this->boletoEnviarWhatsApp,
                text: $this->boletoEnviarMensagem,
                documents: $documents,
            );
        } catch (\Throwable $e) {
            report($e);
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
            ->body('A tela permanece aberta para enviar também por e-mail, se quiser.')
            ->success()
            ->send();
    }

    protected function finalizarBoletoPosDocumentoFluxo(): void
    {
        $url = $this->boletoPosDocumentoRedirectUrl;
        $navigate = $this->boletoPosDocumentoNavigate;

        $this->boletoPosDocumentoContaIds = [];
        $this->boletoPosDocumentoRedirectUrl = null;
        $this->boletoPosDocumentoNavigate = false;
        $this->boletoPosDocumentoPromptOpen = false;
        $this->boletoContaPickOpen = false;

        if (filled($url)) {
            $this->redirect($url, navigate: $navigate);

            return;
        }

        $this->afterBoletoPosDocumentoFluxo();
    }

    protected function afterBoletoPosDocumentoFluxo(): void
    {
        //
    }

    private function empresaOverlayParaBoletoPosDocumento(Boleto $boleto): ?Empresa
    {
        $empresa = $boleto->empresa_id
            ? Empresa::query()->find($boleto->empresa_id)
            : ErpContext::currentEmpresa();

        if (! $empresa instanceof Empresa) {
            return null;
        }

        if ($boleto->boleto_conta_api_id) {
            $contaApi = BoletoContaApi::query()->find($boleto->boleto_conta_api_id);
            if ($contaApi instanceof BoletoContaApi) {
                return $contaApi->asEmpresaOverlay($empresa);
            }
        }

        return $empresa;
    }

    private function cleanupBoletoEnviarAttachmentsPosDocumento(): void
    {
        foreach ($this->boletoEnviarAttachments as $attachment) {
            if (! ($attachment['owned'] ?? true)) {
                continue;
            }
            $path = (string) ($attachment['path'] ?? '');
            if ($path !== '' && is_file($path) && str_contains($path, DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR)) {
                @unlink($path);
            }
        }
        $this->boletoEnviarAttachments = [];
    }
}
