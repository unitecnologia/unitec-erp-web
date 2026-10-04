<?php

namespace App\Filament\Resources\ContaReceberResource\Pages\Concerns;

use App\Models\Boleto;
use App\Models\BoletoContaApi;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\Boleto\BoletoContaApiResolver;
use App\Support\Erp\Boleto\BoletoEmissionDispatcher;
use App\Support\Erp\Boleto\BoletoEnvioService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use Filament\Notifications\Notification;
use Illuminate\Support\Js;

trait ManagesContaReceberBoletoModal
{
    public bool $boletoContaPickOpen = false;

    public ?int $boletoContaPickReceberId = null;

    public ?int $boletoContaPickSelectedId = null;

    /** @var list<array{id: int, rotulo: string, padrao: bool}> */
    public array $boletoContaPickOptions = [];

    public ?int $boletoSucessoId = null;

    public string $boletoSucessoDetalhe = '';

    public bool $boletoSucessoSegundaVia = false;

    public bool $boletoFormaBloqueioOpen = false;

    public string $boletoFormaBloqueioTitulo = '';

    public string $boletoFormaBloqueioMensagem = '';

    public bool $boletoEnviarModalOpen = false;

    public ?int $boletoEnviarId = null;

    public string $boletoEnviarEmail = '';

    public string $boletoEnviarWhatsApp = '';

    public string $boletoEnviarAssunto = '';

    public string $boletoEnviarMensagem = '';

    /** @var list<array{id: string, name: string, path: string, display: string, owned?: bool, mimetype?: string}> */
    public array $boletoEnviarAttachments = [];

    public function gerarBoleto(): void
    {
        if (! $this->erpAuthorizeOrNotify('boletos.create')) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('gerar boleto');
        if (! $recordId) {
            return;
        }

        $conta = ContaReceber::query()->with('cliente')->find($recordId);
        if (! $conta) {
            Notification::make()->title('Conta não encontrada.')->warning()->send();

            return;
        }

        $existente = $this->boletoAbertoComLinha((int) $conta->id);
        if ($existente instanceof Boleto) {
            $this->reabrirBoletoSucesso($existente, jaExistia: true);

            return;
        }

        if (! $conta->isFormaBoleto()) {
            $this->abrirBoletoFormaBloqueio($conta);

            return;
        }

        $empresa = $conta->empresa_id
            ? Empresa::query()->find($conta->empresa_id)
            : ErpContext::currentEmpresa();

        if (! $empresa instanceof Empresa) {
            Notification::make()->title('Empresa não encontrada.')->warning()->send();

            return;
        }

        if (! filter_var($empresa->param_boleto_habilitar ?? false, FILTER_VALIDATE_BOOLEAN)) {
            Notification::make()
                ->title('API Boleto desabilitada')
                ->body('Ative em Empresa > Parâmetros > API Boleto.')
                ->warning()
                ->send();

            return;
        }

        $resolver = app(BoletoContaApiResolver::class);
        $ativas = $resolver->ativas($empresa);

        if ($ativas->isEmpty()) {
            Notification::make()
                ->title('Nenhuma conta de cobrança API')
                ->body('Cadastre Ailos e/ou Sicredi em Empresa > API Boleto.')
                ->warning()
                ->send();

            return;
        }

        if ($ativas->count() === 1) {
            // Segunda request: o wire:loading do progresso só cobre emissão real na API
            // (não a reabertura de segunda via em gerarBoleto).
            $this->js(
                'queueMicrotask(() => $wire.emitirBoletoApi('
                .Js::from((int) $conta->id).','
                .Js::from((int) $ativas->first()->id)
                .'))'
            );

            return;
        }

        $padrao = $ativas->firstWhere('padrao', true) ?? $ativas->first();
        $this->boletoContaPickReceberId = (int) $conta->id;
        $this->boletoContaPickSelectedId = (int) $padrao->id;
        $this->boletoContaPickOptions = $ativas
            ->map(fn ($c): array => [
                'id' => (int) $c->id,
                'rotulo' => $c->rotulo(),
                'padrao' => (bool) $c->padrao,
            ])
            ->values()
            ->all();
        $this->boletoContaPickOpen = true;
    }

    public function closeBoletoContaPickModal(): void
    {
        $this->boletoContaPickOpen = false;
        $this->boletoContaPickReceberId = null;
        $this->boletoContaPickSelectedId = null;
        $this->boletoContaPickOptions = [];
    }

    public function confirmarGerarBoletoComConta(): void
    {
        $receberId = (int) ($this->boletoContaPickReceberId ?? 0);
        $contaApiId = (int) ($this->boletoContaPickSelectedId ?? 0);
        $this->closeBoletoContaPickModal();

        $this->emitirBoletoApi($receberId, $contaApiId);
    }

    public function emitirBoletoApi(int $receberId, int $contaApiId): void
    {
        if (! $this->erpAuthorizeOrNotify('boletos.create')) {
            return;
        }

        if ($receberId <= 0 || $contaApiId <= 0) {
            Notification::make()->title('Selecione a conta de cobrança.')->warning()->send();

            return;
        }

        $conta = ContaReceber::query()->with('cliente')->find($receberId);
        if (! $conta) {
            Notification::make()->title('Conta não encontrada.')->warning()->send();

            return;
        }

        $existente = $this->boletoAbertoComLinha((int) $conta->id);
        if ($existente instanceof Boleto) {
            $this->reabrirBoletoSucesso($existente, jaExistia: true);

            return;
        }

        if (! $conta->isFormaBoleto()) {
            $this->abrirBoletoFormaBloqueio($conta);

            return;
        }

        $this->emitirBoletoComContaApi($conta, $contaApiId);
    }

    public function acknowledgeBoletoFormaBloqueio(): void
    {
        $this->boletoFormaBloqueioOpen = false;
        $this->boletoFormaBloqueioTitulo = '';
        $this->boletoFormaBloqueioMensagem = '';
        $this->editConta();
    }

    public function closeBoletoFormaBloqueio(): void
    {
        $this->boletoFormaBloqueioOpen = false;
        $this->boletoFormaBloqueioTitulo = '';
        $this->boletoFormaBloqueioMensagem = '';
    }

    private function abrirBoletoFormaBloqueio(ContaReceber $conta): void
    {
        $this->boletoFormaBloqueioOpen = true;
        $this->boletoFormaBloqueioTitulo = 'Conta em '.$conta->formaLabel();
        $this->boletoFormaBloqueioMensagem = 'Só é possível gerar boleto quando o tipo for Boleto.'
            ."\n\n"
            .'Altere a forma de pagamento nesta conta e tente novamente.';
        $this->closeBoletoContaPickModal();
    }

    public function acknowledgeBoletoSucessoOverlay(): void
    {
        $this->boletoSucessoId = null;
        $this->boletoSucessoDetalhe = '';
        $this->boletoSucessoSegundaVia = false;
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

        $empresa = $this->empresaOverlayParaBoleto($boleto);
        if (! $empresa instanceof Empresa) {
            Notification::make()->title('Empresa não encontrada.')->warning()->send();

            return;
        }

        $this->cleanupBoletoEnviarAttachments();

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
            : ("Olá".($clienteNome !== '' ? ', '.$clienteNome : '')."!\n\nSegue em anexo o boleto para pagamento.");
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
        $this->cleanupBoletoEnviarAttachments();
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

        $empresa = $this->empresaOverlayParaBoleto($boleto);
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

    private function emitirBoletoComContaApi(ContaReceber $conta, int $contaApiId): void
    {
        Notification::make()
            ->title('Gerando boleto…')
            ->body('Aguarde a autenticação e o registro do título no banco.')
            ->info()
            ->send();

        try {
            $empresa = $conta->empresa_id
                ? Empresa::query()->find($conta->empresa_id)
                : ErpContext::currentEmpresa();

            if (! $empresa instanceof Empresa) {
                throw new \RuntimeException('Empresa não encontrada.');
            }

            $boletoConta = app(BoletoContaApiResolver::class)->findAtiva($empresa, $contaApiId);
            $boleto = app(BoletoEmissionDispatcher::class)
                ->emitirParaContaReceber($conta, $boletoConta, $empresa);

            $this->reabrirBoletoSucesso($boleto, jaExistia: false);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não foi possível gerar o boleto')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function boletoAbertoComLinha(int $contaReceberId): ?Boleto
    {
        return Boleto::query()
            ->where('conta_receber_id', $contaReceberId)
            ->where('status', Boleto::STATUS_ABERTO)
            ->whereNotNull('linha_digitavel')
            ->where('linha_digitavel', '!=', '')
            ->orderByDesc('id')
            ->first();
    }

    private function reabrirBoletoSucesso(Boleto $boleto, bool $jaExistia): void
    {
        $envio = app(BoletoEnvioService::class);
        $this->boletoSucessoId = (int) $boleto->id;
        $this->boletoSucessoDetalhe = $envio->sucessoDetalhe($boleto);
        $this->boletoSucessoSegundaVia = $jaExistia;
        $this->boletoEnviarModalOpen = false;
        $this->closeBoletoContaPickModal();

        if ($jaExistia) {
            Notification::make()
                ->title('Segunda via do boleto')
                ->body('Este título já possui boleto emitido. Reabrindo impressão/envio.')
                ->info()
                ->send();
        }
    }

    private function empresaOverlayParaBoleto(Boleto $boleto): ?Empresa
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

    private function cleanupBoletoEnviarAttachments(): void
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
