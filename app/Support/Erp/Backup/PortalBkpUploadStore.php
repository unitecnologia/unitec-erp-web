<?php

namespace App\Support\Erp\Backup;

use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Persistência local dos envios de arquivo de backup ao portal (sem migration).
 *
 * @phpstan-type UploadJob array{
 *     upload_id: string,
 *     cnpj: string,
 *     path: string,
 *     file_name: string,
 *     sha256: string,
 *     size_bytes: int,
 *     generated_at: string,
 *     status: string,
 *     attempts: int,
 *     next_retry_at: ?string,
 *     last_http: ?int,
 *     last_code: ?string,
 *     last_message: ?string,
 *     confirmed_at: ?string,
 *     created_at: string,
 *     updated_at: string
 * }
 */
final class PortalBkpUploadStore
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    private const RELATIVE_PATH = 'app/backup/portal-upload-state.json';

    public function path(): string
    {
        return storage_path(self::RELATIVE_PATH);
    }

    /**
     * @return array{jobs: list<UploadJob>, summary: array<string, mixed>}
     */
    public function read(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return ['jobs' => [], 'summary' => []];
        }

        try {
            $raw = File::get($path);
            $data = is_string($raw) ? json_decode($raw, true) : null;
        } catch (Throwable) {
            return ['jobs' => [], 'summary' => []];
        }

        if (! is_array($data)) {
            return ['jobs' => [], 'summary' => []];
        }

        $jobs = [];
        foreach (($data['jobs'] ?? []) as $job) {
            if (is_array($job) && filled($job['upload_id'] ?? null)) {
                $jobs[] = $this->normalizeJob($job);
            }
        }

        return [
            'jobs' => $jobs,
            'summary' => is_array($data['summary'] ?? null) ? $data['summary'] : [],
        ];
    }

    /**
     * @param  list<UploadJob>  $jobs
     * @param  array<string, mixed>  $summary
     */
    public function write(array $jobs, array $summary = []): void
    {
        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));

        $payload = [
            'jobs' => array_values($jobs),
            'summary' => $summary,
            'updated_at' => gmdate('c'),
        ];

        File::put(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{"jobs":[],"summary":{}}',
        );
    }

    /**
     * @param  UploadJob  $job
     */
    public function upsertJob(array $job): void
    {
        $state = $this->read();
        $jobs = $state['jobs'];
        $found = false;

        foreach ($jobs as $i => $existing) {
            if (($existing['upload_id'] ?? '') === $job['upload_id']) {
                $jobs[$i] = $this->normalizeJob($job);
                $found = true;
                break;
            }
        }

        if (! $found) {
            $jobs[] = $this->normalizeJob($job);
        }

        $this->write($jobs, $this->buildSummary($jobs, $state['summary']));
    }

    /**
     * @param  list<UploadJob>  $jobs
     * @param  array<string, mixed>  $extra
     */
    public function replaceJobs(array $jobs, array $extra = []): void
    {
        $state = $this->read();
        $summary = array_merge($state['summary'], $extra);
        $this->write(array_map(fn (array $job) => $this->normalizeJob($job), $jobs), $this->buildSummary($jobs, $summary));
    }

    /**
     * @return list<string>
     */
    public function protectedPaths(): array
    {
        $paths = [];

        foreach ($this->read()['jobs'] as $job) {
            if (! in_array($job['status'], [self::STATUS_PENDING, self::STATUS_UPLOADING], true)) {
                continue;
            }

            $path = (string) ($job['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $real = realpath($path) ?: $path;
            $paths[$real] = $real;
        }

        return array_values($paths);
    }

    /**
     * @return array{status: string, label: string, detail: string, file_name: ?string}
     */
    public function uiStatus(): array
    {
        $summary = $this->read()['summary'];
        $status = (string) ($summary['portal_status'] ?? '');

        if ($status === '') {
            $jobs = $this->read()['jobs'];
            $status = $this->derivePortalStatus($jobs);
        }

        $labels = [
            self::STATUS_PENDING => 'Envio pendente',
            self::STATUS_UPLOADING => 'Enviando…',
            self::STATUS_CONFIRMED => 'Arquivo no portal',
            self::STATUS_FAILED => 'Falha no envio',
            'skipped' => 'Não enviado',
        ];

        return [
            'status' => $status,
            'label' => $labels[$status] ?? ($status !== '' ? $status : '—'),
            'detail' => (string) ($summary['portal_message'] ?? ''),
            'file_name' => isset($summary['file_name']) ? (string) $summary['file_name'] : null,
        ];
    }

    /**
     * @param  list<UploadJob>  $jobs
     * @param  array<string, mixed>  $previous
     * @return array<string, mixed>
     */
    public function buildSummary(array $jobs, array $previous = []): array
    {
        // Preferir o job mais recente por created_at.
        usort($jobs, fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        $active = $jobs[0] ?? null;

        $status = $this->derivePortalStatus($jobs);

        return array_merge($previous, [
            'portal_status' => $status,
            'portal_message' => $active['last_message'] ?? ($previous['portal_message'] ?? null),
            'file_name' => $active['file_name'] ?? ($previous['file_name'] ?? null),
            'upload_id' => $active['upload_id'] ?? null,
            'local_path' => $active['path'] ?? null,
        ]);
    }

    /**
     * @param  list<UploadJob>  $jobs
     */
    private function derivePortalStatus(array $jobs): string
    {
        if ($jobs === []) {
            return '';
        }

        usort($jobs, fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        // Agrupa pela geração mais recente (mesmo arquivo).
        $latestKey = ((string) ($jobs[0]['path'] ?? '')).'|'.((string) ($jobs[0]['generated_at'] ?? ''));
        $cohort = array_values(array_filter(
            $jobs,
            fn (array $job): bool => (((string) ($job['path'] ?? '')).'|'.((string) ($job['generated_at'] ?? ''))) === $latestKey
        ));

        $statuses = array_column($cohort, 'status');

        if (in_array(self::STATUS_UPLOADING, $statuses, true)) {
            return self::STATUS_UPLOADING;
        }

        if (in_array(self::STATUS_PENDING, $statuses, true)) {
            return self::STATUS_PENDING;
        }

        if ($statuses !== [] && count(array_unique($statuses)) === 1 && $statuses[0] === self::STATUS_CONFIRMED) {
            return self::STATUS_CONFIRMED;
        }

        if (in_array(self::STATUS_FAILED, $statuses, true) && ! in_array(self::STATUS_PENDING, $statuses, true)) {
            // Se ao menos um CNPJ confirmou, prioriza confirmed parcial como pending? Melhor: confirmed se todos ok, senão failed se algum falhou e nenhum pendente.
            if (in_array(self::STATUS_CONFIRMED, $statuses, true) && ! in_array(self::STATUS_FAILED, $statuses, true)) {
                return self::STATUS_CONFIRMED;
            }

            if (in_array(self::STATUS_CONFIRMED, $statuses, true)) {
                return self::STATUS_PENDING;
            }

            return self::STATUS_FAILED;
        }

        if (in_array(self::STATUS_CONFIRMED, $statuses, true)) {
            return self::STATUS_CONFIRMED;
        }

        return (string) ($statuses[0] ?? '');
    }

    /**
     * @param  array<string, mixed>  $job
     * @return UploadJob
     */
    private function normalizeJob(array $job): array
    {
        return [
            'upload_id' => (string) ($job['upload_id'] ?? ''),
            'cnpj' => preg_replace('/\D/', '', (string) ($job['cnpj'] ?? '')) ?: '',
            'path' => (string) ($job['path'] ?? ''),
            'file_name' => (string) ($job['file_name'] ?? ''),
            'sha256' => strtolower((string) ($job['sha256'] ?? '')),
            'size_bytes' => (int) ($job['size_bytes'] ?? 0),
            'generated_at' => (string) ($job['generated_at'] ?? ''),
            'status' => (string) ($job['status'] ?? self::STATUS_PENDING),
            'attempts' => (int) ($job['attempts'] ?? 0),
            'next_retry_at' => isset($job['next_retry_at']) ? (string) $job['next_retry_at'] : null,
            'last_http' => isset($job['last_http']) ? (int) $job['last_http'] : null,
            'last_code' => isset($job['last_code']) ? (string) $job['last_code'] : null,
            'last_message' => isset($job['last_message']) ? (string) $job['last_message'] : null,
            'confirmed_at' => isset($job['confirmed_at']) ? (string) $job['confirmed_at'] : null,
            'created_at' => (string) ($job['created_at'] ?? gmdate('c')),
            'updated_at' => (string) ($job['updated_at'] ?? gmdate('c')),
        ];
    }
}
