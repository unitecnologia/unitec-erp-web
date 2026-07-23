<?php

namespace App\Console\Commands;

use App\Support\Gestor\GestorPushService;
use Illuminate\Console\Command;

class GestorPushAlertasCommand extends Command
{
    protected $signature = 'gestor:push-alertas';

    protected $description = 'Envia push do Painel Executivo (estoque baixo, contas vencidas, metas)';

    public function handle(GestorPushService $push): int
    {
        if (! $push->isConfigured()) {
            $this->warn('Web Push não configurado (VAPID).');

            return self::SUCCESS;
        }

        $stats = $push->dispararAlertasDiarios();
        $this->info('Push alertas: '.json_encode($stats, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
