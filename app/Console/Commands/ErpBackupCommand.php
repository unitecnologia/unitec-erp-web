<?php

namespace App\Console\Commands;

use App\Support\Erp\Backup\DatabaseBackupService;
use Illuminate\Console\Command;

class ErpBackupCommand extends Command
{
    protected $signature = 'erp:backup
                            {--scheduled : Respeita habilitação e intervalo (configuração única do sistema)}
                            {--pre-update : Backup forçado antes de atualizar (banco + .env)}
                            {--empresa= : Ignorado (backup é único para todas as empresas)}';

    protected $description = 'Gera backup MySQL (mysqldump) do ERP';

    public function handle(DatabaseBackupService $backup): int
    {
        $scheduled = (bool) $this->option('scheduled');
        $preUpdate = (bool) $this->option('pre-update');

        if ($preUpdate) {
            $this->info('Gerando backup pré-update (banco + .env)…');
            $result = $backup->runPreUpdate(null);
        } else {
            $this->info($scheduled ? 'Backup agendado…' : 'Gerando backup…');
            $result = $backup->run(null, scheduled: $scheduled);
        }

        if (! ($result['ok'] ?? false)) {
            $this->error($result['message'] ?? 'Falha no backup.');

            return self::FAILURE;
        }

        $this->info($result['message'] ?? 'Backup concluído.');

        if (! empty($result['path'])) {
            $this->line('Arquivo: '.$result['path']);
        }

        if (($result['files_removed'] ?? 0) > 0) {
            $this->line('Arquivos antigos removidos: '.$result['files_removed']);
        }

        return self::SUCCESS;
    }
}
