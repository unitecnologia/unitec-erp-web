<?php

namespace App\Support\Erp\Os;

/**
 * Medição opt-in do Concluir OS (DEV).
 * Ativar: localStorage/erpOsFinPerf no browser OU arquivo storage/framework/os-fin-perf.on
 *         OU env ERP_OS_FIN_PERF=1
 */
final class OsFinPerf
{
    /** @var array<int, array{label: string, ms: float, at: float}> */
    private static array $marks = [];

    private static ?float $startedAt = null;

    private static int $queryCount = 0;

    private static float $queryMs = 0.0;

    private static bool $queryListening = false;

    public static function enabled(): bool
    {
        if (filter_var(env('ERP_OS_FIN_PERF', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return is_file(storage_path('framework/os-fin-perf.on'));
    }

    public static function reset(): void
    {
        self::$marks = [];
        self::$startedAt = hrtime(true) / 1e6;
        self::$queryCount = 0;
        self::$queryMs = 0.0;
    }

    public static function mark(string $label): void
    {
        if (! self::enabled()) {
            return;
        }

        if (self::$startedAt === null) {
            self::reset();
        }

        $now = hrtime(true) / 1e6;
        $prev = self::$marks === []
            ? self::$startedAt
            : self::$marks[array_key_last(self::$marks)]['at'];

        self::$marks[] = [
            'label' => $label,
            'ms' => round($now - (float) $prev, 2),
            'at' => $now,
        ];
    }

    public static function listenQueries(): void
    {
        if (! self::enabled() || self::$queryListening) {
            return;
        }

        self::$queryListening = true;

        \Illuminate\Support\Facades\DB::listen(function ($query): void {
            self::$queryCount++;
            self::$queryMs += (float) $query->time;
        });
    }

    /**
     * @return array{
     *   total_ms: float,
     *   marks: list<array{label: string, ms: float}>,
     *   query_count: int,
     *   query_ms: float
     * }
     */
    public static function snapshot(): array
    {
        $total = 0.0;
        $marks = [];

        foreach (self::$marks as $mark) {
            $total += $mark['ms'];
            $marks[] = ['label' => $mark['label'], 'ms' => $mark['ms']];
        }

        return [
            'total_ms' => round($total, 2),
            'marks' => $marks,
            'query_count' => self::$queryCount,
            'query_ms' => round(self::$queryMs, 2),
        ];
    }

    public static function flushToLog(string $context = 'os-fin-perf'): void
    {
        if (! self::enabled()) {
            return;
        }

        $snap = self::snapshot();
        \Illuminate\Support\Facades\Log::info($context, $snap);

        $path = storage_path('logs/os-fin-perf-last.json');
        @file_put_contents($path, json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
