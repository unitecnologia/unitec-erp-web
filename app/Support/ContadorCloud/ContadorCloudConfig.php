<?php

namespace App\Support\ContadorCloud;

use App\Models\Empresa;

readonly class ContadorCloudConfig
{
    public function __construct(
        public bool $habilitar,
        public string $url,
        public string $empresaId,
        public string $token,
        public string $ambiente,
        public int $timeout,
        public ?int $contadorId,
        public string $email,
        public bool $enviarCompras,
        public bool $enviarVendas,
        public bool $enviarXml,
        public bool $enviarCanceladas,
        public bool $enviarPacoteMensal,
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
        $timeout = $data['param_portal_contador_timeout'] ?? config('contador-cloud.default_timeout', 30);

        return new self(
            habilitar: (bool) ($data['param_portal_contador_habilitar'] ?? false),
            url: trim((string) ($data['param_portal_contador_url'] ?? '')),
            empresaId: trim((string) ($data['param_portal_contador_empresa_id'] ?? '')),
            token: trim((string) ($data['param_portal_contador_token'] ?? '')),
            ambiente: (string) ($data['param_portal_contador_ambiente'] ?? 'homologacao'),
            timeout: max(1, (int) $timeout),
            contadorId: self::nullableInt($data['param_portal_contador_contador_id'] ?? null),
            email: trim((string) ($data['param_portal_contador_email'] ?? '')),
            enviarCompras: (bool) ($data['param_portal_contador_enviar_compras'] ?? true),
            enviarVendas: (bool) ($data['param_portal_contador_enviar_vendas'] ?? false),
            enviarXml: (bool) ($data['param_portal_contador_enviar_xml'] ?? true),
            enviarCanceladas: (bool) ($data['param_portal_contador_enviar_canceladas'] ?? true),
            enviarPacoteMensal: (bool) ($data['param_portal_contador_enviar_pacote_mensal'] ?? false),
        );
    }

    public function isConfigured(): bool
    {
        return $this->url !== '' && $this->empresaId !== '' && $this->token !== '';
    }

    public function healthUrl(): string
    {
        return rtrim($this->url, '/').config('contador-cloud.health_path', '/api/v1/health');
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
