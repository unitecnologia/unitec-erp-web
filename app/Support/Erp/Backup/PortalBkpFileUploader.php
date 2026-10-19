<?php

namespace App\Support\Erp\Backup;

use App\Models\Empresa;
use App\Support\Erp\ErpSystemConfig;
use App\Support\Erp\License\LicencaHttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class PortalBkpFileUploader
{
    private const MAX_BYTES = 128 * 1024 * 1024;

    private const MAX_NAME_LEN = 180;

    /** Esperas entre tentativas (segundos), após a primeira. */
    private const RETRY_DELAYS = [30, 120, 300];

    public function __construct(
        private readonly PortalBkpUploadStore $store,
    ) {}

    /**
     * Registra envios (um por CNPJ) para o arquivo finalizado pelo gerador e tenta enviar os devidos.
     *
     * @return array{ok: bool, queued: int, confirmed: int, failed: int, message: string}
     */
    public function enqueueAndAttempt(string $absolutePath, ?string $generatedAtUtc = null): array
    {
        $queued = $this->registerForBackup($absolutePath, $generatedAtUtc);

        if ($queued === 0) {
            $ui = $this->store->uiStatus();

            return [
                'ok' => false,
                'queued' => 0,
                'confirmed' => 0,
                'failed' => 0,
                'message' => $ui['detail'] !== '' ? $ui['detail'] : 'Envio ao portal não enfileirado.',
            ];
        }

        return $this->attemptDueJobs();
    }

    /**
     * Cria jobs com UUID/metadados estáveis. Cancela pendências anteriores do mesmo CNPJ.
     */
    public function registerForBackup(string $absolutePath, ?string $generatedAtUtc = null): int
    {
        ErpSystemConfig::ensurePortalBkpToken();
        $token = ErpSystemConfig::portalBkpToken();

        if ($token === '') {
            $this->store->replaceJobs($this->store->read()['jobs'], [
                'portal_status' => 'skipped',
                'portal_message' => 'Token do portal não configurado; arquivo não enviado.',
            ]);

            return 0;
        }

        $path = $this->normalizePath($absolutePath);

        if (! is_file($path) || ! is_readable($path)) {
            $this->store->replaceJobs($this->store->read()['jobs'], [
                'portal_status' => PortalBkpUploadStore::STATUS_FAILED,
                'portal_message' => 'Arquivo de backup ausente ou sem leitura para envio ao portal.',
            ]);

            return 0;
        }

        clearstatcache(true, $path);
        $size = filesize($path);
        $hash = hash_file('sha256', $path);
        $name = basename($path);

        if ($size === false || $hash === false || $size < 1 || $size > self::MAX_BYTES) {
            $this->store->replaceJobs($this->store->read()['jobs'], [
                'portal_status' => PortalBkpUploadStore::STATUS_FAILED,
                'portal_message' => 'Tamanho ou hash do backup inválido para envio ao portal.',
                'file_name' => $name,
            ]);

            return 0;
        }

        if (! $this->isValidFileName($name)) {
            $this->store->replaceJobs($this->store->read()['jobs'], [
                'portal_status' => PortalBkpUploadStore::STATUS_FAILED,
                'portal_message' => 'Nome do arquivo de backup inválido para o portal.',
                'file_name' => $name,
            ]);

            return 0;
        }

        $generatedAt = $generatedAtUtc ?: gmdate('Y-m-d\TH:i:s.v\Z');
        $now = gmdate('c');
        $state = $this->store->read();
        $jobs = $state['jobs'];

        // Abandona envios pendentes de cópias anteriores (portal mantém só a mais recente).
        foreach ($jobs as $i => $job) {
            if (! in_array($job['status'], [PortalBkpUploadStore::STATUS_PENDING, PortalBkpUploadStore::STATUS_UPLOADING], true)) {
                continue;
            }

            $jobs[$i]['status'] = PortalBkpUploadStore::STATUS_FAILED;
            $jobs[$i]['last_code'] = 'SUPERSEDED_LOCAL';
            $jobs[$i]['last_message'] = 'Substituído por backup local mais recente.';
            $jobs[$i]['updated_at'] = $now;
            $jobs[$i]['next_retry_at'] = null;
        }

        $empresas = Empresa::query()->orderBy('id')->get();
        $created = 0;

        foreach ($empresas as $empresa) {
            $cnpj = preg_replace('/\D/', '', (string) ($empresa->cnpj ?? '')) ?: '';

            if (strlen($cnpj) !== 14) {
                continue;
            }

            $jobs[] = [
                'upload_id' => (string) Str::uuid(),
                'cnpj' => $cnpj,
                'path' => $path,
                'file_name' => $name,
                'sha256' => strtolower($hash),
                'size_bytes' => (int) $size,
                'generated_at' => $generatedAt,
                'status' => PortalBkpUploadStore::STATUS_PENDING,
                'attempts' => 0,
                'next_retry_at' => null,
                'last_http' => null,
                'last_code' => null,
                'last_message' => 'Aguardando envio ao portal.',
                'confirmed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $created++;
        }

        if ($created === 0) {
            $this->store->replaceJobs($jobs, [
                'portal_status' => 'skipped',
                'portal_message' => 'Nenhuma empresa com CNPJ válido para envio do arquivo.',
                'file_name' => $name,
            ]);

            return 0;
        }

        $this->store->replaceJobs($jobs, [
            'portal_status' => PortalBkpUploadStore::STATUS_PENDING,
            'portal_message' => 'Backup local concluído; envio do arquivo pendente.',
            'file_name' => $name,
        ]);

        return $created;
    }

    /**
     * Processa jobs pendentes cujo next_retry_at já venceu.
     *
     * @return array{ok: bool, queued: int, confirmed: int, failed: int, message: string}
     */
    public function attemptDueJobs(): array
    {
        $state = $this->store->read();
        $jobs = $state['jobs'];
        $confirmed = 0;
        $failed = 0;
        $attempted = 0;
        $nowTs = time();

        foreach ($jobs as $i => $job) {
            if (($job['status'] ?? '') !== PortalBkpUploadStore::STATUS_PENDING) {
                if (($job['status'] ?? '') === PortalBkpUploadStore::STATUS_CONFIRMED) {
                    $confirmed++;
                }
                if (($job['status'] ?? '') === PortalBkpUploadStore::STATUS_FAILED) {
                    $failed++;
                }

                continue;
            }

            $next = $job['next_retry_at'] ?? null;
            if (is_string($next) && $next !== '' && strtotime($next) > $nowTs) {
                continue;
            }

            $attempted++;
            $jobs[$i] = $this->uploadJob($job);
            $status = $jobs[$i]['status'] ?? '';

            if ($status === PortalBkpUploadStore::STATUS_CONFIRMED) {
                $confirmed++;
            } elseif ($status === PortalBkpUploadStore::STATUS_FAILED) {
                $failed++;
            }
        }

        $this->store->replaceJobs($jobs);
        $ui = $this->store->uiStatus();

        return [
            'ok' => $ui['status'] === PortalBkpUploadStore::STATUS_CONFIRMED,
            'queued' => $attempted,
            'confirmed' => $confirmed,
            'failed' => $failed,
            'message' => $ui['detail'] !== '' ? $ui['detail'] : $ui['label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>
     */
    private function uploadJob(array $job): array
    {
        $now = gmdate('c');
        $job['status'] = PortalBkpUploadStore::STATUS_UPLOADING;
        $job['updated_at'] = $now;
        $this->store->upsertJob($job);

        $token = ErpSystemConfig::portalBkpToken();
        $path = (string) ($job['path'] ?? '');
        $uploadId = (string) ($job['upload_id'] ?? '');
        $cnpj = (string) ($job['cnpj'] ?? '');
        $expectedHash = strtolower((string) ($job['sha256'] ?? ''));
        $expectedSize = (int) ($job['size_bytes'] ?? 0);
        $generatedAt = (string) ($job['generated_at'] ?? '');
        $fileName = (string) ($job['file_name'] ?? '');

        if ($token === '' || $uploadId === '' || strlen($cnpj) !== 14) {
            return $this->failPermanent($job, null, 'CONFIG', 'Configuração de envio incompleta.');
        }

        if (! is_file($path) || ! is_readable($path)) {
            return $this->failPermanent($job, null, 'FILE_GONE', 'Arquivo local removido antes do envio; não reutilizar o UUID com outro arquivo.');
        }

        clearstatcache(true, $path);
        $size = filesize($path);
        $hash = hash_file('sha256', $path);

        if ($size === false || $hash === false || (int) $size !== $expectedSize || strtolower($hash) !== $expectedHash) {
            return $this->failPermanent($job, null, 'FILE_CHANGED', 'Arquivo local alterado após o registro do envio; gere um novo backup.');
        }

        if (! $this->isValidFileName($fileName)) {
            return $this->failPermanent($job, null, 'BAD_NAME', 'Nome do arquivo inválido.');
        }

        $baseUrl = rtrim((string) config('unitec.licenca_api.base_url', 'https://unitecnologiasc.digital'), '/');
        $url = $baseUrl.'/api/bkp/'.$cnpj.'/arquivo?arquivo='.rawurlencode($fileName);

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            return $this->failRetryable($job, null, 'IO', 'Não foi possível abrir o backup para envio.');
        }

        try {
            $response = LicencaHttpClient::make()
                ->connectTimeout(15)
                ->timeout(150)
                ->withHeaders([
                    'X-BKP-Token' => $token,
                    'Content-Type' => 'application/octet-stream',
                    'Content-Length' => (string) $expectedSize,
                    'X-BKP-Upload-Id' => $uploadId,
                    'X-BKP-SHA256' => $expectedHash,
                    'X-BKP-Generated-At' => $generatedAt,
                    'Accept' => 'application/json',
                ])
                ->withOptions([
                    'curl' => [
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    ],
                ])
                ->send('PUT', $url, ['body' => $stream]);
        } catch (Throwable $e) {
            Log::warning('Falha de conexão ao enviar backup ao portal.', [
                'cnpj' => $cnpj,
                'upload_id' => $uploadId,
                'message' => $e->getMessage(),
            ]);

            return $this->failRetryable($job, null, 'CONNECTION', 'Conexão interrompida ou resposta perdida; nova tentativa com o mesmo UUID.');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $http = $response->status();
        $contentType = strtolower((string) $response->header('Content-Type'));
        $data = $response->json();
        $data = is_array($data) ? $data : [];
        $code = isset($data['code']) ? (string) $data['code'] : null;
        $message = isset($data['message']) ? (string) $data['message'] : (isset($data['erro']) ? (string) $data['erro'] : '');

        if ($data === [] && (str_contains($contentType, 'text/html') || str_starts_with(ltrim((string) $response->body()), '<'))) {
            return $this->failRetryable(
                $job,
                $http,
                'ENDPOINT_UNAVAILABLE',
                'Endpoint de arquivo indisponível no portal (ainda não publicado ou URL incorreta).',
            );
        }

        $confirmed = in_array($http, [200, 201], true)
            && ($data['ok'] ?? false) === true
            && ($data['status'] ?? '') === 'recebido'
            && (string) ($data['uploadId'] ?? '') === $uploadId
            && strtolower((string) ($data['sha256'] ?? '')) === $expectedHash
            && (int) ($data['sizeBytes'] ?? 0) === $expectedSize;

        if ($confirmed) {
            $job['status'] = PortalBkpUploadStore::STATUS_CONFIRMED;
            $job['attempts'] = ((int) ($job['attempts'] ?? 0)) + 1;
            $job['last_http'] = $http;
            $job['last_code'] = (($data['replayed'] ?? false) === true) ? 'REPLAYED' : 'RECEBIDO';
            $job['last_message'] = (($data['replayed'] ?? false) === true)
                ? 'Portal confirmou envio já recebido (replay).'
                : 'Arquivo recebido no portal.';
            $job['confirmed_at'] = gmdate('c');
            $job['next_retry_at'] = null;
            $job['updated_at'] = gmdate('c');

            return $job;
        }

        // 200 do POST de log não se aplica aqui; qualquer outro 2xx sem contrato = não confirmado.
        if (in_array($http, [400, 401, 404, 413, 415, 422], true) || in_array($code, ['CLIENT_NOT_FOUND', 'ID_REUSED', 'OLDER_BACKUP'], true)) {
            return $this->failPermanent(
                $job,
                $http,
                $code ?: 'HTTP_'.$http,
                $message !== '' ? $message : 'Portal recusou o envio (HTTP '.$http.').',
            );
        }

        if ($http === 409 && $code === 'ID_REUSED') {
            return $this->failPermanent($job, $http, $code, $message !== '' ? $message : 'UUID reutilizado com outro conteúdo.');
        }

        return $this->failRetryable(
            $job,
            $http,
            $code ?: 'HTTP_'.$http,
            $message !== '' ? $message : 'Falha temporária no envio (HTTP '.$http.').',
        );
    }

    /**
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>
     */
    private function failPermanent(array $job, ?int $http, string $code, string $message): array
    {
        $job['status'] = PortalBkpUploadStore::STATUS_FAILED;
        $job['attempts'] = ((int) ($job['attempts'] ?? 0)) + 1;
        $job['last_http'] = $http;
        $job['last_code'] = $code;
        $job['last_message'] = mb_substr($message, 0, 500);
        $job['next_retry_at'] = null;
        $job['updated_at'] = gmdate('c');

        Log::warning('Envio de backup ao portal falhou (permanente).', [
            'cnpj' => $job['cnpj'] ?? null,
            'upload_id' => $job['upload_id'] ?? null,
            'http' => $http,
            'code' => $code,
            'message' => $message,
        ]);

        return $job;
    }

    /**
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>
     */
    private function failRetryable(array $job, ?int $http, string $code, string $message): array
    {
        $attempts = ((int) ($job['attempts'] ?? 0)) + 1;
        $job['attempts'] = $attempts;
        $job['last_http'] = $http;
        $job['last_code'] = $code;
        $job['last_message'] = mb_substr($message, 0, 500);
        $job['updated_at'] = gmdate('c');

        $delayIndex = $attempts - 1;
        if ($delayIndex >= count(self::RETRY_DELAYS)) {
            $job['status'] = PortalBkpUploadStore::STATUS_FAILED;
            $job['next_retry_at'] = null;
            $job['last_message'] = mb_substr($message.' Esgotadas as novas tentativas automáticas.', 0, 500);
        } else {
            $delay = self::RETRY_DELAYS[$delayIndex] + random_int(0, 15);
            $job['status'] = PortalBkpUploadStore::STATUS_PENDING;
            $job['next_retry_at'] = gmdate('c', time() + $delay);
            $job['last_message'] = mb_substr($message.' Nova tentativa em ~'.$delay.'s.', 0, 500);
        }

        Log::warning('Envio de backup ao portal falhou (temporário).', [
            'cnpj' => $job['cnpj'] ?? null,
            'upload_id' => $job['upload_id'] ?? null,
            'http' => $http,
            'code' => $code,
            'attempts' => $attempts,
        ]);

        return $job;
    }

    private function isValidFileName(string $name): bool
    {
        if ($name === '' || strlen($name) > self::MAX_NAME_LEN) {
            return false;
        }

        if (str_starts_with($name, '.')) {
            return false;
        }

        if (str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            return false;
        }

        return true;
    }

    private function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
