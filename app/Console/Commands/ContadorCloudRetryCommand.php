<?php

namespace App\Console\Commands;

use App\Models\ContadorCloudSyncLog;
use App\Support\ContadorCloud\ContadorCloudSyncService;
use Illuminate\Console\Command;

class ContadorCloudRetryCommand extends Command
{
    protected $signature = 'contador-cloud:retry {--empresa= : ID da empresa} {--limit=50 : Quantidade máxima de reenvios} {--force : Reenvia mesmo registros já marcados como enviados}';

    protected $description = 'Reenvia documentos fiscais pendentes ou com falha para o Portal do Contador';

    public function handle(ContadorCloudSyncService $syncService): int
    {
        $force = (bool) $this->option('force');
        $limit = max(1, (int) $this->option('limit'));
        $empresaId = $this->option('empresa') ? (int) $this->option('empresa') : null;

        if ($force) {
            $query = ContadorCloudSyncLog::query()
                ->whereIn('status', [
                    ContadorCloudSyncLog::STATUS_FAILED,
                    ContadorCloudSyncLog::STATUS_PENDING,
                    ContadorCloudSyncLog::STATUS_SENT,
                ])
                ->orderBy('id')
                ->limit($limit);

            if ($empresaId) {
                $query->where('empresa_id', $empresaId);
            }

            $logs = $query->get();
            $enviados = 0;
            $falhas = 0;

            foreach ($logs as $log) {
                $resultado = $syncService->retry($log);
                if ($resultado->status === ContadorCloudSyncLog::STATUS_SENT) {
                    $enviados++;
                    $this->line('OK chave '.$resultado->chave);
                } else {
                    $falhas++;
                    $this->error('Falha chave '.$resultado->chave.': '.$resultado->error_message);
                }
            }

            $this->info("Reenvio concluído: {$enviados} enviado(s), {$falhas} falha(s).");

            return $falhas > 0 ? self::FAILURE : self::SUCCESS;
        }

        $resultado = $syncService->processRetryable($empresaId, $limit);
        $this->info(sprintf(
            'Reenvio automático: %d enviado(s), %d falha(s), %d ignorado(s).',
            $resultado['enviados'],
            $resultado['falhas'],
            $resultado['ignorados'] ?? 0,
        ));

        // Sempre SUCCESS no agendamento: falha de rede é esperada e será retentada no próximo minuto.
        return self::SUCCESS;
    }
}
