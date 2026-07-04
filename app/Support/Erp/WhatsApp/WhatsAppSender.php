<?php

namespace App\Support\Erp\WhatsApp;

use App\Models\Empresa;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use Illuminate\Support\Facades\Date;

class WhatsAppSender
{
    public const TIPO_ORCAMENTO = 'orcamento';

    public const TIPO_COBRANCA = 'cobranca';

    public const TIPO_NFE = 'nfe';

    public function __construct(
        protected WhatsAppClient $client,
        protected WhatsAppGatewayManager $gatewayManager,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function sendTextMessage(Empresa $empresa, string $tipo, string $number, string $text): array
    {
        $boot = $this->gatewayManager->ensureRunning($empresa);

        if (! $boot['ok']) {
            return $boot;
        }

        $empresa = $empresa->fresh() ?? $empresa;
        $this->ensureGatewayWhatsAppSession($empresa);
        $empresa = $empresa->fresh() ?? $empresa;

        $config = WhatsAppConfig::fromEmpresa($empresa);

        $validation = $this->validateBeforeSend($empresa, $config, $tipo, $number);

        if (! $validation['ok']) {
            return $validation;
        }

        $normalized = WhatsAppPhone::normalize($number);

        if ($normalized === null) {
            return [
                'ok' => false,
                'message' => 'Informe um número de WhatsApp válido.',
            ];
        }

        $config = WhatsAppConfig::fromEmpresa($empresa);

        $result = $this->client->sendText(
            $config,
            (int) $empresa->id,
            $normalized,
            $text,
            WhatsAppPhone::lookupCandidates($number),
        );

        if ($result['ok']) {
            $this->incrementDailyCounter($empresa);
        }

        return $result;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function sendDocumentMessage(
        Empresa $empresa,
        string $tipo,
        string $number,
        string $text,
        string $documentPath,
        string $documentName,
        string $mimetype = 'application/pdf',
    ): array {
        if (! is_file($documentPath)) {
            return [
                'ok' => false,
                'message' => 'Arquivo para envio não encontrado.',
            ];
        }

        $boot = $this->gatewayManager->ensureRunning($empresa);

        if (! $boot['ok']) {
            return $boot;
        }

        $empresa = $empresa->fresh() ?? $empresa;
        $this->ensureGatewayWhatsAppSession($empresa);
        $empresa = $empresa->fresh() ?? $empresa;

        $config = WhatsAppConfig::fromEmpresa($empresa);

        $validation = $this->validateBeforeSend($empresa, $config, $tipo, $number);

        if (! $validation['ok']) {
            return $validation;
        }

        $normalized = WhatsAppPhone::normalize($number);

        if ($normalized === null) {
            return [
                'ok' => false,
                'message' => 'Informe um número de WhatsApp válido.',
            ];
        }

        $config = WhatsAppConfig::fromEmpresa($empresa);

        $result = $this->client->sendDocument(
            $config,
            (int) $empresa->id,
            $normalized,
            $text,
            $documentPath,
            $documentName,
            $mimetype,
            WhatsAppPhone::lookupCandidates($number),
        );

        if ($result['ok']) {
            $this->incrementDailyCounter($empresa);
        }

        return $result;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    protected function validateBeforeSend(Empresa $empresa, WhatsAppConfig $config, string $tipo, string $number): array
    {
        if (! $config->habilitar) {
            return [
                'ok' => false,
                'message' => 'WhatsApp não está habilitado nos parâmetros da empresa.',
            ];
        }

        if (! $config->isConnected()) {
            $this->refreshConnectionStatusFromGateway($empresa);
            $config = WhatsAppConfig::fromEmpresa($empresa->fresh());
        }

        if (! $config->isConnected()) {
            return [
                'ok' => false,
                'message' => 'WhatsApp não está conectado. Vincule o número em Empresa → Parâmetros → WhatsApp.',
            ];
        }

        if ($tipo === self::TIPO_ORCAMENTO && ! $config->enviarOrcamento) {
            return [
                'ok' => false,
                'message' => 'Envio de orçamentos por WhatsApp está desabilitado nos parâmetros.',
            ];
        }

        if ($tipo === self::TIPO_COBRANCA && ! $config->enviarCobranca) {
            return [
                'ok' => false,
                'message' => 'Envio de cobranças por WhatsApp está desabilitado nos parâmetros.',
            ];
        }

        if ($tipo === self::TIPO_NFE && ! $config->enviarNfe) {
            return [
                'ok' => false,
                'message' => 'Envio de NF-e por WhatsApp está desabilitado. Use e-mail.',
            ];
        }

        if (blank($number)) {
            return [
                'ok' => false,
                'message' => 'Informe o número do destinatário.',
            ];
        }

        $this->resetDailyCounterIfNeeded($empresa, $config);
        $config = WhatsAppConfig::fromEmpresa($empresa->fresh());

        if ($config->msgsHoje >= $config->limiteDia) {
            return [
                'ok' => false,
                'message' => 'Limite diário de ' . $config->limiteDia . ' mensagens atingido.',
            ];
        }

        return [
            'ok' => true,
            'message' => '',
        ];
    }

    protected function refreshConnectionStatusFromGateway(Empresa $empresa): void
    {
        $result = $this->client->fetchSessionStatus($empresa, lightweight: true);

        if (! $result['ok']) {
            return;
        }

        $fields = [];

        if (isset($result['status'])) {
            $fields['param_whatsapp_status'] = WhatsAppConfig::normalizeStatus($result['status']);
        }

        if (isset($result['number'])) {
            $fields['param_whatsapp_numero'] = WhatsAppPhone::normalize((string) $result['number'])
                ?? preg_replace('/\D/', '', (string) $result['number']);
        }

        if ($fields === []) {
            return;
        }

        $empresa->forceFill($fields)->save();
    }

    protected function resetDailyCounterIfNeeded(Empresa $empresa, WhatsAppConfig $config): void
    {
        $today = Date::today()->toDateString();

        if ($config->msgsData === $today) {
            return;
        }

        $empresa->forceFill([
            'param_whatsapp_msgs_hoje' => 0,
            'param_whatsapp_msgs_data' => $today,
        ])->save();
    }

    protected function incrementDailyCounter(Empresa $empresa): void
    {
        $today = Date::today()->toDateString();
        $empresa->refresh();

        $msgsHoje = (int) ($empresa->param_whatsapp_msgs_hoje ?? 0);
        $msgsData = $empresa->param_whatsapp_msgs_data;

        if ($msgsData instanceof \DateTimeInterface) {
            $msgsData = $msgsData->format('Y-m-d');
        }

        if ($msgsData !== $today) {
            $msgsHoje = 0;
        }

        $empresa->forceFill([
            'param_whatsapp_msgs_hoje' => $msgsHoje + 1,
            'param_whatsapp_msgs_data' => $today,
        ])->save();
    }

    protected function ensureGatewayWhatsAppSession(Empresa $empresa): void
    {
        $status = $this->client->fetchSessionStatus($empresa, lightweight: true);

        if (($status['status'] ?? null) === WhatsAppConfig::STATUS_CONECTADO) {
            return;
        }

        $this->client->startSession($empresa);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            usleep(500_000);

            $status = $this->client->fetchSessionStatus($empresa, lightweight: true);

            if (($status['status'] ?? null) === WhatsAppConfig::STATUS_CONECTADO) {
                $this->refreshConnectionStatusFromGateway($empresa);

                return;
            }

            if (($status['status'] ?? null) === WhatsAppConfig::STATUS_AGUARDANDO_QR) {
                break;
            }
        }
    }
}
