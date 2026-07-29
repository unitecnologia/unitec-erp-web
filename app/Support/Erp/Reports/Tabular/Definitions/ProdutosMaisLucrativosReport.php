<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

class ProdutosMaisLucrativosReport extends AbstractLucratividadeReport
{
    public function slug(): string
    {
        return 'produtos-mais-lucrativos';
    }

    public function title(): string
    {
        return 'PRODUTOS MAIS LUCRATIVOS';
    }

    protected function sortOrder(): string
    {
        return 'desc';
    }
}
