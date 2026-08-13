<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

class ProdutosMenosLucrativosReport extends AbstractLucratividadeReport
{
    public function slug(): string
    {
        return 'produtos-menos-lucrativos';
    }

    public function title(): string
    {
        return 'PRODUTOS MENOS LUCRATIVOS';
    }

    protected function sortOrder(): string
    {
        return 'asc';
    }
}
