<?php

namespace App\Support\Erp;

use Filament\Notifications\Notification;

/**
 * Notificação do painel. Sucesso some sozinho em ~1s; erro, alerta e as demais
 * continuam com a duração que o Filament (ou a tela) já definiu.
 */
class ErpFilamentNotification extends Notification
{
    public const SUCCESS_DURATION_MS = 1000;

    public function getDuration(): int|string
    {
        if ($this->getStatus() === 'success') {
            return self::SUCCESS_DURATION_MS;
        }

        return parent::getDuration();
    }
}
