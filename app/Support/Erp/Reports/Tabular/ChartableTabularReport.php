<?php

namespace App\Support\Erp\Reports\Tabular;

use Illuminate\Http\Request;

/**
 * Relatórios tabulares que expõem um gráfico opcional (dados já agregados).
 */
interface ChartableTabularReport
{
    /**
     * Metadados leves para a UI do gráfico (tipo, opções de empresa, etc.).
     *
     * @return array{
     *     type: string,
     *     show_empresa_select: bool,
     *     empresas: list<array{id: int, label: string}>,
     *     default_empresa: string,
     *     supports_yoy: bool
     * }
     */
    public function chartConfig(): array;

    /**
     * Payload Chart.js já resumido (labels + datasets). Sem milhares de linhas.
     *
     * @return array{
     *     labels: list<string>,
     *     datasets: list<array{label: string, data: list<float|int>, borderColor?: string, backgroundColor?: string}>,
     *     meta: array<string, mixed>
     * }
     */
    public function chartData(Request $request): array;
}
