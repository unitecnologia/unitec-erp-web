<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\ErpSchema;
use App\Models\Nfe;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class ErpDashboardFiscalDocsChart
{
    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string, empty?: bool}
     */
    public static function data(int|array|null $empresaScope = null): array
    {
        return static::fromDatabase($empresaScope) ?? [
            'labels' => ['NFe Aut.', 'NFe Pend.', 'NFCe Aut.', 'NFCe Pend.'],
            'values' => [0.0, 0.0, 0.0, 0.0],
            'colors' => ['#1d4ed8', '#93c5fd', '#0f766e', '#f59e0b'],
            'unit' => 'count',
            'empty' => true,
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string, empty?: bool}|null
     */
    private static function fromDatabase(int|array|null $empresaScope = null): ?array
    {
        try {
            if ($empresaScope === null) {
                $empresaId = ErpDashboardCertificadoAlert::resolveEmpresaId();
                $empresaScope = ($empresaId && $empresaId > 0) ? $empresaId : null;
            }

            $hoje = ErpFinanceiroMetricas::hoje();
            $inicio = $hoje->copy()->startOfMonth();
            $fim = $hoje;

            $nfeAut = static::countNfe($empresaScope, $inicio, $fim, autorizadas: true);
            $nfePend = static::countNfe($empresaScope, $inicio, $fim, autorizadas: false);
            $nfceAut = static::countNfce($empresaScope, $inicio, $fim, autorizadas: true);
            $nfcePend = static::countNfce($empresaScope, $inicio, $fim, autorizadas: false);

            $empty = ($nfeAut + $nfePend + $nfceAut + $nfcePend) <= 0;

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
                'empty' => $empty,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function countNfe(int|array|null $empresaScope, Carbon $inicio, Carbon $fim, bool $autorizadas): int
    {
        if (! ErpSchema::hasTable((new Nfe)->getTable())) {
            return 0;
        }

        $query = Nfe::query()
            ->whereDate('data_emissao', '>=', $inicio->toDateString())
            ->whereDate('data_emissao', '<=', $fim->toDateString());

        ErpFinanceiroMetricas::applyEmpresaColumn($query, (new Nfe)->getTable(), $empresaScope);

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

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function countNfce(int|array|null $empresaScope, Carbon $inicio, Carbon $fim, bool $autorizadas): int
    {
        if (! ErpSchema::hasTable((new PdvVendaNfce)->getTable())) {
            return 0;
        }

        $query = PdvVendaNfce::query()
            ->whereHas('pdvVenda', function (Builder $venda) use ($inicio, $fim): void {
                $venda->whereDate('fechado_em', '>=', $inicio->toDateString())
                    ->whereDate('fechado_em', '<=', $fim->toDateString());
            });

        static::applyNfceEmpresaScope($query, $empresaScope);

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

    /**
     * Mesmo critério da tela NFC-e: empresa_id no scope OU (nulo + sessão PDV).
     *
     * @param  int|list<int>|null  $empresaScope
     */
    private static function applyNfceEmpresaScope(Builder $query, int|array|null $empresaScope): void
    {
        if ($empresaScope === null) {
            return;
        }

        $ids = is_array($empresaScope)
            ? array_values(array_filter(array_map('intval', $empresaScope)))
            : [(int) $empresaScope];
        $ids = array_values(array_filter($ids, fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return;
        }

        $query->where(function (Builder $outer) use ($ids): void {
            $outer->whereIn('empresa_id', $ids)
                ->orWhere(function (Builder $inner) use ($ids): void {
                    $inner->whereNull('empresa_id')
                        ->whereHas('pdvVenda.sessao', fn (Builder $sessao): Builder => $sessao
                            ->whereIn('empresa_id', $ids));
                });
        });
    }
}
