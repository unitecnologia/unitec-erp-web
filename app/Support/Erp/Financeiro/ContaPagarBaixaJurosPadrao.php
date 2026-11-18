<?php

namespace App\Support\Erp\Financeiro;

use Illuminate\Support\Carbon;

final class ContaPagarBaixaJurosPadrao
{
    public const PERC_JUROS_MES = 1.0;

    public const DIAS_MES = 30;

    public const PERC_MULTA = 2.0;

    public static function dataIsoValida(?string $data): bool
    {
        $data = trim((string) $data);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1) {
            return false;
        }

        try {
            $parsed = Carbon::parse($data)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        return $parsed->toDateString() === $data;
    }

    public static function diasAtraso(?string $vencimento, ?string $pagoEm): int
    {
        $vencimento = trim((string) $vencimento);
        $pagoEm = trim((string) $pagoEm);
        if ($vencimento === '' || $pagoEm === '' || ! self::dataIsoValida($pagoEm)) {
            return 0;
        }

        try {
            $inicio = Carbon::parse($vencimento)->startOfDay();
            $fim = Carbon::parse($pagoEm)->startOfDay();
        } catch (\Throwable) {
            return 0;
        }

        if ($fim->lte($inicio)) {
            return 0;
        }

        return (int) $inicio->diffInDays($fim);
    }

    public static function juros(float $saldo, int $dias, float $percAoMes = self::PERC_JUROS_MES): float
    {
        $saldo = round($saldo, 2);
        $percAoMes = round($percAoMes, 2);
        if ($saldo <= 0 || $dias <= 0 || $percAoMes <= 0) {
            return 0.0;
        }

        return round($saldo * ($percAoMes / 100) * ($dias / self::DIAS_MES), 2);
    }

    public static function multa(float $saldo, int $dias, float $perc = self::PERC_MULTA): float
    {
        $saldo = round($saldo, 2);
        $perc = round($perc, 2);
        if ($saldo <= 0 || $dias <= 0 || $perc <= 0) {
            return 0.0;
        }

        return round($saldo * ($perc / 100), 2);
    }
}
