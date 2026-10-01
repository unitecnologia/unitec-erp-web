<?php

declare(strict_types=1);

namespace App\Support\Pix\Ailos;

use App\Models\Empresa;
use App\Models\VendasParametro;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Configuração Pix Ailos por empresa.
 *
 * Credenciais (Client ID/Secret/Chave) vêm de Empresa > API PIX.
 * PFX: usa o certificado A1 da NF-e da mesma empresa (mTLS), sem colar .pfx na tela.
 * Secrets AILOS_PRODUCTION_* no .env são fallback opcional (ex.: teste local).
 */
final readonly class AilosPixConfig
{
    public const ENV_HOMOLOGATION = 'homologation';

    public const ENV_PRODUCTION = 'production';

    public function __construct(
        public string $environment,
        public string $baseUrl,
        public string $clientId,
        public string $clientSecret,
        public string $pixKey,
        public ?string $pfxPath,
        public ?string $pfxBase64,
        public string $pfxPassword,
        public int $timeout,
        public int $expirationSeconds,
        public string $tokenScopes,
    ) {
    }

    public static function fromEmpresa(Empresa $empresa): self
    {
        $ambienteEmpresa = strtolower(trim((string) ($empresa->param_pix_ambiente ?? 'producao')));
        $environment = $ambienteEmpresa === 'homologacao'
            ? self::ENV_HOMOLOGATION
            : self::ENV_PRODUCTION;

        $settings = config("ailos.environments.{$environment}", []);
        if (! is_array($settings)) {
            $settings = [];
        }

        // Por empresa (cliente) — fonte principal no ERP multi-empresa.
        $clientId = trim((string) ($empresa->param_pix_client_id ?: ($settings['client_id'] ?? '')));
        $clientSecret = (string) ($empresa->param_pix_client_secret ?: ($settings['client_secret'] ?? ''));
        $pixKey = trim((string) ($empresa->param_pix_chave ?: ($settings['pix_key'] ?? '')));

        $pfxPassword = trim((string) ($empresa->param_pix_certificado_senha ?: ($settings['pfx_password'] ?? '')));
        $pfxPath = null;
        $pfxBase64 = trim((string) ($settings['pfx_base64'] ?? ''));
        $envPath = trim((string) ($settings['pfx_path'] ?? ''));
        if ($envPath !== '' && is_file($envPath)) {
            $pfxPath = $envPath;
            $pfxBase64 = '';
        }

        // Certificado A1 da NF-e desta empresa (mesmo PFX / senha).
        [$nfePfxBase64, $nfeSenha] = self::nfePfxFromEmpresa($empresa);
        if ($pfxBase64 === '' && $pfxPath === null && $nfePfxBase64 !== null) {
            $pfxBase64 = $nfePfxBase64;
        }
        if ($pfxPassword === '' && filled($nfeSenha)) {
            $pfxPassword = (string) $nfeSenha;
        }

        // Homologação: ainda aceita upload/caminho em param_pix_certificado.
        if ($environment === self::ENV_HOMOLOGATION) {
            $cert = trim((string) ($empresa->param_pix_certificado ?: ''));
            if ($pfxPath === null && $pfxBase64 === '' && $cert !== '') {
                if (is_file($cert)) {
                    $pfxPath = $cert;
                } elseif (Storage::disk('local')->exists($cert)) {
                    $pfxPath = Storage::disk('local')->path($cert);
                } elseif (is_file(storage_path('app/private/'.$cert))) {
                    $pfxPath = storage_path('app/private/'.$cert);
                } else {
                    $pfxBase64 = $cert;
                }
            }
        }

        if ($clientId === '' || $clientSecret === '' || $pixKey === '') {
            throw new InvalidArgumentException(
                'Parâmetros Ailos Pix incompletos (Client ID, Client Secret e Chave PIX). '
                .'Preencha em Empresa > Parâmetros > API PIX.'
            );
        }

        if ($pfxPassword === '') {
            throw new InvalidArgumentException(
                'Senha do certificado A1 ausente. Cadastre o certificado em Configurações Fiscais (NF-e) '
                .'ou informe a senha do PFX em API PIX.'
            );
        }

        if ($pfxPath === null && $pfxBase64 === '') {
            throw new InvalidArgumentException(
                'Certificado A1 (.pfx) não encontrado. Importe o certificado da NF-e em Configurações Fiscais.'
            );
        }

        $baseUrl = rtrim((string) ($settings['base_url'] ?? self::defaultBaseUrl($environment)), '/');
        self::assertBaseUrl($environment, $baseUrl);

        return new self(
            environment: $environment,
            baseUrl: $baseUrl,
            clientId: $clientId,
            clientSecret: $clientSecret,
            pixKey: $pixKey,
            pfxPath: $pfxPath,
            pfxBase64: $pfxBase64 !== '' ? $pfxBase64 : null,
            pfxPassword: $pfxPassword,
            timeout: max(5, (int) config('ailos.timeout', 20)),
            expirationSeconds: max(60, (int) config('ailos.expiration_seconds', 86400)),
            tokenScopes: (string) config(
                'ailos.token_scopes',
                'cob.read cob.write cobv.read cobv.write pix.read pix.write '
                .'webhook.read webhook.write qrcode.read qrcode.write '
                .'payloadlocation.write payloadlocation.read lotecobv.write lotecobv.read',
            ),
        );
    }

    /**
     * @return array{0: ?string, 1: ?string} [base64, senha]
     */
    private static function nfePfxFromEmpresa(Empresa $empresa): array
    {
        try {
            $params = VendasParametro::forEmpresa((int) $empresa->id);
        } catch (Throwable) {
            return [null, null];
        }

        $senha = null;
        try {
            if ($params->hasStoredSenhaCertificado()) {
                $senha = (string) $params->senha_certificado;
            }
        } catch (DecryptException|Throwable) {
            $senha = null;
        }

        $binary = null;
        try {
            if (filled($params->getRawOriginal('certificado_pfx'))) {
                $content = $params->certificado_pfx;
                if (is_string($content) && $content !== '') {
                    $binary = $content;
                }
            }
        } catch (DecryptException|Throwable) {
            $binary = null;
        }

        if ($binary === null) {
            try {
                $caminho = trim((string) ($params->caminho_certificado ?? ''));
                if ($caminho !== '' && is_file($caminho)) {
                    $resolved = @file_get_contents($caminho);
                } elseif ($caminho !== '' && Storage::disk('local')->exists($caminho)) {
                    $resolved = Storage::disk('local')->get($caminho);
                } else {
                    $resolved = null;
                }
                if (is_string($resolved) && $resolved !== '') {
                    $binary = $resolved;
                }
            } catch (Throwable) {
                $binary = null;
            }
        }

        if (! is_string($binary) || strlen($binary) < 100) {
            return [null, $senha];
        }

        return [base64_encode($binary), $senha];
    }

    public static function defaultBaseUrl(string $environment): string
    {
        return $environment === self::ENV_PRODUCTION
            ? 'https://pixcobranca.ailos.coop.br/ailos/pix-cobranca/api/v1'
            : 'https://pixcobranca-h.ailos.coop.br/qa/ailos/pix-cobranca/api/v1';
    }

    private static function assertBaseUrl(string $environment, string $baseUrl): void
    {
        $parsedUrl = parse_url($baseUrl);
        $hostname = strtolower((string) ($parsedUrl['host'] ?? ''));
        if (($parsedUrl['scheme'] ?? '') !== 'https' || ! preg_match('/(?:^|\.)ailos\.coop\.br$/', $hostname)) {
            throw new InvalidArgumentException('A URL da API Ailos Pix deve usar HTTPS e o domínio ailos.coop.br.');
        }
        if ($environment === self::ENV_HOMOLOGATION && ! str_contains($hostname, 'pixcobranca-h.')) {
            throw new InvalidArgumentException('A URL de homologação Ailos Pix é inválida.');
        }
        if ($environment === self::ENV_PRODUCTION && str_contains($hostname, 'pixcobranca-h.')) {
            throw new InvalidArgumentException('A URL de produção Ailos Pix é inválida.');
        }
    }
}
