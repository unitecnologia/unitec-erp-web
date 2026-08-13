<?php

namespace App\Support\Erp\Dashboard;

final class ErpDashboardBackupAlert
{
    /**
     * @return array{tone: string, title: string, time: string}
     */
    public static function resolve(): array
    {
        $status = (string) config('unitec.backup_last_status', 'ok');

        if ($status === 'failed') {
            return [
                'tone' => 'red',
                'title' => 'Backup automático falhou',
                'time' => self::timeLabel(true),
            ];
        }

        return [
            'tone' => 'green',
            'title' => 'Backup automático concluído',
            'time' => self::timeLabel(false),
        ];
    }

    private static function timeLabel(bool $failed): string
    {
        $at = config('unitec.backup_last_at');

        if (filled($at)) {
            return (string) $at;
        }

        return $failed ? 'Hoje' : 'Ontem';
    }
}
