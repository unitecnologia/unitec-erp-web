<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

class ProdutosMaisVendidosReport extends AbstractRankingVendasReport
{
    public function slug(): string
    {
        return 'produtos-mais-vendidos';
    }

    public function title(): string
    {
        return 'PRODUTOS MAIS VENDIDOS';
    }

    protected function sortOrder(): string
    {
        return 'desc';
    }
}
