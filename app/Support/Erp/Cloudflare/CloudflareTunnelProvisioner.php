<?php

namespace App\Support\Erp\Cloudflare;

use App\Models\Empresa;
use App\Support\Erp\CloudflaredStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Provisiona túnel Cloudflare + DNS + config.yml local (UnitecErpServer / cloudflared).
 */
final class CloudflareTunnelProvisioner
{
    /** @var list<string> */
    private const RESERVED_SUBDOMAINS = [
        'www', 'erp', 'gestor', 'api', 'mail', 'ftp', 'admin', 'app', 'cdn',
        'static', 'dev', 'test', 'staging', 'portal', 'update', 'docs',
    ];

    /**
     * @param  array{
     *   subdomain?: string,
     *   api_token?: string,
     *   account_id?: string,
     *   zone_id?: string,
     *   base_domain?: string,
     * }  $overrides  valores do formulário (empresa) — vazios caem no .env
     * @return array{
     *   subdomain: string,
     *   hostname: string,
     *   erp_url: string,
     *   gestor_url: string,
     *   tunnel_id: string,
     *   base_domain: string,
     *   created_tunnel: bool,
     *   recreated_missing_credentials: bool,
     *   program_data_dir: string,
     * }
     */
    public function provision(Empresa $empresa, array $overrides = []): array
    {
        $creds = $this->resolveCredentials($empresa, $overrides);
        $baseDomain = $creds['base_domain'];
        $subdomain = $this->normalizeSubdomain(
            (string) ($overrides['subdomain'] ?? $empresa->param_cf_subdomain ?? ''),
            $empresa
        );
        $hostname = $subdomain.'.'.$baseDomain;
        $localService = rtrim((string) config('unitec.cloudflare.local_service', 'http://127.0.0.1:8765'), '/');
        $programData = rtrim((string) config('unitec.cloudflare.program_data_dir', 'C:\\ProgramData\\Unitec\\cloudflared'), '\\/');

        $existingTunnelId = trim((string) ($empresa->param_cf_tunnel_id ?? ''));
        $createdTunnel = false;
        $recreatedMissingCredentials = false;

        $existingCredentialsPath = $existingTunnelId !== ''
            ? $programData.DIRECTORY_SEPARATOR.$existingTunnelId.'.json'
            : '';

        if ($existingTunnelId !== '' && is_file($existingCredentialsPath)) {
            $tunnelId = $existingTunnelId;
            $tunnelSecret = null;
        } else {
            // Sem Tunnel ID, ou ID vindo de restore/outro PC sem o JSON local:
            // Cloudflare não devolve o secret — precisa criar túnel novo neste PC.
            $orphanTunnelId = $existingTunnelId;
            $created = $this->createLocalTunnel(
                $creds,
                $this->tunnelName($empresa, $subdomain, $orphanTunnelId !== '')
            );
            $tunnelId = $created['id'];
            $tunnelSecret = $created['secret'];
            $createdTunnel = true;
            $recreatedMissingCredentials = $orphanTunnelId !== '';
        }

        $this->ensureDnsCname($creds, $hostname, $tunnelId);
        $this->writeLocalFiles($programData, $tunnelId, $creds['account_id'], $tunnelSecret, $hostname, $localService);

        CloudflaredStatus::ensureExeInProgramData();

        try {
            CloudflaredStatus::requestRestart();
        } catch (\Throwable) {
            // config.yml já está gravado; o Ensure periódico do serviço ainda sobe o túnel.
        }

        return [
            'subdomain' => $subdomain,
            'hostname' => $hostname,
            'erp_url' => 'https://'.$hostname,
            'gestor_url' => 'https://'.$hostname.'/gestor',
            'tunnel_id' => $tunnelId,
            'base_domain' => $baseDomain,
            'created_tunnel' => $createdTunnel,
            'recreated_missing_credentials' => $recreatedMissingCredentials,
            'program_data_dir' => $programData,
        ];
    }

    public function suggestSubdomain(Empresa|string|null $empresaOrName): string
    {
        $name = $empresaOrName instanceof Empresa
            ? (string) ($empresaOrName->fantasia ?: $empresaOrName->razao_social ?: $empresaOrName->nome ?: '')
            : (string) $empresaOrName;

        return $this->slugify($name);
    }

    public function subdomainFromPublicUrl(string $url): string
    {
        $raw = strtolower(trim($url));
        if ($raw === '') {
            return '';
        }

        $host = strtolower(trim((string) parse_url($raw, PHP_URL_HOST)));
        if ($host === '') {
            $host = preg_replace('#^https?://#', '', $raw) ?? $raw;
            $host = explode('/', $host)[0] ?? '';
            $host = explode(':', $host)[0] ?? '';
        }

        $base = strtolower(trim((string) config('unitec.cloudflare.base_domain', 'unierp.uk')));
        if ($base !== '' && str_ends_with($host, '.'.$base)) {
            return $this->slugify(substr($host, 0, -strlen('.'.$base)));
        }

        $parts = explode('.', $host);

        return $this->slugify((string) ($parts[0] ?? ''));
    }

    /**
     * @param  array{
     *   subdomain?: string,
     *   api_token?: string,
     *   account_id?: string,
     *   zone_id?: string,
     *   base_domain?: string,
     * }  $overrides
     * @return array{api_token: string, account_id: string, zone_id: string, base_domain: string}
     */
    private function resolveCredentials(Empresa $empresa, array $overrides): array
    {
        $cfg = (array) config('unitec.cloudflare', []);

        $pick = static function (string $overrideKey, string $empresaAttr, string $configKey) use ($empresa, $overrides, $cfg): string {
            $fromForm = trim((string) ($overrides[$overrideKey] ?? ''));
            if ($fromForm !== '') {
                return $fromForm;
            }

            $fromEmpresa = trim((string) ($empresa->getAttribute($empresaAttr) ?? ''));
            if ($fromEmpresa !== '') {
                return $fromEmpresa;
            }

            return trim((string) ($cfg[$configKey] ?? ''));
        };

        $apiToken = $pick('api_token', 'param_cf_api_token', 'api_token');
        $accountId = $pick('account_id', 'param_cf_account_id', 'account_id');
        $zoneId = $pick('zone_id', 'param_cf_zone_id', 'zone_id');
        $baseDomain = strtolower($pick('base_domain', 'param_cf_base_domain', 'base_domain'));
        if ($baseDomain === '') {
            $baseDomain = 'unierp.uk';
        }

        if ($apiToken === '') {
            throw new RuntimeException('Informe o Token Cloudflare (campo na empresa ou CLOUDFLARE_API_TOKEN no .env).');
        }
        if ($accountId === '') {
            throw new RuntimeException('Informe o Account ID Cloudflare (campo na empresa ou CLOUDFLARE_ACCOUNT_ID no .env).');
        }
        if ($zoneId === '') {
            throw new RuntimeException('Informe o Zone ID Cloudflare (campo na empresa ou CLOUDFLARE_ZONE_ID no .env).');
        }

        return [
            'api_token' => $apiToken,
            'account_id' => $accountId,
            'zone_id' => $zoneId,
            'base_domain' => $baseDomain,
        ];
    }

    private function normalizeSubdomain(string $raw, Empresa $empresa): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            $raw = $this->suggestSubdomain($empresa);
        }

        $slug = $this->slugify($raw);
        if ($slug === '') {
            throw new RuntimeException('Informe um subdomínio válido (ex.: mesavirada).');
        }
        if (in_array($slug, self::RESERVED_SUBDOMAINS, true)) {
            throw new RuntimeException("O subdomínio \"{$slug}\" é reservado. Escolha outro nome.");
        }
        if (strlen($slug) > 63) {
            throw new RuntimeException('Subdomínio muito longo (máx. 63 caracteres).');
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value), 'UTF-8'));
        // Compacto: "Mesa Virada" → mesavirada (host curto).
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';

        return $value;
    }

    private function tunnelName(Empresa $empresa, string $subdomain, bool $uniqueSuffix = false): string
    {
        $codigo = trim((string) ($empresa->codigo ?? ''));
        $base = $codigo !== '' ? 'unitec-'.$codigo.'-'.$subdomain : 'unitec-'.$subdomain;
        if ($uniqueSuffix) {
            $base .= '-'.substr(bin2hex(random_bytes(3)), 0, 6);
        }

        return substr($base, 0, 90);
    }

    /**
     * @param  array{api_token: string, account_id: string, zone_id: string, base_domain: string}  $creds
     * @return array{id: string, secret: string}
     */
    private function createLocalTunnel(array $creds, string $name): array
    {
        $secret = base64_encode(random_bytes(32));

        $payload = $this->request(
            $creds['api_token'],
            'post',
            "https://api.cloudflare.com/client/v4/accounts/{$creds['account_id']}/cfd_tunnel",
            [
                'name' => $name,
                'config_src' => 'local',
                'tunnel_secret' => $secret,
            ]
        );

        $id = trim((string) ($payload['result']['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('Cloudflare não retornou o ID do túnel.');
        }

        return ['id' => $id, 'secret' => $secret];
    }

    /**
     * @param  array{api_token: string, account_id: string, zone_id: string, base_domain: string}  $creds
     */
    private function ensureDnsCname(array $creds, string $hostname, string $tunnelId): void
    {
        $target = strtolower($tunnelId.'.cfargotunnel.com');
        $list = $this->request(
            $creds['api_token'],
            'get',
            "https://api.cloudflare.com/client/v4/zones/{$creds['zone_id']}/dns_records",
            query: [
                'type' => 'CNAME',
                'name' => $hostname,
            ]
        );

        $records = $list['result'] ?? [];
        if (! is_array($records)) {
            $records = [];
        }

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $content = strtolower(trim((string) ($record['content'] ?? '')));
            $recordId = trim((string) ($record['id'] ?? ''));

            if ($content === $target) {
                // Já aponta para este túnel.
                if ($recordId !== '' && ! ($record['proxied'] ?? false)) {
                    $this->request(
                        $creds['api_token'],
                        'patch',
                        "https://api.cloudflare.com/client/v4/zones/{$creds['zone_id']}/dns_records/{$recordId}",
                        ['proxied' => true]
                    );
                }

                return;
            }

            // Pós-restore / recriação: CNAME ainda aponta para outro túnel Unitec — reapontar.
            if ($recordId !== '' && str_ends_with($content, '.cfargotunnel.com')) {
                $this->request(
                    $creds['api_token'],
                    'patch',
                    "https://api.cloudflare.com/client/v4/zones/{$creds['zone_id']}/dns_records/{$recordId}",
                    [
                        'type' => 'CNAME',
                        'name' => $hostname,
                        'content' => $target,
                        'proxied' => true,
                        'ttl' => 1,
                    ]
                );

                return;
            }

            throw new RuntimeException(
                "O hostname {$hostname} já existe no DNS apontando para \"{$content}\". Escolha outro subdomínio."
            );
        }

        $this->request(
            $creds['api_token'],
            'post',
            "https://api.cloudflare.com/client/v4/zones/{$creds['zone_id']}/dns_records",
            [
                'type' => 'CNAME',
                'name' => $hostname,
                'content' => $target,
                'proxied' => true,
                'ttl' => 1,
            ]
        );
    }

    private function writeLocalFiles(
        string $programData,
        string $tunnelId,
        string $accountId,
        ?string $tunnelSecret,
        string $hostname,
        string $localService,
    ): void {
        if (! is_dir($programData) && ! @mkdir($programData, 0775, true) && ! is_dir($programData)) {
            throw new RuntimeException("Não foi possível criar a pasta {$programData}. Execute o ERP como administrador uma vez ou crie a pasta manualmente.");
        }

        $credentialsPath = $programData.DIRECTORY_SEPARATOR.$tunnelId.'.json';

        if ($tunnelSecret !== null) {
            $credentials = [
                'AccountTag' => $accountId,
                'TunnelSecret' => $tunnelSecret,
                'TunnelID' => $tunnelId,
            ];
            $json = json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false || @file_put_contents($credentialsPath, $json) === false) {
                throw new RuntimeException("Falha ao gravar credencial do túnel em {$credentialsPath}.");
            }
        } elseif (! is_file($credentialsPath)) {
            // Defesa: provision() já deveria ter recriado o túnel antes.
            throw new RuntimeException(
                "Falta o arquivo de credencial em {$credentialsPath}. Ative o túnel novamente para recriar."
            );
        }

        $configPath = $programData.DIRECTORY_SEPARATOR.'config.yml';
        $credentialsPathYaml = str_replace('\\', '/', $credentialsPath);
        $yml = <<<YML
# Gerado pelo Unitec ERP — não editar à mão se for reprovisionar pela tela.
tunnel: {$tunnelId}
credentials-file: {$credentialsPathYaml}

ingress:
  - hostname: {$hostname}
    service: {$localService}
  - service: http_status:404
YML;

        if (@file_put_contents($configPath, $yml) === false) {
            throw new RuntimeException("Falha ao gravar {$configPath}.");
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $token, string $method, string $url, ?array $json = null, array $query = []): array
    {
        $pending = Http::withToken($token)
            ->acceptJson()
            ->timeout(45)
            ->withHeaders(['Content-Type' => 'application/json']);

        $response = match (strtolower($method)) {
            'get' => $pending->get($url, $query),
            'post' => $pending->post($url, $json ?? []),
            'put' => $pending->put($url, $json ?? []),
            'patch' => $pending->patch($url, $json ?? []),
            'delete' => $pending->delete($url, $json ?? []),
            default => throw new RuntimeException("Método HTTP inválido: {$method}"),
        };

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful() || ($body['success'] ?? false) !== true) {
            $errors = $body['errors'] ?? [];
            $messages = [];
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    if (is_array($error)) {
                        $messages[] = trim((string) ($error['message'] ?? json_encode($error)));
                    }
                }
            }
            $detail = $messages !== [] ? implode(' | ', $messages) : ('HTTP '.$response->status());

            throw new RuntimeException('Cloudflare API: '.$detail);
        }

        return $body;
    }
}
