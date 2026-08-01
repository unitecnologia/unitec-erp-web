<?php

namespace App\Console\Commands;

use App\Support\Erp\Backup\DatabaseBackupService;
use Illuminate\Console\Command;

class ErpBackupCommand extends Command
{
    protected $signature = 'erp:backup
                            {--scheduled : Respeita habilitação e intervalo da empresa}
                            {--pre-update : Backup forçado antes de atualizar (banco + .env)}
                            {--empresa= : ID da empresa (opcional)}';

    protected $description = 'Gera backup MySQL (mysqldump) do ERP';

    public function handle(DatabaseBackupService $backup): int
    {
        $empresaId = filled($this->option('empresa')) ? (int) $this->option('empresa') : null;
        $scheduled = (bool) $this->option('scheduled');
        $preUpdate = (bool) $this->option('pre-update');

        if ($preUpdate) {
            $this->info('Gerando backup pré-update (banco + .env)…');
            $result = $backup->runPreUpdate($empresaId);
        } else {
            $this->info($scheduled ? 'Backup agendado…' : 'Gerando backup…');
            $result = $backup->run($empresaId, scheduled: $scheduled);
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
