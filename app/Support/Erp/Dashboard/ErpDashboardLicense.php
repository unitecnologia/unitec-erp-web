<?php

namespace App\Support\Erp\Dashboard;

use Carbon\Carbon;
use Throwable;

class ErpDashboardLicense
{
    /**
     * @return array<string, mixed>
     */
    public static function kpi(): array
    {
        $expiresAt = static::expiresAt();
        $daysRemaining = static::daysRemaining($expiresAt);

        $tone = match (true) {
            $daysRemaining === null => 'slate',
            $daysRemaining < 0 => 'red',
            $daysRemaining <= 7 => 'red',
            $daysRemaining <= 20 => 'orange',
            default => 'amber',
        };

        $value = match (true) {
            $daysRemaining === null => '—',
            $daysRemaining < 0 => 'Vencida',
            $daysRemaining === 0 => 'Vence hoje',
            $daysRemaining === 1 => 'Falta 1 dia',
            default => "Faltam {$daysRemaining} dias",
        };

        $hint = match (true) {
            $daysRemaining === null => 'Data de licença não configurada',
            $daysRemaining < 0 => 'Regularize para continuar usando',
            default => 'Para vencer o sistema',
        };

        return [
            'key' => 'licenca_sistema',
            'label' => 'Licença do sistema',
            'value' => $value,
            'hint' => $hint,
            'tone' => $tone,
            'icon' => 'heroicon-o-shield-exclamation',
            'action_url' => config('unitec.pagamento_url'),
            'action_label' => 'Clique aqui para pagar',
        ];
    }

    private static function expiresAt(): ?Carbon
    {
        $raw = trim((string) config('unitec.licenca', ''));

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $raw)->startOfDay();
        } catch (Throwable) {
            try {
                return Carbon::parse($raw)->startOfDay();
            } catch (Throwable) {
                return null;
            }
        }
    }

    private static function daysRemaining(?Carbon $expiresAt): ?int
    {
        if ($expiresAt === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($expiresAt, false);
    }
}
