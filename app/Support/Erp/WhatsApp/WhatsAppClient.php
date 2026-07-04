<?php



namespace App\Support\Erp\WhatsApp;



use App\Models\Empresa;

use Illuminate\Http\Client\Response;

use Illuminate\Support\Facades\Http;



class WhatsAppClient

{

    public function __construct(

        protected WhatsAppGatewayManager $gatewayManager,

    ) {}



    protected function bumpExecutionTime(int $seconds = 90): void

    {

        if (function_exists('set_time_limit')) {

            @set_time_limit(max($seconds, 30));

        }

    }



    protected function httpTimeout(WhatsAppConfig $config, int $minimum = 15, int $maximum = 45): int

    {

        return min(max($config->timeout, $minimum), $maximum);

    }



    /**

     * @return array{ok: bool, message: string, status?: string, number?: string, qr?: string|null}

     */

    public function fetchSessionStatus(Empresa $empresa, bool $lightweight = false): array

    {

        $this->bumpExecutionTime($lightweight ? 20 : 90);

        $config = WhatsAppConfig::fromEmpresa($empresa);



        if (! $config->habilitar) {

            return [

                'ok' => false,

                'message' => 'Habilite o envio de WhatsApp antes de consultar o status.',

            ];

        }



        $boot = $this->prepareGateway($empresa, $lightweight);



        if (! $boot['ok']) {

            return $boot;

        }



        try {

            $response = $this->requestFor($empresa)

                ->timeout($lightweight ? min($config->timeout, 5) : $config->timeout)

                ->get('/sessions/' . $empresa->id . '/status');



            if (! $response->successful()) {

                return $this->failureFromResponse($response, 'consultar o status');

            }



            /** @var array<string, mixed> $payload */

            $payload = $response->json();



            return [

                'ok' => true,

                'message' => 'Status atualizado.',

                'status' => (string) ($payload['status'] ?? WhatsAppConfig::STATUS_DESCONECTADO),

                'number' => (string) ($payload['number'] ?? ''),

                'qr' => isset($payload['qr']) ? (string) $payload['qr'] : null,

            ];

        } catch (\Throwable $exception) {

            return [

                'ok' => false,

                'message' => 'Não foi possível consultar o gateway: ' . $exception->getMessage(),

            ];

        }

    }



    /**

     * @return array{ok: bool, message: string, status?: string, number?: string, qr?: string|null}

     */

    public function startSession(Empresa $empresa): array

    {

        $this->bumpExecutionTime(90);

        $config = WhatsAppConfig::fromEmpresa($empresa);



        if (! $config->habilitar) {

            return [

                'ok' => false,

                'message' => 'Habilite o envio de WhatsApp antes de conectar.',

            ];

        }



        $boot = $this->prepareGateway($empresa);



        if (! $boot['ok']) {

            return $boot;

        }



        try {

            $response = $this->requestFor($empresa)

                ->timeout($this->httpTimeout($config, 20, 45))

                ->post('/sessions/' . $empresa->id . '/start');



            if (! $response->successful()) {

                return $this->failureFromResponse($response, 'iniciar a conexão');

            }



            /** @var array<string, mixed> $payload */

            $payload = $response->json();



            return [

                'ok' => true,

                'message' => (string) ($payload['message'] ?? 'Conexão iniciada.'),

                'status' => (string) ($payload['status'] ?? WhatsAppConfig::STATUS_AGUARDANDO_QR),

                'number' => (string) ($payload['number'] ?? ''),

                'qr' => isset($payload['qr']) ? (string) $payload['qr'] : null,

            ];

        } catch (\Throwable $exception) {

            return [

                'ok' => false,

                'message' => 'Não foi possível iniciar a conexão: ' . $exception->getMessage(),

            ];

        }

    }



    /**

     * @return array{ok: bool, message: string}

     */

    public function disconnectSession(Empresa $empresa): array

    {

        $this->bumpExecutionTime(90);

        $config = WhatsAppConfig::fromEmpresa($empresa);



        if (! $config->habilitar) {

            return [

                'ok' => false,

                'message' => 'WhatsApp não está habilitado.',

            ];

        }



        $boot = $this->prepareGateway($empresa);



        if (! $boot['ok']) {

            $this->gatewayManager->clearLocalSession($empresa);



            return [

                'ok' => true,

                'message' => 'WhatsApp desconectado localmente. O serviço interno não estava disponível.',

            ];

        }



        try {

            $response = $this->requestFor($empresa)

                ->timeout($config->timeout)

                ->delete('/sessions/' . $empresa->id);



            if ($response->status() === 401) {

                $this->gatewayManager->restartGateway($empresa);



                $response = $this->requestFor($empresa->fresh())

                    ->timeout($config->timeout)

                    ->delete('/sessions/' . $empresa->id);

            }



            if ($response->successful()) {

                return [

                    'ok' => true,

                    'message' => 'Sessão desconectada.',

                ];

            }



            if ($response->status() === 401) {

                $this->gatewayManager->clearLocalSession($empresa);



                return [

                    'ok' => true,

                    'message' => 'WhatsApp desconectado localmente. Reinicie o dev-windows.ps1 antes de conectar novamente.',

                ];

            }



            return $this->failureFromResponse($response, 'desconectar');

        } catch (\Throwable $exception) {

            $this->gatewayManager->clearLocalSession($empresa);



            return [

                'ok' => true,

                'message' => 'WhatsApp desconectado localmente. Detalhe: ' . $exception->getMessage(),

            ];

        }

    }



    /**
     * @param  list<string>  $candidates
     * @return array{ok: bool, message: string}
     */
    public function sendText(WhatsAppConfig $config, int $empresaId, string $number, string $text, array $candidates = []): array
    {
        $this->bumpExecutionTime($this->httpTimeout($config, 15, 35) + 5);

        return $this->sendPayload($config, $empresaId, [
            'number' => $number,
            'text' => $text,
            'candidates' => array_values(array_unique(array_filter($candidates))),
        ]);
    }



    /**
     * @param  list<string>  $candidates
     * @return array{ok: bool, message: string}
     */
    public function sendDocument(
        WhatsAppConfig $config,
        int $empresaId,
        string $number,
        string $text,
        string $documentPath,
        string $documentName,
        string $mimetype = 'application/pdf',
        array $candidates = [],
    ): array {
        $this->bumpExecutionTime($this->httpTimeout($config, 15, 35) + 5);

        return $this->sendPayload($config, $empresaId, [
            'number' => $number,
            'text' => $text,
            'documentPath' => $documentPath,
            'documentName' => $documentName,
            'mimetype' => $mimetype,
            'candidates' => array_values(array_unique(array_filter($candidates))),
        ]);
    }



    /**

     * @param  array<string, mixed>  $payload

     * @return array{ok: bool, message: string}

     */

    protected function sendPayload(WhatsAppConfig $config, int $empresaId, array $payload): array

    {

        $this->bumpExecutionTime($this->httpTimeout($config, 15, 35) + 5);

        $empresa = Empresa::query()->find($empresaId);



        if (! $empresa) {

            return [

                'ok' => false,

                'message' => 'Empresa não encontrada.',

            ];

        }



        try {

            $response = $this->requestFor($empresa)

                ->timeout($this->httpTimeout($config, 15, 35))

                ->post('/sessions/' . $empresaId . '/send', $payload);



            if (! $response->successful()) {

                if ($response->status() === 409 && $empresa) {

                    $empresa->forceFill([

                        'param_whatsapp_status' => WhatsAppConfig::STATUS_DESCONECTADO,

                    ])->save();

                }

                return $this->failureFromResponse($response, 'enviar mensagem');

            }



            /** @var array<string, mixed> $body */

            $body = $response->json();



            return [

                'ok' => true,

                'message' => (string) ($body['message'] ?? 'Mensagem enviada.'),

            ];

        } catch (\Throwable $exception) {

            return [

                'ok' => false,

                'message' => 'Falha ao enviar mensagem: ' . $exception->getMessage(),

            ];

        }

    }



    /**

     * @return array{ok: bool, message: string}

     */

    public function testConnection(WhatsAppConfig $config, Empresa $empresa): array

    {

        $this->bumpExecutionTime(60);

        if (! $config->habilitar) {

            return [

                'ok' => false,

                'message' => 'Habilite o envio de WhatsApp antes de testar.',

            ];

        }



        $boot = $this->prepareGateway($empresa);



        if (! $boot['ok']) {

            return $boot;

        }



        if ($config->isConnected()) {

            return [

                'ok' => true,

                'message' => 'WhatsApp conectado em ' . WhatsAppPhone::formatDisplay($config->numero) . '.',

            ];

        }



        return [

            'ok' => false,

            'message' => 'Gateway ativo, mas o WhatsApp ainda não está conectado. Leia o QR Code para vincular.',

        ];

    }



    /**

     * @return array{ok: bool, message: string}

     */

    protected function prepareGateway(Empresa $empresa, bool $lightweight = false): array

    {

        if ($lightweight) {

            $this->gatewayManager->writeRuntimeConfig($empresa);

            $config = WhatsAppConfig::fromEmpresa($empresa->fresh());



            if ($this->gatewayManager->isHealthy($config)) {

                return ['ok' => true, 'message' => ''];

            }



            return [

                'ok' => false,

                'message' => 'Serviço WhatsApp interno não está ativo. Rode: .\\scripts\\restart-whatsapp-gateway.ps1',

            ];

        }



        return $this->gatewayManager->ensureRunning($empresa);

    }



    protected function requestFor(Empresa $empresa): \Illuminate\Http\Client\PendingRequest

    {

        $this->gatewayManager->writeRuntimeConfig($empresa);

        $empresa = $empresa->fresh() ?? $empresa;

        $config = WhatsAppConfig::fromEmpresa($empresa);

        $key = $this->gatewayManager->resolveOrCreateKey($empresa);



        return Http::baseUrl($config->gatewayBaseUrl())

            ->acceptJson()

            ->withHeaders([

                'X-Erp-Gateway-Key' => $key,

            ]);

    }



    /**

     * @return array{ok: false, message: string}

     */

    protected function failureFromResponse(Response $response, string $action): array

    {

        /** @var array<string, mixed>|null $body */

        $body = $response->json();

        $detail = is_array($body) ? trim((string) ($body['message'] ?? '')) : '';



        if ($detail !== '') {

            return [

                'ok' => false,

                'message' => $detail,

            ];

        }



        return [

            'ok' => false,

            'message' => 'Gateway respondeu com erro HTTP ' . $response->status() . ' ao ' . $action . '.',

        ];

    }

}

