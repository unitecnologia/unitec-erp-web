<?php

namespace App\Support\ContadorCloud;

use App\Jobs\ContadorCloudProcessPendingJob;
use App\Models\ContadorCloudSyncLog;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\PdvVendaNfce;
use Illuminate\Support\Facades\Log;

final class ContadorCloudSyncService
{
    /** Tentativas automáticas antes de deixar só o reenvio manual. */
    private const MAX_AUTO_ATTEMPTS = 60;

    public function __construct(
        private readonly ContadorCloudDocumentPayloadBuilder $payloadBuilder = new ContadorCloudDocumentPayloadBuilder(),
        private readonly ContadorCloudClient $client = new ContadorCloudClient(),
    ) {}

    /**
     * @param  array<string, mixed>  $documento
     */
    public function dispatch(Empresa $empresa, array $documento, bool $immediate = true, bool $force = false): ?ContadorCloudSyncLog
    {
        $config = ContadorCloudConfig::fromEmpresa($empresa);

        if (! $config->isActive()) {
            return null;
        }

        if (! $this->shouldDispatch($config, $documento)) {
            return $this->registrarIgnorado($empresa, $documento, 'Tipo de documento desabilitado nos parâmetros.');
        }

        if (! $config->enviarXml) {
            unset($documento['xml_base64']);
        }

        $chave = (string) ($documento['chave'] ?? '');
        $evento = (string) ($documento['evento'] ?? ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO);

        if ($chave !== '' && ! $force && $this->jaRegistrado($empresa, $chave, $evento)) {
            return null;
        }

        // Reenvio forçado (ex.: canceladas sem valorTotal): marca o envio anterior para não bloquear.
        if ($force && $chave !== '') {
            $this->marcarSuperseded($empresa, $chave, $evento);
        }

        if (! $immediate) {
            return $this->criarPendente($empresa, $documento);
        }

        $payload = $this->payloadBuilder->buildEnvelope($config, $documento);
        $log = $this->enviar($empresa, $config, $documento, $payload, agendarRetrySeFalhar: true);

        return $log;
    }

    /**
     * Dispara reenvio em segundo plano (após a resposta HTTP ou no schedule).
     */
    public static function agendarRetryAutomatico(?int $empresaId = null, int $limit = 100): void
    {
        ContadorCloudProcessPendingJob::dispatch(max(0, (int) $empresaId), $limit)->afterResponse();
    }

    /**
     * Envia logs pendentes (útil após importação em lote).
     *
     * @return array{enviados: int, falhas: int}
     */
    public function processPending(?int $empresaId = null, int $limit = 200): array
    {
        return $this->processRetryable($empresaId, $limit);
    }

    /**
     * Reenvia pendentes e falhas (ex.: contingência criada sem internet).
     *
     * @return array{enviados: int, falhas: int, ignorados: int}
     */
    public function processRetryable(?int $empresaId = null, int $limit = 200): array
    {
        $query = ContadorCloudSyncLog::query()
            ->where(function ($q): void {
                $q->where('status', ContadorCloudSyncLog::STATUS_PENDING)
                    ->orWhere(function ($failed): void {
                        $failed->where('status', ContadorCloudSyncLog::STATUS_FAILED)
                            ->where('attempts', '<', self::MAX_AUTO_ATTEMPTS);
                    });
            })
            ->orderBy('id')
            ->limit(max(1, $limit));

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        $enviados = 0;
        $falhas = 0;
        $ignorados = 0;

        foreach ($query->get() as $log) {
            $resultado = $this->retry($log);

            if ($resultado->status === ContadorCloudSyncLog::STATUS_SENT) {
                $enviados++;
            } elseif ($resultado->status === ContadorCloudSyncLog::STATUS_SKIPPED) {
                $ignorados++;
            } else {
                $falhas++;
            }
        }

        return ['enviados' => $enviados, 'falhas' => $falhas, 'ignorados' => $ignorados];
    }

    public function retry(ContadorCloudSyncLog $log): ContadorCloudSyncLog
    {
        $empresa = $log->empresa;

        if (! $empresa) {
            $log->update([
                'status' => ContadorCloudSyncLog::STATUS_FAILED,
                'error_message' => 'Empresa não encontrada para reenvio.',
            ]);

            return $log->fresh() ?? $log;
        }

        $config = ContadorCloudConfig::fromEmpresa($empresa);

        if (! $config->isActive()) {
            return $log;
        }

        // Contingência antiga: se a nota já foi autorizada/cancelada, não reenviar XML de contingência.
        if ((string) $log->evento === ContadorCloudDocumentPayloadBuilder::EVENTO_CONTINGENCIA) {
            $resolvido = $this->resolverRetryContingenciaObsoleta($empresa, $log);
            if ($resolvido !== null) {
                return $resolvido;
            }
        }

        $documento = json_decode((string) $log->payload_json, true);

        if (! is_array($documento)) {
            $log->update([
                'status' => ContadorCloudSyncLog::STATUS_FAILED,
                'error_message' => 'Payload inválido para reenvio.',
            ]);

            return $log->fresh() ?? $log;
        }

        if (! $config->enviarXml) {
            unset($documento['xml_base64']);
        }

        $payload = $this->payloadBuilder->buildEnvelope($config, $documento);

        return $this->enviar($empresa, $config, $documento, $payload, $log, agendarRetrySeFalhar: false);
    }

    /**
     * Se a NFC-e/NF-e da contingência já foi autorizada, marca o log como ignorado e envia a emitida.
     */
    private function resolverRetryContingenciaObsoleta(Empresa $empresa, ContadorCloudSyncLog $log): ?ContadorCloudSyncLog
    {
        $refTipo = (string) ($log->referencia_type ?? '');
        $refId = (int) ($log->referencia_id ?? 0);

        if ($refId < 1) {
            return null;
        }

        if ($refTipo === 'pdv_venda_nfce') {
            $nfce = PdvVendaNfce::query()->find($refId);

            if (! $nfce || $nfce->status === PdvVendaNfce::STATUS_CONTINGENCIA) {
                return null;
            }

            $log->update([
                'status' => ContadorCloudSyncLog::STATUS_SKIPPED,
                'error_message' => 'Contingência não reenviada: nota já '.$nfce->status.'; envio da situação atual.',
            ]);

            if ($nfce->status === PdvVendaNfce::STATUS_AUTORIZADA && ! $nfce->simulada) {
                $this->dispatch(
                    $empresa,
                    $this->payloadBuilder->fromNfce($nfce, $empresa, ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO),
                    immediate: true,
                    force: true,
                );
            } elseif ($nfce->status === PdvVendaNfce::STATUS_CANCELADA && ! $nfce->simulada) {
                $this->dispatch(
                    $empresa,
                    $this->payloadBuilder->fromNfce($nfce, $empresa, ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO),
                    immediate: true,
                    force: true,
                );
            }

            return $log->fresh() ?? $log;
        }

        if ($refTipo === 'nfe') {
            $nfe = Nfe::query()->find($refId);

            if (! $nfe || $nfe->status === Nfe::STATUS_CONTINGENCIA) {
                return null;
            }

            $log->update([
                'status' => ContadorCloudSyncLog::STATUS_SKIPPED,
                'error_message' => 'Contingência não reenviada: NF-e já '.$nfe->status.'; envio da situação atual.',
            ]);

            if ($nfe->status === Nfe::STATUS_TRANSMITIDA) {
                $this->dispatch(
                    $empresa,
                    $this->payloadBuilder->fromNfe($nfe, $empresa, ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO),
                    immediate: true,
                    force: true,
                );
            }

            return $log->fresh() ?? $log;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function shouldDispatch(ContadorCloudConfig $config, array $documento): bool
    {
        $tipo = (string) ($documento['tipo'] ?? '');
        $evento = (string) ($documento['evento'] ?? ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO);

        if ($tipo === ContadorCloudDocumentPayloadBuilder::TIPO_NFSE) {
            if ($evento === ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO) {
                return $config->enviarNfse && $config->enviarCanceladas;
            }

            return $config->enviarNfse;
        }

        if ($evento === ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO) {
            return $config->enviarCanceladas;
        }

        if ($evento === ContadorCloudDocumentPayloadBuilder::EVENTO_CONTINGENCIA) {
            return $config->enviarVendas;
        }

        return match ($tipo) {
            ContadorCloudDocumentPayloadBuilder::TIPO_NFE_SAIDA,
            ContadorCloudDocumentPayloadBuilder::TIPO_NFCE_SAIDA => $config->enviarVendas,
            ContadorCloudDocumentPayloadBuilder::TIPO_NFE_ENTRADA,
            ContadorCloudDocumentPayloadBuilder::TIPO_COMPRA_ENTRADA,
            ContadorCloudDocumentPayloadBuilder::TIPO_NOTA_FORNECEDOR => $config->enviarCompras,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function criarPendente(Empresa $empresa, array $documento): ContadorCloudSyncLog
    {
        return ContadorCloudSyncLog::query()->create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => (string) ($documento['tipo'] ?? 'desconhecido'),
            'evento' => (string) ($documento['evento'] ?? ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO),
            'chave' => filled($documento['chave'] ?? null) ? (string) $documento['chave'] : null,
            'referencia_type' => (string) ($documento['referencia']['tipo'] ?? null),
            'referencia_id' => isset($documento['referencia']['id']) ? (int) $documento['referencia']['id'] : null,
            'status' => ContadorCloudSyncLog::STATUS_PENDING,
            'payload_json' => json_encode($documento, JSON_UNESCAPED_UNICODE),
            'attempts' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $documento
     * @param  array<string, mixed>  $payload
     */
    private function enviar(
        Empresa $empresa,
        ContadorCloudConfig $config,
        array $documento,
        array $payload,
        ?ContadorCloudSyncLog $log = null,
        bool $agendarRetrySeFalhar = false,
    ): ContadorCloudSyncLog {
        $log ??= $this->criarPendente($empresa, $documento);

        $resultado = $this->client->syncDocumento($config, $payload);

        $log->update([
            'attempts' => ((int) $log->attempts) + 1,
            'http_status' => $resultado['http_status'],
            'response_body' => $resultado['response'] !== null
                ? json_encode($resultado['response'], JSON_UNESCAPED_UNICODE)
                : null,
            'error_message' => $resultado['ok'] ? null : $resultado['message'],
            'status' => $resultado['ok']
                ? ContadorCloudSyncLog::STATUS_SENT
                : ContadorCloudSyncLog::STATUS_FAILED,
            'sent_at' => $resultado['ok'] ? now() : null,
        ]);

        if ($resultado['ok']) {
            Log::info('Portal do Contador: documento enviado.', [
                'empresa_id' => $empresa->id,
                'chave' => $documento['chave'] ?? null,
                'tipo' => $documento['tipo'] ?? null,
                'evento' => $documento['evento'] ?? null,
            ]);
        } else {
            Log::warning('Portal do Contador: falha no envio.', [
                'empresa_id' => $empresa->id,
                'chave' => $documento['chave'] ?? null,
                'tipo' => $documento['tipo'] ?? null,
                'evento' => $documento['evento'] ?? null,
                'message' => $resultado['message'],
            ]);

            if ($agendarRetrySeFalhar) {
                self::agendarRetryAutomatico((int) $empresa->id);
            }
        }

        return $log->fresh() ?? $log;
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function registrarIgnorado(Empresa $empresa, array $documento, string $motivo): ContadorCloudSyncLog
    {
        return ContadorCloudSyncLog::query()->create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => (string) ($documento['tipo'] ?? 'desconhecido'),
            'evento' => (string) ($documento['evento'] ?? ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO),
            'chave' => filled($documento['chave'] ?? null) ? (string) $documento['chave'] : null,
            'referencia_type' => (string) ($documento['referencia']['tipo'] ?? null),
            'referencia_id' => isset($documento['referencia']['id']) ? (int) $documento['referencia']['id'] : null,
            'status' => ContadorCloudSyncLog::STATUS_SKIPPED,
            'error_message' => $motivo,
            'payload_json' => json_encode($documento, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function jaRegistrado(Empresa $empresa, string $chave, string $evento): bool
    {
        return ContadorCloudSyncLog::query()
            ->where('empresa_id', $empresa->id)
            ->where('chave', $chave)
            ->where('evento', $evento)
            ->whereIn('status', [
                ContadorCloudSyncLog::STATUS_SENT,
                ContadorCloudSyncLog::STATUS_PENDING,
            ])
            ->exists();
    }

    private function marcarSuperseded(Empresa $empresa, string $chave, string $evento): void
    {
        ContadorCloudSyncLog::query()
            ->where('empresa_id', $empresa->id)
            ->where('chave', $chave)
            ->where('evento', $evento)
            ->whereIn('status', [
                ContadorCloudSyncLog::STATUS_SENT,
                ContadorCloudSyncLog::STATUS_PENDING,
            ])
            ->update([
                'status' => ContadorCloudSyncLog::STATUS_SKIPPED,
                'error_message' => 'Substituído por reenvio atualizado.',
            ]);
    }
}
