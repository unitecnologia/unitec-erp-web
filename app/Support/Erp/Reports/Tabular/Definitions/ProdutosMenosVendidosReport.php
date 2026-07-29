<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

class ProdutosMenosVendidosReport extends AbstractRankingVendasReport
{
    public function slug(): string
    {
        return 'produtos-menos-vendidos';
    }

    public function title(): string
    {
        return 'PRODUTOS MENOS VENDIDOS';
    }

    protected function sortOrder(): string
    {
        return 'asc';
    }
}
