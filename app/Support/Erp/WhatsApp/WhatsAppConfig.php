<?php

namespace App\Support\Erp\WhatsApp;

use App\Models\Empresa;

readonly class WhatsAppConfig
{
    public const STATUS_DESCONECTADO = 'desconectado';

    public const STATUS_AGUARDANDO_QR = 'aguardando_qr';

    public const STATUS_CONECTADO = 'conectado';

    public const STATUS_ERRO = 'erro';

    public function __construct(
        public bool $habilitar,
        public int $gatewayPort,
        public string $internoChave,
        public string $status,
        public string $numero,
        public int $timeout,
        public bool $enviarOrcamento,
        public bool $enviarCobranca,
        public bool $enviarNfe,
        public int $limiteDia,
        public int $msgsHoje,
        public ?string $msgsData,
    ) {}

    public static function fromEmpresa(Empresa $empresa): self
    {
        return self::fromArray($empresa->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromFormData(array $data): self
    {
        return self::fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function fromArray(array $data): self
    {
        $timeout = $data['param_whatsapp_timeout'] ?? 30;
        $port = $data['param_whatsapp_gateway_port'] ?? 8091;
        $limite = $data['param_whatsapp_limite_dia'] ?? 100;
        $msgsHoje = $data['param_whatsapp_msgs_hoje'] ?? 0;
        $msgsData = $data['param_whatsapp_msgs_data'] ?? null;

        if ($msgsData instanceof \DateTimeInterface) {
            $msgsData = $msgsData->format('Y-m-d');
        }

        return new self(
            habilitar: (bool) ($data['param_whatsapp_habilitar'] ?? false),
            gatewayPort: max(1024, (int) $port),
            internoChave: trim((string) ($data['param_whatsapp_interno_chave'] ?? '')),
            status: self::normalizeStatus($data['param_whatsapp_status'] ?? self::STATUS_DESCONECTADO),
            numero: preg_replace('/\D/', '', (string) ($data['param_whatsapp_numero'] ?? '')),
            timeout: max(1, (int) $timeout),
            enviarOrcamento: (bool) ($data['param_whatsapp_enviar_orcamento'] ?? true),
            enviarCobranca: (bool) ($data['param_whatsapp_enviar_cobranca'] ?? true),
            enviarNfe: (bool) ($data['param_whatsapp_enviar_nfe'] ?? false),
            limiteDia: max(1, (int) $limite),
            msgsHoje: max(0, (int) $msgsHoje),
            msgsData: filled($msgsData) ? (string) $msgsData : null,
        );
    }

    public static function normalizeStatus(mixed $status): string
    {
        $normalized = mb_strtolower(trim((string) ($status ?? '')), 'UTF-8');

        return match ($normalized) {
            self::STATUS_AGUARDANDO_QR,
            self::STATUS_CONECTADO,
            self::STATUS_ERRO => $normalized,
            default => self::STATUS_DESCONECTADO,
        };
    }

    public function gatewayBaseUrl(): string
    {
        return 'http://127.0.0.1:' . $this->gatewayPort;
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONECTADO && $this->numero !== '';
    }

    public function isConfigured(): bool
    {
        return $this->habilitar && $this->internoChave !== '';
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DESCONECTADO => 'Desconectado',
            self::STATUS_AGUARDANDO_QR => 'Aguardando leitura do QR Code',
            self::STATUS_CONECTADO => 'Conectado',
            self::STATUS_ERRO => 'Erro na conexão',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }
}
