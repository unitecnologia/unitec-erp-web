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
        public bool $enviarNfse,
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

        // URL sempre global (integração fixa) — ignora valor legado na empresa.
        $url = ContadorCloudHttpHelper::resolvePortalBaseUrl();

        return new self(
            habilitar: self::boolFrom($data['param_portal_contador_habilitar'] ?? false, false),
            url: $url,
            empresaId: trim((string) ($data['param_portal_contador_empresa_id'] ?? '')),
            token: trim((string) ($data['param_portal_contador_token'] ?? '')),
            ambiente: 'producao',
            timeout: max(1, (int) $timeout),
            contadorId: self::nullableInt($data['param_portal_contador_contador_id'] ?? null),
            email: trim((string) ($data['param_portal_contador_email'] ?? '')),
            enviarCompras: self::boolFrom($data['param_portal_contador_enviar_compras'] ?? true, true),
            enviarVendas: self::boolFrom($data['param_portal_contador_enviar_vendas'] ?? false, false),
            // null/ausente = habilitado (default de novas configs; não força UPDATE em empresas antigas).
            enviarNfse: self::boolFrom($data['param_portal_contador_enviar_nfse'] ?? true, true),
            enviarXml: self::boolFrom($data['param_portal_contador_enviar_xml'] ?? true, true),
            enviarCanceladas: self::boolFrom($data['param_portal_contador_enviar_canceladas'] ?? true, true),
            // Flag legada (ZIP e-mail é outro fluxo); default alinhado ao catálogo.
            enviarPacoteMensal: self::boolFrom($data['param_portal_contador_enviar_pacote_mensal'] ?? false, false),
        );
    }

    public function isConfigured(): bool
    {
        return $this->url !== '' && $this->empresaId !== '' && $this->token !== '';
    }

    /**
     * Conectado de fato: habilitado + credenciais completas (envio efetivo).
     */
    public function isActive(): bool
    {
        return $this->habilitar && $this->isConfigured();
    }

    public function healthUrl(): string
    {
        return ContadorCloudHttpHelper::resolveHealthUrl($this->url);
    }

    public function syncUrl(): string
    {
        return ContadorCloudHttpHelper::resolveSyncUrl($this->url);
    }

    private static function boolFrom(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
