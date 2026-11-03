<?php

namespace App\Support\ContadorCloud;

use App\Jobs\ContadorCloudProcessPendingJob;
use App\Models\ContadorCloudSyncLog;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\Nfse;
use App\Models\NotaFornecedor;
use App\Models\PdvVendaNfce;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Enfileira e envia ao Portal do Contador os documentos fiscais
 * (NF-e, NFC-e, NFS-e, contingência, compras).
 */
final class ContadorCloudMesAtualBackfillService
{
    public function __construct(
        private readonly ContadorCloudDocumentPayloadBuilder $payloadBuilder = new ContadorCloudDocumentPayloadBuilder(),
        private readonly ContadorCloudSyncService $syncService = new ContadorCloudSyncService(),
    ) {}

    /**
     * Envia mês atual + mês anterior (pula o que já foi enviado com sucesso).
     *
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
        $mesAnterior = now()->copy()->subMonthNoOverflow()->startOfMonth();
        $mesAtual = now()->copy()->startOfMonth();
        $competenciaLabel = $mesAnterior->format('m/Y').'–'.$mesAtual->format('m/Y');

        return $this->enfileirarEProcessar(
            $empresa,
            $competenciaLabel,
            $mesAnterior->copy()->startOfDay(),
            $mesAtual->copy()->endOfMonth()->endOfDay(),
            force: false,
            processLimit: $processLimit,
            logTag: 'backfill mês atual + anterior',
        );
    }

    /**
     * Reenvia todo o histórico disponível (força novo envio mesmo se o ERP
     * já marcava como enviado — útil quando a nuvem perdeu os documentos).
     * No ERP, o log anterior da mesma chave/evento é marcado como ignorado
     * (não duplica fila ativa).
     *
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
    public function enviarHistoricoCompleto(Empresa $empresa, int $processLimit = 300): array
    {
        return $this->enfileirarEProcessar(
            $empresa,
            'histórico completo',
            null,
            null,
            force: true,
            processLimit: $processLimit,
            logTag: 'backfill histórico completo (force)',
        );
    }

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
    private function enfileirarEProcessar(
        Empresa $empresa,
        string $competenciaLabel,
        ?Carbon $inicio,
        ?Carbon $fim,
        bool $force,
        int $processLimit,
        string $logTag,
    ): array {
        $config = ContadorCloudConfig::fromEmpresa($empresa);

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

        if (function_exists('set_time_limit')) {
            @set_time_limit($force ? 600 : 180);
        }

        $enfileirados = 0;
        $enfileirados += $this->enfileirarNfes($empresa, $inicio, $fim, $force);
        $enfileirados += $this->enfileirarNfces($empresa, $inicio, $fim, $force);
        $enfileirados += $this->enfileirarNfses($empresa, $inicio, $fim, $force);
        $enfileirados += $this->enfileirarNotasFornecedor($empresa, $inicio, $fim, $force);

        $resultado = $this->syncService->processPending((int) $empresa->id, max(1, $processLimit));

        $pendentes = $this->contarPendentes((int) $empresa->id);

        if ($pendentes > 0) {
            ContadorCloudProcessPendingJob::dispatch((int) $empresa->id, 500)->afterResponse();
        }

        Log::info('Portal do Contador: '.$logTag.'.', [
            'empresa_id' => $empresa->id,
            'competencia' => $competenciaLabel,
            'inicio' => $inicio?->toDateString(),
            'fim' => $fim?->toDateString(),
            'force' => $force,
            'enfileirados' => $enfileirados,
            'enviados' => $resultado['enviados'],
            'falhas' => $resultado['falhas'],
            'pendentes' => $pendentes,
        ]);

        $message = sprintf(
            'Período %s (NF-e, NFC-e, NFS-e, contingência e compras): %d documento(s) preparado(s), %d enviado(s), %d falha(s).',
            $competenciaLabel,
            $enfileirados,
            $resultado['enviados'],
            $resultado['falhas'],
        );

        if ($pendentes > 0) {
            $message .= sprintf(' %d ainda em fila (continua em segundo plano).', $pendentes);
        } elseif ($enfileirados === 0 && $resultado['enviados'] === 0) {
            $message = $force
                ? sprintf('Nenhum documento do período %s para reenviar (fora das flags ou sem chave/XML).', $competenciaLabel)
                : sprintf(
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

    private function enfileirarNfes(Empresa $empresa, ?Carbon $inicio, ?Carbon $fim, bool $force): int
    {
        $count = 0;

        $query = Nfe::query()
            ->where('empresa_id', $empresa->id)
            ->whereNotNull('chave')
            ->where('chave', '!=', '')
            ->whereIn('status', [Nfe::STATUS_TRANSMITIDA, Nfe::STATUS_CANCELADA, Nfe::STATUS_CONTINGENCIA]);

        if ($inicio !== null && $fim !== null) {
            $query->whereBetween('data_emissao', [$inicio->toDateString(), $fim->toDateString()]);
        }

        $query
            ->orderBy('id')
            ->cursor()
            ->each(function (Nfe $nfe) use ($empresa, $force, &$count): void {
                $evento = match ($nfe->status) {
                    Nfe::STATUS_CANCELADA => ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO,
                    Nfe::STATUS_CONTINGENCIA => ContadorCloudDocumentPayloadBuilder::EVENTO_CONTINGENCIA,
                    default => ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO,
                };

                $log = $this->syncService->dispatch(
                    $empresa,
                    $this->payloadBuilder->fromNfe($nfe, $empresa, $evento),
                    immediate: false,
                    // Canceladas: sempre force (XML de evento não traz vNF).
                    // Histórico completo: force em tudo para a nuvem receber de novo.
                    force: $force || $evento === ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO,
                );

                if ($log !== null && $log->status === ContadorCloudSyncLog::STATUS_PENDING) {
                    $count++;
                }
            });

        return $count;
    }

    private function enfileirarNfces(Empresa $empresa, ?Carbon $inicio, ?Carbon $fim, bool $force): int
    {
        $count = 0;

        $query = PdvVendaNfce::query()
            ->where('empresa_id', $empresa->id)
            ->where(function ($inner): void {
                $inner->where('simulada', false)->orWhereNull('simulada');
            })
            ->whereNotNull('chave')
            ->where('chave', '!=', '')
            ->whereIn('status', [
                PdvVendaNfce::STATUS_AUTORIZADA,
                PdvVendaNfce::STATUS_CANCELADA,
                PdvVendaNfce::STATUS_CONTINGENCIA,
            ]);

        if ($inicio !== null && $fim !== null) {
            $query->where(function ($q) use ($inicio, $fim): void {
                $q
                    ->whereBetween('autorizada_em', [$inicio, $fim])
                    ->orWhere(function ($inner) use ($inicio, $fim): void {
                        $inner->whereNull('autorizada_em')
                            ->whereBetween('created_at', [$inicio, $fim]);
                    })
                    ->orWhereBetween('cancelada_em', [$inicio, $fim]);
            });
        }

        $query
            ->orderBy('id')
            ->cursor()
            ->each(function (PdvVendaNfce $nfce) use ($empresa, $force, &$count): void {
                $evento = match ($nfce->status) {
                    PdvVendaNfce::STATUS_CANCELADA => ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO,
                    PdvVendaNfce::STATUS_CONTINGENCIA => ContadorCloudDocumentPayloadBuilder::EVENTO_CONTINGENCIA,
                    default => ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO,
                };

                $log = $this->syncService->dispatch(
                    $empresa,
                    $this->payloadBuilder->fromNfce($nfce, $empresa, $evento),
                    immediate: false,
                    force: $force || $evento === ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO,
                );

                if ($log !== null && $log->status === ContadorCloudSyncLog::STATUS_PENDING) {
                    $count++;
                }
            });

        return $count;
    }

    private function enfileirarNfses(Empresa $empresa, ?Carbon $inicio, ?Carbon $fim, bool $force): int
    {
        $count = 0;

        $query = Nfse::query()
            ->where('empresa_id', $empresa->id)
            ->whereIn('status', [Nfse::STATUS_AUTORIZADA, Nfse::STATUS_CANCELADA])
            ->where(function ($q): void {
                $q
                    ->where(function ($xml): void {
                        $xml->whereNotNull('xml_nfse')->where('xml_nfse', '!=', '');
                    })
                    ->orWhere(function ($canc): void {
                        $canc->where('status', Nfse::STATUS_CANCELADA)
                            ->whereNotNull('xml_cancelamento')
                            ->where('xml_cancelamento', '!=', '');
                    });
            });

        if ($inicio !== null && $fim !== null) {
            $inicioData = $inicio->toDateString();
            $fimData = $fim->toDateString();
            $compInicio = $inicio->format('Y-m');
            $compFim = $fim->format('Y-m');

            $query->where(function ($q) use ($inicio, $fim, $inicioData, $fimData, $compInicio, $compFim): void {
                $q
                    ->whereBetween('data_emissao', [$inicioData, $fimData])
                    ->orWhereBetween('cancelada_em', [$inicio, $fim])
                    ->orWhere(function ($inner) use ($compInicio, $compFim): void {
                        $inner
                            ->whereNotNull('competencia')
                            ->where('competencia', '!=', '')
                            ->where('competencia', '>=', $compInicio)
                            ->where('competencia', '<=', $compFim);
                    });
            });
        }

        $query
            ->orderBy('id')
            ->cursor()
            ->each(function (Nfse $nfse) use ($empresa, $force, &$count): void {
                $evento = $nfse->status === Nfse::STATUS_CANCELADA
                    ? ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO
                    : ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO;

                $log = $this->syncService->dispatch(
                    $empresa,
                    $this->payloadBuilder->fromNfse($nfse, $empresa, $evento),
                    immediate: false,
                    force: $force || $evento === ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO,
                );

                if ($log !== null && $log->status === ContadorCloudSyncLog::STATUS_PENDING) {
                    $count++;
                }
            });

        return $count;
    }

    private function enfileirarNotasFornecedor(Empresa $empresa, ?Carbon $inicio, ?Carbon $fim, bool $force): int
    {
        $count = 0;

        $query = NotaFornecedor::query()
            ->where('empresa_id', $empresa->id)
            ->whereNotNull('chave')
            ->where('chave', '!=', '');

        if ($inicio !== null && $fim !== null) {
            $inicioData = $inicio->toDateString();
            $fimData = $fim->toDateString();

            $query->where(function ($q) use ($inicioData, $fimData): void {
                $q
                    ->whereBetween('data_emissao', [$inicioData, $fimData])
                    ->orWhereBetween('data_entrada', [$inicioData, $fimData]);
            });
        }

        $query
            ->orderBy('id')
            ->cursor()
            ->each(function (NotaFornecedor $nota) use ($empresa, $force, &$count): void {
                $xml = filled($nota->xml) ? (string) $nota->xml : null;

                $log = $this->syncService->dispatch(
                    $empresa,
                    $this->payloadBuilder->fromNotaFornecedor($nota, $empresa, $xml),
                    immediate: false,
                    force: $force,
                );

                if ($log !== null && $log->status === ContadorCloudSyncLog::STATUS_PENDING) {
                    $count++;
                }
            });

        return $count;
    }

    private function contarPendentes(int $empresaId): int
    {
        return (int) ContadorCloudSyncLog::query()
            ->where('empresa_id', $empresaId)
            ->where('status', ContadorCloudSyncLog::STATUS_PENDING)
            ->count();
    }
}
