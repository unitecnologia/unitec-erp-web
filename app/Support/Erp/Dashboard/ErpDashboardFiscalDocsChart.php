<?php

namespace App\Support\Erp\Dashboard;

use App\Models\Nfe;
use App\Models\PdvVendaNfce;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ErpDashboardFiscalDocsChart
{
    /**
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string}
     */
    public static function data(?int $empresaId = null): array
    {
        $real = static::fromDatabase($empresaId);

        if ($real !== null) {
            return $real;
        }

        return ErpDashboardDemoData::fiscalDocsChart();
    }

    /**
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string}|null
     */
    private static function fromDatabase(?int $empresaId = null): ?array
    {
        try {
            $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();
            $inicio = Carbon::today()->startOfMonth();
            $fim = Carbon::today()->endOfMonth();

            $nfeAut = static::countNfe($empresaId, $inicio, $fim, autorizadas: true);
            $nfePend = static::countNfe($empresaId, $inicio, $fim, autorizadas: false);
            $nfceAut = static::countNfce($empresaId, $inicio, $fim, autorizadas: true);
            $nfcePend = static::countNfce($empresaId, $inicio, $fim, autorizadas: false);

            if ($nfeAut + $nfePend + $nfceAut + $nfcePend <= 0) {
                return null;
            }

            return [
                'labels' => ['NFe Aut.', 'NFe Pend.', 'NFCe Aut.', 'NFCe Pend.'],
                'values' => [
                    (float) $nfeAut,
                    (float) $nfePend,
                    (float) $nfceAut,
                    (float) $nfcePend,
                ],
                'colors' => ['#1d4ed8', '#93c5fd', '#0f766e', '#f59e0b'],
                'unit' => 'count',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private static function countNfe(?int $empresaId, Carbon $inicio, Carbon $fim, bool $autorizadas): int
    {
        if (! Schema::hasTable((new Nfe)->getTable())) {
            return 0;
        }

        $query = Nfe::query()
            ->whereDate('data_emissao', '>=', $inicio->toDateString())
            ->whereDate('data_emissao', '<=', $fim->toDateString());

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        if ($autorizadas) {
            $query->where(function ($builder): void {
                $builder->where('situacao', Nfe::SITUACAO_TRANSMITIDA)
                    ->orWhere('status', Nfe::STATUS_TRANSMITIDA);
            });
        } else {
            $query->where(function ($builder): void {
                $builder->whereIn('situacao', [
                    Nfe::SITUACAO_ABERTA,
                    Nfe::SITUACAO_CONTINGENCIA,
                ])->orWhereIn('status', [
                    Nfe::STATUS_ABERTA,
                    Nfe::STATUS_CONTINGENCIA,
                ]);
            });
        }

        return (int) $query->count();
    }

    private static function countNfce(?int $empresaId, Carbon $inicio, Carbon $fim, bool $autorizadas): int
    {
        if (! Schema::hasTable((new PdvVendaNfce)->getTable())) {
            return 0;
        }

        $query = PdvVendaNfce::query()
            ->where(function ($period) use ($inicio, $fim): void {
                $period->where(function ($auth) use ($inicio, $fim): void {
                    $auth->whereNotNull('autorizada_em')
                        ->whereDate('autorizada_em', '>=', $inicio->toDateString())
                        ->whereDate('autorizada_em', '<=', $fim->toDateString());
                })->orWhere(function ($fallback) use ($inicio, $fim): void {
                    $fallback->whereNull('autorizada_em')
                        ->whereDate('created_at', '>=', $inicio->toDateString())
                        ->whereDate('created_at', '<=', $fim->toDateString());
                });
            });

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        if ($autorizadas) {
            $query->whereIn('status', [
                PdvVendaNfce::STATUS_AUTORIZADA,
                PdvVendaNfce::STATUS_SIMULADA,
            ]);
        } else {
            $query->whereIn('status', [
                PdvVendaNfce::STATUS_PENDENTE,
                PdvVendaNfce::STATUS_CONTINGENCIA,
            ]);
        }

        return (int) $query->count();
    }
}
