<?php

namespace App\Support\ContadorCloud;

use App\Jobs\ContadorCloudProcessPendingJob;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NotaFornecedor;
use App\Models\PdvVendaNfce;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Enfileira e envia ao Portal do Contador os documentos fiscais
 * do mês atual e do mês anterior (NF-e, NFC-e, contingência, compras).
 */
final class ContadorCloudMesAtualBackfillService
{
    public function __construct(
        private readonly ContadorCloudDocumentPayloadBuilder $payloadBuilder = new ContadorCloudDocumentPayloadBuilder(),
        private readonly ContadorCloudSyncService $syncService = new ContadorCloudSyncService(),
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     competencia: string,
     *     enfileirados: int,
     *     enviados: int,
     *     falhas: int,
     *     pendentes: int
     * }
     */
    public function enviarMesAtual(Empresa $empresa, int $processLimit = 300): array
    {
        $config = ContadorCloudConfig::fromEmpresa($empresa);

        $mesAnterior = now()->copy()->subMonthNoOverflow()->startOfMonth();
        $mesAtual = now()->copy()->startOfMonth();
        $competenciaLabel = $mesAnterior->format('m/Y').'–'.$mesAtual->format('m/Y');

        if (! $config->isActive()) {
            return [
                'ok' => false,
                'message' => 'Conecte o Portal do Contador (token) e habilite o envio antes de subir os documentos.',
                'competencia' => $competenciaLabel,
                'enfileirados' => 0,
                'enviados' => 0,
                'falhas' => 0,
                'pendentes' => 0,
            ];
        }

        // Do dia 1 do mês anterior até o fim do mês atual.
        $inicio = $mesAnterior->copy()->startOfDay();
        $fim = $mesAtual->copy()->endOfMonth()->endOfDay();

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $enfileirados = 0;
        $enfileirados += $this->enfileirarNfes($empresa, $inicio, $fim);
        $enfileirados += $this->enfileirarNfces($empresa, $inicio, $fim);
        $enfileirados += $this->enfileirarNotasFornecedor($empresa, $inicio, $fim);

        $resultado = $this->syncService->processPending((int) $empresa->id, max(1, $processLimit));

        $pendentes = $this->contarPendentes((int) $empresa->id);

        if ($pendentes > 0) {
            ContadorCloudProcessPendingJob::dispatch((int) $empresa->id, 500)->afterResponse();
        }

        Log::info('Portal do Contador: backfill mês atual + anterior.', [
            'empresa_id' => $empresa->id,
            'competencia' => $competenciaLabel,
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'enfileirados' => $enfileirados,
            'enviados' => $resultado['enviados'],
            'falhas' => $resultado['falhas'],
            'pendentes' => $pendentes,
        ]);

        $message = sprintf(
            'Período %s (NF-e, NFC-e, contingência e compras): %d documento(s) preparado(s), %d enviado(s), %d falha(s).',
            $competenciaLabel,
            $enfileirados,
            $resultado['enviados'],
            $resultado['falhas'],
        );

        if ($pendentes > 0) {
            $message .= sprintf(' %d ainda em fila (continua em segundo plano).', $pendentes);
        } elseif ($enfileirados === 0 && $resultado['enviados'] === 0) {
            $message = sprintf(
                'Nenhum documento novo do período %s para enviar (já enviados ou fora das flags).',
                $competenciaLabel,
            );
        }

        return [
            'ok' => true,
            'message' => $message,
            'competencia' => $competenciaLabel,
            'enfileirados' => $enfileirados,
            'enviados' => $resultado['enviados'],
            'falhas' => $resultado['falhas'],
            'pendentes' => $pendentes,
        ];
    }

    private function enfileirarNfes(Empresa $empresa, Carbon $inicio, Carbon $fim): int
    {
        $count = 0;

        Nfe::query()
            ->where('empresa_id', $empresa->id)
            ->whereBetween('data_emissao', [$inicio->toDateString(), $fim->toDateString()])
            ->whereNotNull('chave')
            ->where('chave', '!=', '')
            ->whereIn('status', [Nfe::STATUS_TRANSMITIDA, Nfe::STATUS_CANCELADA, Nfe::STATUS_CONTINGENCIA])
            ->orderBy('id')
            ->cursor()
            ->each(function (Nfe $nfe) use ($empresa, &$count): void {
                $evento = match ($nfe->status) {
                    Nfe::STATUS_CANCELADA => ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO,
                    Nfe::STATUS_CONTINGENCIA => ContadorCloudDocumentPayloadBuilder::EVENTO_CONTINGENCIA,
                    default => ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO,
                };

                $log = $this->syncService->dispatch(
                    $empresa,
                    $this->payloadBuilder->fromNfe($nfe, $empresa, $evento),
                    immediate: false,
                );

                if ($log !== null && $log->status === \App\Models\ContadorCloudSyncLog::STATUS_PENDING) {
                    $count++;
                }
            });

        return $count;
    }

    private function enfileirarNfces(Empresa $empresa, Carbon $inicio, Carbon $fim): int
    {
        $count = 0;

        PdvVendaNfce::query()
            ->where('empresa_id', $empresa->id)
            ->where(function ($query): void {
                $query->where('simulada', false)->orWhereNull('simulada');
            })
            ->whereNotNull('chave')
            ->where('chave', '!=', '')
            ->whereIn('status', [
                PdvVendaNfce::STATUS_AUTORIZADA,
                PdvVendaNfce::STATUS_CANCELADA,
                PdvVendaNfce::STATUS_CONTINGENCIA,
            ])
            ->where(function ($query) use ($inicio, $fim): void {
                $query
                    ->whereBetween('autorizada_em', [$inicio, $fim])
                    ->orWhere(function ($inner) use ($inicio, $fim): void {
                        $inner->whereNull('autorizada_em')
                            ->whereBetween('created_at', [$inicio, $fim]);
                    })
                    ->orWhereBetween('cancelada_em', [$inicio, $fim]);
            })
            ->orderBy('id')
            ->cursor()
            ->each(function (PdvVendaNfce $nfce) use ($empresa, &$count): void {
                $evento = match ($nfce->status) {
                    PdvVendaNfce::STATUS_CANCELADA => ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO,
                    PdvVendaNfce::STATUS_CONTINGENCIA => ContadorCloudDocumentPayloadBuilder::EVENTO_CONTINGENCIA,
                    default => ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO,
                };

                $log = $this->syncService->dispatch(
                    $empresa,
                    $this->payloadBuilder->fromNfce($nfce, $empresa, $evento),
                    immediate: false,
                );

                if ($log !== null && $log->status === \App\Models\ContadorCloudSyncLog::STATUS_PENDING) {
                    $count++;
                }
            });

        return $count;
    }

    private function enfileirarNotasFornecedor(Empresa $empresa, Carbon $inicio, Carbon $fim): int
    {
        $count = 0;
        $inicioData = $inicio->toDateString();
        $fimData = $fim->toDateString();

        NotaFornecedor::query()
            ->where('empresa_id', $empresa->id)
            ->whereNotNull('chave')
            ->where('chave', '!=', '')
            ->where(function ($query) use ($inicioData, $fimData): void {
                $query
                    ->whereBetween('data_emissao', [$inicioData, $fimData])
                    ->orWhereBetween('data_entrada', [$inicioData, $fimData]);
            })
            ->orderBy('id')
            ->cursor()
            ->each(function (NotaFornecedor $nota) use ($empresa, &$count): void {
                $xml = filled($nota->xml) ? (string) $nota->xml : null;

                $log = $this->syncService->dispatch(
                    $empresa,
                    $this->payloadBuilder->fromNotaFornecedor($nota, $empresa, $xml),
                    immediate: false,
                );

                if ($log !== null && $log->status === \App\Models\ContadorCloudSyncLog::STATUS_PENDING) {
                    $count++;
                }
            });

        return $count;
    }

    private function contarPendentes(int $empresaId): int
    {
        return (int) \App\Models\ContadorCloudSyncLog::query()
            ->where('empresa_id', $empresaId)
            ->where('status', \App\Models\ContadorCloudSyncLog::STATUS_PENDING)
            ->count();
    }
}
