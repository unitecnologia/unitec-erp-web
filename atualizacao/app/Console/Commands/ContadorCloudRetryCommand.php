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

        $query = ContadorCloudSyncLog::query()
            ->when(
                $force,
                fn ($builder) => $builder->whereIn('status', [
                    ContadorCloudSyncLog::STATUS_FAILED,
                    ContadorCloudSyncLog::STATUS_PENDING,
                    ContadorCloudSyncLog::STATUS_SENT,
                ]),
                fn ($builder) => $builder->whereIn('status', [
                    ContadorCloudSyncLog::STATUS_FAILED,
                    ContadorCloudSyncLog::STATUS_PENDING,
                ]),
            )
            ->orderBy('id');

        if ($empresaId = $this->option('empresa')) {
            $query->where('empresa_id', (int) $empresaId);
        }

        $logs = $query->limit((int) $this->option('limit'))->get();

        if ($logs->isEmpty()) {
            $this->info('Nenhum envio pendente de reenvio.');

            return self::SUCCESS;
        }

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
}
