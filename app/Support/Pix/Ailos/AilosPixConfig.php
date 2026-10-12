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
 * Configuração Pix Ailos por empresa — somente dados do banco, nunca .env.
 *
 * O cliente informa apenas Client ID, Client Secret e Chave PIX (Empresa > API PIX).
 * Endpoint, ambiente (produção), escopos e tempos são padrão da integração.
 * mTLS obrigatório da Ailos: usa o certificado A1 da NF-e da mesma empresa.
 */
final readonly class AilosPixConfig
{
    public const ENV_HOMOLOGATION = 'homologation';

    public const ENV_PRODUCTION = 'production';

    public const BASE_URL_PRODUCTION = 'https://pixcobranca.ailos.coop.br/ailos/pix-cobranca/api/v1';

    public const BASE_URL_HOMOLOGATION = 'https://pixcobranca-h.ailos.coop.br/qa/ailos/pix-cobranca/api/v1';

    public const TIMEOUT_SECONDS = 20;

    public const EXPIRATION_SECONDS = 86400;

    public const TOKEN_SCOPES = 'cob.read cob.write cobv.read cobv.write pix.read pix.write '
        .'webhook.read webhook.write qrcode.read qrcode.write '
        .'payloadlocation.write payloadlocation.read lotecobv.write lotecobv.read';

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
        $clientId = trim((string) ($empresa->param_pix_client_id ?? ''));
        $clientSecret = (string) ($empresa->param_pix_client_secret ?? '');
        $pixKey = trim((string) ($empresa->param_pix_chave ?? ''));

        if ($clientId === '' || $clientSecret === '' || $pixKey === '') {
            throw new InvalidArgumentException(
                'Parâmetros Ailos Pix incompletos (Client ID, Client Secret e Chave PIX). '
                .'Preencha em Empresa > Parâmetros > API PIX.'
            );
        }

        [$pfxBase64, $nfeSenha] = self::nfePfxFromEmpresa($empresa);

        if ($pfxBase64 === null) {
            throw new InvalidArgumentException(
                'A Ailos exige o certificado A1 (.pfx) da empresa. '
                .'Importe o certificado da NF-e em Configurações Fiscais.'
            );
        }

        $pfxPassword = trim((string) ($nfeSenha ?: ($empresa->param_pix_certificado_senha ?? '')));

        if ($pfxPassword === '') {
            throw new InvalidArgumentException(
                'Senha do certificado A1 ausente. Informe a senha do certificado em Configurações Fiscais (NF-e).'
            );
        }

        return new self(
            environment: self::ENV_PRODUCTION,
            baseUrl: self::BASE_URL_PRODUCTION,
            clientId: $clientId,
            clientSecret: $clientSecret,
            pixKey: $pixKey,
            pfxPath: null,
            pfxBase64: $pfxBase64,
            pfxPassword: $pfxPassword,
            timeout: self::TIMEOUT_SECONDS,
            expirationSeconds: self::EXPIRATION_SECONDS,
            tokenScopes: self::TOKEN_SCOPES,
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
            ? self::BASE_URL_PRODUCTION
            : self::BASE_URL_HOMOLOGATION;
    }
}
