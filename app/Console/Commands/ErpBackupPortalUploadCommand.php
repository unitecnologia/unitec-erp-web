<?php

namespace App\Console\Commands;

use App\Support\Erp\Backup\PortalBkpFileUploader;
use Illuminate\Console\Command;

class ErpBackupPortalUploadCommand extends Command
{
    protected $signature = 'erp:backup-portal-upload';

    protected $description = 'Reenvia arquivos de backup pendentes ao portal (mesmos UUID/metadados)';

    public function handle(PortalBkpFileUploader $uploader): int
    {
        $result = $uploader->attemptDueJobs();
        $message = (string) ($result['message'] ?? 'Sem pendências.');

        if (($result['queued'] ?? 0) === 0) {
            $this->line($message !== '' ? $message : 'Nenhum envio pendente no momento.');

            return self::SUCCESS;
        }

        if (($result['ok'] ?? false) === true) {
            $this->info($message);
        } else {
            $this->warn($message);
        }

        $this->line(sprintf(
            'Tentativas nesta execução: %d · confirmados: %d · falhas: %d',
            (int) ($result['queued'] ?? 0),
            (int) ($result['confirmed'] ?? 0),
            (int) ($result['failed'] ?? 0),
        ));

        return self::SUCCESS;
    }
}
