<?php

namespace App\Support\Erp\Reports\Tabular;

use Illuminate\Http\Request;

/**
 * Relatórios tabulares com detalhe sob demanda (expandir linha na prévia interativa).
 */
interface ExpandableTabularReport
{
    /**
     * Detalhes paginados da linha expandida. Sem carregar tudo na abertura do relatório.
     *
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     meta: array<string, mixed>
     * }
     */
    public function expandDetails(Request $request): array;
}
