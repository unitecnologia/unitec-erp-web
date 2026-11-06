<?php

namespace App\Support\Erp;

use Closure;
use Filament\Notifications\Notification;

/**
 * Notificação do painel. Sucesso some sozinho em ~1s; erro, alerta e as demais
 * continuam com a duração que o Filament (ou a tela) já definiu.
 */
class ErpFilamentNotification extends Notification
{
    public const SUCCESS_DURATION_MS = 1000;

    private bool $explicitDuration = false;

    public function duration(Closure|string|int|null $duration): static
    {
        $this->explicitDuration = true;

        return parent::duration($duration);
    }

    public function getDuration(): int|string
    {
        // Sucesso some rápido por padrão; duração explícita (ex.: consulta CNPJ) prevalece.
        if ($this->getStatus() === 'success' && ! $this->explicitDuration) {
            return self::SUCCESS_DURATION_MS;
        }

        return parent::getDuration();
    }
}
