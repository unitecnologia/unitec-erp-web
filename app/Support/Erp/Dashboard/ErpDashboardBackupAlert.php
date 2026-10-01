<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\ErpSystemConfig;
use App\Support\Erp\ErpTimezone;
use Throwable;

final class ErpDashboardBackupAlert
{
    /**
     * @return array{tone: string, title: string, time: string}
     */
    public static function resolve(): array
    {
        $status = mb_strtolower(trim(ErpSystemConfig::backupLastStatus()), 'UTF-8');
        $failed = in_array($status, ['failed', 'erro', 'error', 'falha'], true);
        $at = self::formatBackupAt(ErpSystemConfig::backupLastAt());

        if ($failed) {
            return [
                'tone' => 'red',
                'title' => 'Backup automático falhou',
                'time' => $at ?? 'Sem data registrada',
            ];
        }

        if ($status === '' || $at === null) {
            return [
                'tone' => 'amber',
                'title' => 'Backup automático sem registro',
                'time' => 'Ainda não há backup concluído',
            ];
        }

        return [
            'tone' => 'green',
            'title' => 'Backup automático concluído',
            'time' => $at,
        ];
    }

    private static function formatBackupAt(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        try {
            return ErpTimezone::toLocal($raw)->format('d/m/Y H:i:s');
        } catch (Throwable) {
            return $raw;
        }
    }
}
