<?php

namespace App\Support\Erp\Nfe;

use App\Models\Empresa;
use App\Models\VendasParametro;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class NfeFiscalConfig
{
    /**
     * @return list<string>
     */
    public static function ufOptions(): array
    {
        return [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
            'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function sslLibOptions(): array
    {
        return [
            0 => 'libNone',
            1 => 'libOpenSSL',
            2 => 'libCapicom',
            3 => 'libCapicomDelphiSignature',
            4 => 'libWinCrypt',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function sslTypeOptions(): array
    {
        return [
            0 => 'LT_all',
            1 => 'LT_SSLv2',
            2 => 'LT_SSLv3',
            3 => 'LT_TLSv1',
            4 => 'LT_TLSv1_1',
            5 => 'LT_TLSv1_2',
            6 => 'LT_TLSv1_3',
            7 => 'LT_SSHv2',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function cryptLibOptions(): array
    {
        return [
            0 => 'cryNone',
            1 => 'cryOpenSSL',
            2 => 'cryCapicom',
            3 => 'cryWinCrypt',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function httpLibOptions(): array
    {
        return [
            0 => 'httpNone',
            1 => 'httpWinINet',
            2 => 'httpWinHttp',
            3 => 'httpOpenSSL',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function xmlSignLibOptions(): array
    {
        return [
            0 => 'xsNone',
            1 => 'xsXmlSec',
            2 => 'xsMsXml',
            3 => 'xsMsXmlCapicom',
            4 => 'xsLibXml2',
        ];
    }

    /**
     * Valores fixos da pilha OpenSSL/PHP no servidor web (equivalente ACBr no Linux).
     *
     * @return array<string, int|string>
     */
    public static function defaultWebStack(): array
    {
        return [
            'forma_emissao' => 1,
            'crypt_lib' => '1',
            'http_lib' => '3',
            'xml_sign' => '4',
            'ssl_tipo' => 5,
        ];
    }

    public static function syncWebStack(VendasParametro $params): VendasParametro
    {
        $updates = [];

        foreach (self::defaultWebStack() as $field => $value) {
            if ($params->{$field} != $value) {
                $updates[$field] = $value;
            }
        }

        if ($updates !== []) {
            $params->update($updates);

            return $params->fresh();
        }

        return $params;
    }

    public static function ensureDefaults(VendasParametro $params, ?Empresa $empresa = null): void
    {
        $empresaId = (int) $params->empresa_id;
        $defaults = [
            'uf' => $params->uf ?: ($empresa?->uf ?: 'SC'),
            'ambiente' => $params->ambiente ?? VendasParametro::AMBIENTE_HOMOLOGACAO,
            'versao_nfe' => $params->versao_nfe ?? 4,
            'tipo_emissao' => $params->tipo_emissao ?? 1,
            ...self::defaultWebStack(),
            'aguardar' => $params->aguardar ?? 5,
            'tentativas' => $params->tentativas ?? 5,
            'intervalo' => $params->intervalo ?? 10,
            'ajustar_auto' => $params->ajustar_auto ?? 'S',
            'numero' => $params->numero ?? 1,
            'serie' => $params->serie ?? '1',
            'serie_nfe' => $params->serie_nfe ?? 1,
            ...self::defaultStoragePaths($empresaId),
        ];

        foreach (self::defaultStoragePaths($empresaId) as $field => $default) {
            if (self::isInvalidStoragePath($params->{$field})) {
                $defaults[$field] = $default;
            } else {
                $defaults[$field] = $params->{$field};
            }
        }

        $dirty = false;
        $pathFields = array_keys(self::defaultStoragePaths($empresaId));

        foreach ($defaults as $field => $value) {
            $current = $params->{$field};
            $shouldUpdate = $current === null || $current === '';

            if (in_array($field, $pathFields, true) && self::isInvalidStoragePath($current)) {
                $shouldUpdate = true;
            }

            if ($shouldUpdate) {
                $params->{$field} = $value;
                $dirty = true;
            }
        }

        if ($dirty) {
            $params->save();
        }

        self::syncStoragePaths($params->fresh());
        self::syncWebStack($params->fresh());
    }

    public static function storageBase(int $empresaId): string
    {
        return "nfe/{$empresaId}";
    }

    /**
     * @return array<string, string>
     */
    public static function defaultStoragePaths(int $empresaId): array
    {
        $base = self::storageBase($empresaId);

        return [
            'path_salvar_nfe' => "{$base}/xml",
            'path_schemas_nfe' => "{$base}/schemas",
            'path_enviada_nfe' => "{$base}/enviadas",
            'path_can_nfe' => "{$base}/canceladas",
            'path_inuti_nfe' => "{$base}/inutilizadas",
            'path_evento_nfe' => "{$base}/eventos",
            'path_pdf_nfe' => "{$base}/pdf",
        ];
    }

    public static function isInvalidStoragePath(?string $path): bool
    {
        $path = trim((string) $path);

        if ($path === '') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)
            || str_contains($path, ':\\')
            || str_starts_with($path, '/');
    }

    public static function syncStoragePaths(VendasParametro $params): VendasParametro
    {
        $defaults = self::defaultStoragePaths((int) $params->empresa_id);
        $updates = [];

        foreach ($defaults as $field => $default) {
            if (self::isInvalidStoragePath($params->{$field})) {
                $updates[$field] = $default;
            }
        }

        if ($updates !== []) {
            $params->update($updates);
            $params = $params->fresh();
        }

        self::ensureDirectories($params);

        return $params;
    }

    /**
     * @return array<int, string>
     */
    public static function versaoNfeOptions(): array
    {
        return [
            4 => '4.00 (VE400)',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function tipoEmissaoNfeOptions(): array
    {
        return [
            1 => 'Normal',
            2 => 'Contingência FS-IA',
            3 => 'Contingência SCAN',
            4 => 'Contingência DPEC',
            5 => 'Contingência FS-DA',
            6 => 'Contingência SVC-AN',
            7 => 'Contingência SVC-RS',
            9 => 'Contingência off-line NFC-e',
        ];
    }

    public static function ensureDirectories(VendasParametro $params): void
    {
        foreach (self::pathFields() as $field) {
            $relative = (string) ($params->{$field} ?? '');

            if ($relative === '') {
                continue;
            }

            Storage::disk('local')->makeDirectory($relative);
        }

        Storage::disk('local')->makeDirectory('certificados/'.$params->empresa_id);
    }

    /**
     * @return list<string>
     */
    public static function pathFields(): array
    {
        return [
            'path_salvar_nfe',
            'path_schemas_nfe',
            'path_enviada_nfe',
            'path_can_nfe',
            'path_inuti_nfe',
            'path_evento_nfe',
            'path_pdf_nfe',
        ];
    }

    public static function certificadoAbsolutePath(VendasParametro $params): ?string
    {
        $relative = trim((string) $params->caminho_certificado);

        if ($relative === '') {
            return null;
        }

        if (File::exists($relative)) {
            return $relative;
        }

        $storagePath = Storage::disk('local')->path($relative);

        return File::exists($storagePath) ? $storagePath : null;
    }

    /**
     * @return array{ok: bool, message: string, validade?: string, numero_serie?: string}
     */
    public static function readPkcs12(string $content, string $senha): array
    {
        $certs = [];

        if (! openssl_pkcs12_read($content, $certs, $senha)) {
            return ['ok' => false, 'message' => 'Senha inválida ou .pfx inválido.'];
        }

        $parsed = openssl_x509_parse($certs['cert'] ?? '');

        if ($parsed === false) {
            return ['ok' => false, 'message' => 'Não foi possível ler o certificado.'];
        }

        $validade = date('d/m/Y', (int) ($parsed['validTo_time_t'] ?? time()));
        $validadeInicio = date('d/m/Y', (int) ($parsed['validFrom_time_t'] ?? time()));

        return [
            'ok' => true,
            'message' => 'Certificado lido com sucesso.',
            'validade' => $validade,
            'validade_inicio' => $validadeInicio,
            'numero_serie' => self::formatCertificadoSerial($parsed),
            'titulo' => self::formatCertificadoNome($parsed['subject'] ?? []),
            'emissor' => self::formatCertificadoNome($parsed['issuer'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $dn
     */
    public static function formatCertificadoNome(array $dn): string
    {
        if (isset($dn['CN'])) {
            return (string) $dn['CN'];
        }

        $parts = [];

        foreach (['O', 'OU', 'emailAddress'] as $key) {
            if (! empty($dn[$key])) {
                $parts[] = (string) $dn[$key];
            }
        }

        return $parts !== [] ? implode(' — ', $parts) : 'Certificado digital';
    }

    /**
     * @return array{titulo: string, emissor: string, validade_inicio: string, validade: string, numero_serie: string}
     */
    public static function certificadoOpcaoFromParsed(array $parsed): array
    {
        return [
            'titulo' => self::formatCertificadoNome($parsed['subject'] ?? []),
            'emissor' => self::formatCertificadoNome($parsed['issuer'] ?? []),
            'validade_inicio' => date('d/m/Y', (int) ($parsed['validFrom_time_t'] ?? time())),
            'validade' => date('d/m/Y', (int) ($parsed['validTo_time_t'] ?? time())),
            'numero_serie' => self::formatCertificadoSerial($parsed),
        ];
    }

    /**
     * @return array{ok: bool, message: string, numero_serie?: string}
     */
    public static function readCertificadoSerial(VendasParametro $params, ?string $senha = null): array
    {
        $path = self::certificadoAbsolutePath($params);

        if ($path === null) {
            return ['ok' => false, 'message' => 'Informe o caminho do certificado ou selecione um arquivo .pfx.'];
        }

        $senha = $senha ?? $params->safeSenhaCertificado();

        if ($senha === null || $senha === '') {
            $message = $params->hasStoredSenhaCertificado()
                ? 'Senha do certificado não pôde ser lida. Informe-a novamente nas configurações fiscais.'
                : 'Informe a senha do certificado.';

            return ['ok' => false, 'message' => $message];
        }

        $result = self::readPkcs12(File::get($path), $senha);

        if (! $result['ok']) {
            return $result;
        }

        return [
            'ok' => true,
            'message' => 'Número de série lido do certificado.',
            'numero_serie' => $result['numero_serie'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function formatCertificadoSerial(array $parsed): string
    {
        if (! empty($parsed['serialNumberHex'])) {
            return strtoupper((string) $parsed['serialNumberHex']);
        }

        $serial = (string) ($parsed['serialNumber'] ?? '');

        if ($serial === '') {
            return '';
        }

        if (function_exists('gmp_init') && ctype_digit($serial)) {
            return strtoupper(gmp_strval(gmp_init($serial, 10), 16));
        }

        $normalized = strtoupper(preg_replace('/[^0-9A-F]/', '', $serial) ?? '');

        return $normalized !== '' ? $normalized : strtoupper($serial);
    }

    /**
     * @return array{ok: bool, message: string, validade?: string}
     */
    public static function testCertificado(VendasParametro $params, ?string $senha = null): array
    {
        $path = self::certificadoAbsolutePath($params);

        if ($path === null) {
            return ['ok' => false, 'message' => 'Certificado não encontrado. Envie o arquivo .pfx.'];
        }

        $senha = $senha ?? $params->safeSenhaCertificado();

        if ($senha === null || $senha === '') {
            $message = $params->hasStoredSenhaCertificado()
                ? 'Senha do certificado não pôde ser lida. Informe-a novamente nas configurações fiscais.'
                : 'Informe a senha do certificado.';

            return ['ok' => false, 'message' => $message];
        }

        $result = self::readPkcs12(File::get($path), $senha);

        if (! $result['ok']) {
            return $result;
        }

        return [
            'ok' => true,
            'message' => "Certificado válido até {$result['validade']}.",
            'validade' => $result['validade'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toFormArray(VendasParametro $params): array
    {
        return [
            'uf' => $params->uf ?? 'SC',
            'ambiente' => (int) ($params->ambiente ?? VendasParametro::AMBIENTE_HOMOLOGACAO),
            'forma_emissao' => (int) ($params->forma_emissao ?? 1),
            'versao_nfe' => (int) ($params->versao_nfe ?? 4),
            'tipo_emissao' => (int) ($params->tipo_emissao ?? 1),
            'aguardar' => (int) ($params->aguardar ?? 5),
            'intervalo' => (int) ($params->intervalo ?? 10),
            'tentativas' => (int) ($params->tentativas ?? 5),
            'ajustar_auto' => ($params->ajustar_auto ?? 'S') === 'S',
            'proxy_host' => $params->proxy_host ?? '',
            'proxy_porta' => $params->proxy_porta ?? '',
            'proxy_usuario' => $params->proxy_usuario ?? '',
            'proxy_senha' => (string) ($params->safeEncrypted('proxy_senha') ?? ''),
            'numero_serie_certificado' => $params->numero_serie_certificado ?? '',
            'senha_certificado' => filled($params->caminho_certificado) && $params->hasStoredSenhaCertificado()
                ? (string) ($params->safeSenhaCertificado() ?? '')
                : '',
            'crypt_lib' => (string) ($params->crypt_lib ?? '1'),
            'http_lib' => (string) ($params->http_lib ?? '2'),
            'xml_sign' => (string) ($params->xml_sign ?? '4'),
            'ssl_tipo' => (int) ($params->ssl_tipo ?? 5),
            'id_token' => $params->id_token ?? '',
            'token' => $params->token ?? '',
            'path_salvar_nfe' => $params->path_salvar_nfe ?? '',
            'path_schemas_nfe' => $params->path_schemas_nfe ?? '',
            'path_enviada_nfe' => $params->path_enviada_nfe ?? '',
            'path_can_nfe' => $params->path_can_nfe ?? '',
            'path_inuti_nfe' => $params->path_inuti_nfe ?? '',
            'path_evento_nfe' => $params->path_evento_nfe ?? '',
            'path_pdf_nfe' => $params->path_pdf_nfe ?? '',
            'logomarca' => $params->logomarca ?? '',
            'numero' => (int) ($params->numero ?? 1),
            'serie' => (string) ($params->serie ?? '1'),
            'serie_nfe' => (int) ($params->serie_nfe ?? 1),
            'email_host' => $params->email_host ?? '',
            'email_porta' => $params->email_porta ?? '',
            'email_user' => $params->email_user ?? '',
            'email_senha' => '',
            'email_assunto' => $params->email_assunto ?? '',
            'email_ssl' => ($params->email_ssl ?? 'N') === 'S',
            'email_tls' => ($params->email_tls ?? 'N') === 'S',
            'caminho_certificado' => $params->caminho_certificado ?? '',
        ];
    }
}
